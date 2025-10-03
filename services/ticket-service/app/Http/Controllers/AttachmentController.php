<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * Store a new attachment
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'ticket_id' => 'required|uuid|exists:tickets,id',
            'comment_id' => 'nullable|uuid|exists:ticket_comments,id',
            'file_name' => 'required|string|max:255',
            'file_type' => 'nullable|string|max:100',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:100',
            'is_inline' => 'nullable|boolean',
            'content_base64' => 'required|string',
        ]);

        try {
            // Decode base64 content
            $fileContent = base64_decode($request->input('content_base64'));

            if ($fileContent === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid base64 content',
                ], 400);
            }

            // Generate unique file path
            $ticketId = $request->input('ticket_id');
            $fileName = $request->input('file_name');
            $sanitizedFileName = Str::slug(pathinfo($fileName, PATHINFO_FILENAME));
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = $sanitizedFileName . '_' . time() . '_' . Str::random(8) . '.' . $extension;
            $storagePath = "tickets/{$ticketId}/attachments/{$uniqueFileName}";

            // Store file using Laravel Storage (will use MinIO if configured)
            Storage::put($storagePath, $fileContent);

            // Create attachment record
            $attachment = Attachment::create([
                'ticket_id' => $ticketId,
                'comment_id' => $request->input('comment_id'),
                'uploaded_by_user_id' => null, // Set by auth middleware if available
                'uploaded_by_client_id' => null, // TODO: Get from ticket or email
                'file_name' => $fileName,
                'file_type' => $request->input('file_type'),
                'file_size' => $request->input('file_size') ?: strlen($fileContent),
                'storage_path' => $storagePath,
                'mime_type' => $request->input('mime_type') ?: 'application/octet-stream',
                'is_inline' => $request->input('is_inline', false),
            ]);

            Log::info('Attachment uploaded successfully', [
                'attachment_id' => $attachment->id,
                'ticket_id' => $ticketId,
                'file_name' => $fileName,
                'file_size' => $attachment->file_size,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully',
                'data' => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_size' => $attachment->file_size,
                    'file_size_human' => $attachment->file_size_human,
                    'mime_type' => $attachment->mime_type,
                    'is_inline' => $attachment->is_inline,
                    'created_at' => $attachment->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to upload attachment', [
                'error' => $e->getMessage(),
                'ticket_id' => $request->input('ticket_id'),
                'file_name' => $request->input('file_name'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download an attachment
     */
    public function download($id)
    {
        try {
            $attachment = Attachment::findOrFail($id);

            // Check if file exists in storage
            if (!Storage::exists($attachment->storage_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment file not found in storage',
                ], 404);
            }

            // Get file content
            $fileContent = Storage::get($attachment->storage_path);

            // Return file as download
            return response($fileContent)
                ->header('Content-Type', $attachment->mime_type)
                ->header('Content-Disposition', 'attachment; filename="' . $attachment->file_name . '"')
                ->header('Content-Length', strlen($fileContent));

        } catch (\Exception $e) {
            Log::error('Failed to download attachment', [
                'attachment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attachments for a ticket
     */
    public function getTicketAttachments($ticketId)
    {
        try {
            $ticket = Ticket::findOrFail($ticketId);

            $attachments = Attachment::where('ticket_id', $ticketId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_size' => $attachment->file_size,
                        'file_size_human' => $attachment->file_size_human,
                        'mime_type' => $attachment->mime_type,
                        'is_inline' => $attachment->is_inline,
                        'comment_id' => $attachment->comment_id,
                        'created_at' => $attachment->created_at,
                        'download_url' => "/api/v1/attachments/{$attachment->id}/download",
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $attachments,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attachments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an attachment
     */
    public function destroy($id)
    {
        try {
            $attachment = Attachment::findOrFail($id);

            // Delete file from storage
            if (Storage::exists($attachment->storage_path)) {
                Storage::delete($attachment->storage_path);
            }

            // Delete database record
            $attachment->delete();

            Log::info('Attachment deleted', [
                'attachment_id' => $id,
                'file_name' => $attachment->file_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete attachment', [
                'attachment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
