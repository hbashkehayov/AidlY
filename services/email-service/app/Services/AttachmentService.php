<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttachmentService
{
    protected $storagePath;
    protected $maxFileSize;
    protected $allowedExtensions;
    protected $storageDriver;

    public function __construct()
    {
        $this->storagePath = 'email-attachments';
        $this->maxFileSize = env('EMAIL_MAX_ATTACHMENT_SIZE', 10485760); // 10MB
        $this->allowedExtensions = explode(',', env('EMAIL_ALLOWED_ATTACHMENT_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip'));
        $this->storageDriver = env('EMAIL_ATTACHMENT_STORAGE', 'local'); // Can be 'local' or 's3'
    }

    /**
     * Process and store email attachments locally
     */
    public function storeAttachments(array $attachments, string $ticketId, ?string $emailId = null): array
    {
        $storedAttachments = [];

        foreach ($attachments as $attachment) {
            try {
                // Validate attachment
                if (!$this->validateAttachment($attachment)) {
                    Log::warning("Attachment validation failed", [
                        'filename' => $attachment['filename'] ?? 'unknown',
                        'size' => $attachment['size'] ?? 0,
                    ]);
                    continue;
                }

                // Generate unique filename
                $originalName = $attachment['filename'] ?? 'attachment';
                $extension = $this->getFileExtension($originalName);
                $uniqueName = $this->generateUniqueFilename($originalName, $ticketId);

                // Decode base64 content
                $content = base64_decode($attachment['content_base64']);

                // Create directory structure
                $directory = $this->getAttachmentDirectory($ticketId, $emailId);

                // Store file
                $path = $this->storeFile($directory, $uniqueName, $content);

                // Store metadata
                $storedAttachment = [
                    'ticket_id' => $ticketId,
                    'email_id' => $emailId,
                    'original_name' => $originalName,
                    'stored_name' => $uniqueName,
                    'path' => $path,
                    'size' => strlen($content),
                    'mime_type' => $attachment['mime_type'] ?? $this->getMimeType($extension),
                    'extension' => $extension,
                    'is_inline' => $attachment['is_inline'] ?? false,
                    'content_id' => $attachment['content_id'] ?? null,
                    'storage_driver' => $this->storageDriver,
                    'checksum' => md5($content),
                    'created_at' => now(),
                ];

                $storedAttachments[] = $storedAttachment;

                Log::info("Attachment stored successfully", [
                    'ticket_id' => $ticketId,
                    'filename' => $originalName,
                    'path' => $path,
                    'size' => $storedAttachment['size'],
                ]);

            } catch (\Exception $e) {
                Log::error("Failed to store attachment", [
                    'ticket_id' => $ticketId,
                    'filename' => $attachment['filename'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $storedAttachments;
    }

    /**
     * Validate attachment
     */
    protected function validateAttachment(array $attachment): bool
    {
        // Check if required fields exist
        if (empty($attachment['filename']) || empty($attachment['content_base64'])) {
            return false;
        }

        // Check file size
        $size = $attachment['size'] ?? strlen(base64_decode($attachment['content_base64']));
        if ($size > $this->maxFileSize) {
            Log::warning("Attachment exceeds maximum size", [
                'filename' => $attachment['filename'],
                'size' => $size,
                'max_size' => $this->maxFileSize,
            ]);
            return false;
        }

        // Check file extension
        $extension = $this->getFileExtension($attachment['filename']);
        if (!in_array(strtolower($extension), $this->allowedExtensions)) {
            Log::warning("Attachment has disallowed extension", [
                'filename' => $attachment['filename'],
                'extension' => $extension,
                'allowed' => $this->allowedExtensions,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Store file to disk
     */
    protected function storeFile(string $directory, string $filename, string $content): string
    {
        $path = $directory . '/' . $filename;

        if ($this->storageDriver === 's3') {
            Storage::disk('s3')->put($path, $content, 'private');
        } else {
            // Store locally
            Storage::disk('local')->put($path, $content);
        }

        return $path;
    }

    /**
     * Get attachment directory structure
     */
    protected function getAttachmentDirectory(string $ticketId, ?string $emailId = null): string
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $directory = "{$this->storagePath}/{$year}/{$month}/{$day}/{$ticketId}";

        if ($emailId) {
            $directory .= "/{$emailId}";
        }

        return $directory;
    }

    /**
     * Generate unique filename
     */
    protected function generateUniqueFilename(string $originalName, string $ticketId): string
    {
        $extension = $this->getFileExtension($originalName);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = Str::slug($basename);

        $timestamp = time();
        $random = Str::random(6);

        return "{$basename}_{$ticketId}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get file extension from filename
     */
    protected function getFileExtension(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return strtolower($extension ?: 'bin');
    }

    /**
     * Get MIME type from extension
     */
    protected function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Retrieve attachment from storage
     */
    public function getAttachment(string $path): ?array
    {
        try {
            if ($this->storageDriver === 's3') {
                if (!Storage::disk('s3')->exists($path)) {
                    return null;
                }

                return [
                    'content' => Storage::disk('s3')->get($path),
                    'url' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30)),
                ];
            } else {
                if (!Storage::disk('local')->exists($path)) {
                    return null;
                }

                return [
                    'content' => Storage::disk('local')->get($path),
                    'path' => storage_path('app/' . $path),
                ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve attachment", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete attachment from storage
     */
    public function deleteAttachment(string $path): bool
    {
        try {
            if ($this->storageDriver === 's3') {
                return Storage::disk('s3')->delete($path);
            } else {
                return Storage::disk('local')->delete($path);
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete attachment", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clean up old attachments
     */
    public function cleanupOldAttachments(int $daysOld = 90): int
    {
        $deletedCount = 0;
        $cutoffDate = now()->subDays($daysOld);

        try {
            $directories = Storage::disk('local')->directories($this->storagePath);

            foreach ($directories as $yearDir) {
                $year = basename($yearDir);
                if ($year < $cutoffDate->year) {
                    Storage::disk('local')->deleteDirectory($yearDir);
                    $deletedCount++;
                    continue;
                }

                $monthDirs = Storage::disk('local')->directories($yearDir);
                foreach ($monthDirs as $monthDir) {
                    $month = basename($monthDir);
                    if ($year == $cutoffDate->year && $month < $cutoffDate->month) {
                        Storage::disk('local')->deleteDirectory($monthDir);
                        $deletedCount++;
                    }
                }
            }

            Log::info("Cleaned up old attachments", [
                'deleted_directories' => $deletedCount,
                'cutoff_date' => $cutoffDate->format('Y-m-d'),
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to cleanup old attachments", [
                'error' => $e->getMessage(),
            ]);
        }

        return $deletedCount;
    }

    /**
     * Get attachment statistics for a ticket
     */
    public function getTicketAttachmentStats(string $ticketId): array
    {
        $directory = $this->getAttachmentDirectory($ticketId, null);
        $stats = [
            'total_count' => 0,
            'total_size' => 0,
            'types' => [],
        ];

        try {
            if (Storage::disk('local')->exists($directory)) {
                $files = Storage::disk('local')->allFiles($directory);

                foreach ($files as $file) {
                    $stats['total_count']++;
                    $stats['total_size'] += Storage::disk('local')->size($file);

                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    if (!isset($stats['types'][$extension])) {
                        $stats['types'][$extension] = 0;
                    }
                    $stats['types'][$extension]++;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to get attachment stats", [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }
}