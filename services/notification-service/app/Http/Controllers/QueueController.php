<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class QueueController extends Controller
{
    /**
     * Process pending notifications
     */
    public function processNotifications(Request $request)
    {
        try {
            $limit = $request->get('limit', 50);

            $pendingNotifications = DB::table('notification_queue')
                ->where('status', 'pending')
                ->where('scheduled_at', '<=', \Carbon\Carbon::now())
                ->orderBy('priority', 'desc')
                ->orderBy('scheduled_at', 'asc')
                ->limit($limit)
                ->get();

            $processed = 0;
            $failed = 0;

            foreach ($pendingNotifications as $notification) {
                try {
                    // Mark as processing
                    DB::table('notification_queue')
                        ->where('id', $notification->id)
                        ->update([
                            'status' => 'processing',
                            'attempts' => ($notification->attempts ?? 0) + 1,
                            'started_at' => \Carbon\Carbon::now()
                        ]);

                    // Process based on type
                    $success = $this->processNotificationByType($notification);

                    if ($success) {
                        DB::table('notification_queue')
                            ->where('id', $notification->id)
                            ->update([
                                'status' => 'sent',
                                'sent_at' => \Carbon\Carbon::now(),
                                'updated_at' => \Carbon\Carbon::now()
                            ]);
                        $processed++;
                    } else {
                        $this->handleFailedNotification($notification);
                        $failed++;
                    }

                } catch (\Exception $e) {
                    DB::table('notification_queue')
                        ->where('id', $notification->id)
                        ->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'updated_at' => \Carbon\Carbon::now()
                        ]);
                    $failed++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification processing completed',
                'stats' => [
                    'processed' => $processed,
                    'failed' => $failed,
                    'total' => count($pendingNotifications)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send digest notifications
     */
    public function sendDigests(Request $request)
    {
        try {
            $frequency = $request->get('frequency', 'daily');
            $limit = $request->get('limit', 100);

            // Get users with digest enabled for this frequency
            $users = DB::table('notification_preferences')
                ->where('digest_enabled', true)
                ->where('digest_frequency', $frequency)
                ->limit($limit)
                ->get();

            $digestsSent = 0;

            foreach ($users as $user) {
                try {
                    $digestContent = $this->generateDigestContent($user->user_id, $frequency);

                    if (!empty($digestContent)) {
                        // Queue digest notification
                        DB::table('notification_queue')->insert([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'user_id' => $user->user_id,
                            'type' => 'email',
                            'event' => 'digest',
                            'subject' => 'Your ' . ucfirst($frequency) . ' Notification Digest',
                            'body' => $this->formatDigestBody($digestContent),
                            'data' => json_encode(['digest_data' => $digestContent]),
                            'priority' => 'low',
                            'status' => 'pending',
                            'scheduled_at' => \Carbon\Carbon::now(),
                            'created_at' => \Carbon\Carbon::now()
                        ]);

                        $digestsSent++;
                    }

                } catch (\Exception $e) {
                    // Log error but continue processing other users
                    error_log("Failed to generate digest for user {$user->user_id}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Digest notifications queued successfully',
                'digests_sent' => $digestsSent
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed notifications
     */
    public function retryFailed(Request $request)
    {
        try {
            $maxRetries = $request->get('max_retries', 3);
            $limit = $request->get('limit', 100);

            $failedNotifications = DB::table('notification_queue')
                ->where('status', 'failed')
                ->where('attempts', '<', $maxRetries)
                ->where('created_at', '>', \Carbon\Carbon::now()->subDays(7)) // Only retry recent failures
                ->limit($limit)
                ->get();

            $retried = 0;

            foreach ($failedNotifications as $notification) {
                DB::table('notification_queue')
                    ->where('id', $notification->id)
                    ->update([
                        'status' => 'pending',
                        'error_message' => null,
                        'scheduled_at' => \Carbon\Carbon::now()->addMinutes(5), // Retry in 5 minutes
                        'updated_at' => \Carbon\Carbon::now()
                    ]);
                $retried++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Failed notifications queued for retry',
                'retried_count' => $retried
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get queue statistics
     */
    public function queueStats()
    {
        try {
            $stats = [
                'queue_counts' => [
                    'pending' => DB::table('notification_queue')->where('status', 'pending')->count(),
                    'processing' => DB::table('notification_queue')->where('status', 'processing')->count(),
                    'sent' => DB::table('notification_queue')->where('status', 'sent')->whereDate('sent_at', \Carbon\Carbon::today())->count(),
                    'failed' => DB::table('notification_queue')->where('status', 'failed')->whereDate('updated_at', \Carbon\Carbon::today())->count()
                ],
                'type_breakdown' => DB::table('notification_queue')
                    ->select('type', DB::raw('COUNT(*) as count'))
                    ->where('created_at', '>', \Carbon\Carbon::now()->subDay())
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'hourly_volume' => DB::table('notification_queue')
                    ->select(DB::raw('EXTRACT(HOUR FROM created_at) as hour'), DB::raw('COUNT(*) as count'))
                    ->whereDate('created_at', \Carbon\Carbon::today())
                    ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
                    ->pluck('count', 'hour')
                    ->toArray(),
                'redis_queues' => $this->getRedisQueueStats()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process notification by type
     */
    private function processNotificationByType($notification)
    {
        try {
            switch ($notification->type) {
                case 'email':
                    return $this->sendEmailNotification($notification);
                case 'sms':
                    return $this->sendSMSNotification($notification);
                case 'push':
                    return $this->sendPushNotification($notification);
                case 'slack':
                    return $this->sendSlackNotification($notification);
                case 'webhook':
                    return $this->sendWebhookNotification($notification);
                default:
                    throw new \Exception("Unsupported notification type: {$notification->type}");
            }
        } catch (\Exception $e) {
            error_log("Failed to process {$notification->type} notification {$notification->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification($notification)
    {
        // Simulate email sending (replace with actual email service integration)
        return true;
    }

    /**
     * Send SMS notification
     */
    private function sendSMSNotification($notification)
    {
        // Simulate SMS sending (replace with actual SMS service integration)
        return true;
    }

    /**
     * Send push notification
     */
    private function sendPushNotification($notification)
    {
        // Simulate push notification (replace with actual push service integration)
        return true;
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification($notification)
    {
        // Simulate Slack notification (replace with actual Slack integration)
        return true;
    }

    /**
     * Send webhook notification
     */
    private function sendWebhookNotification($notification)
    {
        // Simulate webhook sending (replace with actual HTTP client)
        return true;
    }

    /**
     * Handle failed notification
     */
    private function handleFailedNotification($notification)
    {
        $maxRetries = 3;
        $currentAttempts = $notification->attempts ?? 0;

        if ($currentAttempts < $maxRetries) {
            // Schedule for retry with exponential backoff
            $retryDelay = pow(2, $currentAttempts) * 5; // 5, 10, 20 minutes

            DB::table('notification_queue')
                ->where('id', $notification->id)
                ->update([
                    'status' => 'pending',
                    'scheduled_at' => \Carbon\Carbon::now()->addMinutes($retryDelay),
                    'updated_at' => \Carbon\Carbon::now()
                ]);
        } else {
            DB::table('notification_queue')
                ->where('id', $notification->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Max retry attempts exceeded',
                    'updated_at' => \Carbon\Carbon::now()
                ]);
        }
    }

    /**
     * Generate digest content for user
     */
    private function generateDigestContent($userId, $frequency)
    {
        $timeRange = match($frequency) {
            'hourly' => \Carbon\Carbon::now()->subHour(),
            'daily' => \Carbon\Carbon::now()->subDay(),
            'weekly' => \Carbon\Carbon::now()->subWeek(),
            default => \Carbon\Carbon::now()->subDay()
        };

        return DB::table('notifications')
            ->where('user_id', $userId)
            ->where('created_at', '>', $timeRange)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Format digest body
     */
    private function formatDigestBody($digestContent)
    {
        $body = "Here's your notification digest:\n\n";

        foreach ($digestContent as $notification) {
            $body .= "• {$notification->subject}\n";
            $body .= "  {$notification->body}\n\n";
        }

        return $body;
    }

    /**
     * Get Redis queue statistics
     */
    private function getRedisQueueStats()
    {
        try {
            return [
                'notification_queue' => Redis::llen('queues:notification_queue'),
                'email_queue' => Redis::llen('queues:email_queue'),
                'sms_queue' => Redis::llen('queues:sms_queue'),
                'push_queue' => Redis::llen('queues:push_queue')
            ];
        } catch (\Exception $e) {
            return ['error' => 'Unable to connect to Redis'];
        }
    }
}