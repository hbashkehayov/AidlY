<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NotificationManager
{
    private WebSocketService $webSocketService;
    private EmailNotificationService $emailService;

    public function __construct(
        WebSocketService $webSocketService,
        EmailNotificationService $emailService
    ) {
        $this->webSocketService = $webSocketService;
        $this->emailService = $emailService;
    }

    /**
     * Create and send notification
     */
    public function notify(array $data): ?Notification
    {
        DB::beginTransaction();

        try {
            // Create notification record
            $notification = Notification::create([
                'type' => $data['type'],
                'channel' => $data['channel'] ?? 'in_app',
                'notifiable_id' => $data['notifiable_id'],
                'notifiable_type' => $data['notifiable_type'],
                'ticket_id' => $data['ticket_id'] ?? null,
                'comment_id' => $data['comment_id'] ?? null,
                'triggered_by' => $data['triggered_by'] ?? null,
                'title' => $data['title'],
                'message' => $data['message'],
                'data' => $data['data'] ?? [],
                'action_url' => $data['action_url'] ?? null,
                'action_text' => $data['action_text'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'group_key' => $data['group_key'] ?? null
            ]);

            // Get user preferences
            $preferences = $this->getUserPreferences($notification->notifiable_id, $notification->notifiable_type);

            // Send notification through appropriate channels
            $sent = false;

            // Check if channel is enabled for this event type
            if ($preferences && $preferences->isEventChannelEnabled($notification->type, $notification->channel)) {
                $sent = $this->sendThroughChannel($notification, $notification->channel);
            }

            if ($sent) {
                $notification->markAsSent();
            }

            DB::commit();

            return $notification;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create notification', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send notification to multiple recipients
     */
    public function notifyMultiple(array $recipientIds, string $recipientType, array $data): array
    {
        $notifications = [];

        foreach ($recipientIds as $recipientId) {
            $data['notifiable_id'] = $recipientId;
            $data['notifiable_type'] = $recipientType;

            $notification = $this->notify($data);

            if ($notification) {
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    /**
     * Send notification through all enabled channels
     */
    public function notifyAllChannels(array $data): array
    {
        $notifications = [];
        $channels = ['email', 'in_app', 'push'];

        // Get user preferences
        $preferences = $this->getUserPreferences($data['notifiable_id'], $data['notifiable_type']);

        foreach ($channels as $channel) {
            // Check if channel is enabled for this event type
            if ($preferences && $preferences->isEventChannelEnabled($data['type'], $channel)) {
                $data['channel'] = $channel;
                $notification = $this->notify($data);

                if ($notification) {
                    $notifications[] = $notification;
                }
            }
        }

        return $notifications;
    }

    /**
     * Send notification through specific channel
     */
    private function sendThroughChannel(Notification $notification, string $channel): bool
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmailNotification($notification);

            case 'in_app':
                return $this->sendInAppNotification($notification);

            case 'push':
                return $this->sendPushNotification($notification);

            case 'sms':
                return $this->sendSMSNotification($notification);

            default:
                Log::warning('Unknown notification channel', [
                    'channel' => $channel,
                    'notification_id' => $notification->id
                ]);
                return false;
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(Notification $notification): bool
    {
        // Check if should be queued for digest
        $preferences = $this->getUserPreferences($notification->notifiable_id, $notification->notifiable_type);

        if ($preferences && $preferences->digest_enabled && $preferences->email_frequency !== 'immediate') {
            // Queue for digest
            $notification->update(['status' => 'queued']);
            return true;
        }

        return $this->emailService->sendEmailNotification($notification);
    }

    /**
     * Send in-app notification (via WebSocket)
     */
    private function sendInAppNotification(Notification $notification): bool
    {
        $result = $this->webSocketService->sendNotification($notification);

        if ($result) {
            $notification->markAsDelivered();
        }

        return $result;
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(Notification $notification): bool
    {
        // TODO: Implement push notification service (FCM, APNS, etc.)
        Log::info('Push notification would be sent here', [
            'notification_id' => $notification->id
        ]);
        return true;
    }

    /**
     * Send SMS notification
     */
    private function sendSMSNotification(Notification $notification): bool
    {
        // TODO: Implement SMS service (Twilio, etc.)
        Log::info('SMS notification would be sent here', [
            'notification_id' => $notification->id
        ]);
        return false;
    }

    /**
     * Get user notification preferences
     */
    private function getUserPreferences($notifiableId, $notifiableType): ?NotificationPreference
    {
        if ($notifiableType === 'user') {
            return NotificationPreference::where('user_id', $notifiableId)->first();
        } elseif ($notifiableType === 'client') {
            return NotificationPreference::where('client_id', $notifiableId)->first();
        }

        return null;
    }

    /**
     * Process notification queue (for batch sending)
     */
    public function processQueue(): void
    {
        // Process pending notifications
        $pendingNotifications = Notification::pending()
            ->where('created_at', '<=', now()->subMinutes(1))
            ->limit(100)
            ->get();

        foreach ($pendingNotifications as $notification) {
            $sent = $this->sendThroughChannel($notification, $notification->channel);

            if ($sent) {
                $notification->markAsSent();
            } else {
                $notification->markAsFailed();
            }
        }

        // Process failed notifications for retry
        $retryableNotifications = Notification::retryable(3)
            ->where('failed_at', '<=', now()->subMinutes(5))
            ->limit(50)
            ->get();

        foreach ($retryableNotifications as $notification) {
            $sent = $this->sendThroughChannel($notification, $notification->channel);

            if ($sent) {
                $notification->markAsSent();
            } else {
                $notification->markAsFailed('Max retries exceeded');
            }
        }
    }

    /**
     * Send digest emails
     */
    public function sendDigests(): void
    {
        // Get users with digest enabled
        $preferences = NotificationPreference::where('digest_enabled', true)
            ->get();

        foreach ($preferences as $preference) {
            if (!$preference->shouldSendDigestNow()) {
                continue;
            }

            // Get queued notifications for this user
            $notifications = Notification::where('status', 'queued')
                ->where('channel', 'email')
                ->where(function ($query) use ($preference) {
                    if ($preference->user_id) {
                        $query->where('notifiable_id', $preference->user_id)
                              ->where('notifiable_type', 'user');
                    } elseif ($preference->client_id) {
                        $query->where('notifiable_id', $preference->client_id)
                              ->where('notifiable_type', 'client');
                    }
                })
                ->get();

            if ($notifications->isNotEmpty()) {
                // Get recipient email (would need to be implemented based on your models)
                $recipientEmail = $this->getRecipientEmail($preference);

                if ($recipientEmail) {
                    $this->emailService->sendDigestEmail($notifications->toArray(), $recipientEmail);
                }
            }
        }
    }

    /**
     * Get recipient email from preference
     */
    private function getRecipientEmail(NotificationPreference $preference): ?string
    {
        // This would need to be implemented based on your user/client model structure
        // For now, returning null
        return null;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId): bool
    {
        $notification = Notification::find($notificationId);

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(array $notificationIds): int
    {
        return Notification::whereIn('id', $notificationIds)
            ->whereNull('read_at')
            ->update([
                'status' => 'read',
                'read_at' => now()
            ]);
    }

    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications($notifiableId, $notifiableType, $limit = 20)
    {
        return Notification::where('notifiable_id', $notifiableId)
            ->where('notifiable_type', $notifiableType)
            ->unread()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($n) => $n->toApiResponse());
    }

    /**
     * Get notification statistics for user
     */
    public function getNotificationStats($notifiableId, $notifiableType): array
    {
        $query = Notification::where('notifiable_id', $notifiableId)
            ->where('notifiable_type', $notifiableType);

        return [
            'total' => $query->count(),
            'unread' => $query->unread()->count(),
            'high_priority' => $query->whereIn('priority', ['high', 'urgent'])->unread()->count(),
            'by_type' => $query->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type')
        ];
    }
}