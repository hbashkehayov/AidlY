<?php

namespace App\Services;

use Pusher\Pusher;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    private Pusher $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            [
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                'useTLS' => true,
                'host' => env('PUSHER_HOST', null),
                'port' => env('PUSHER_PORT', null),
                'scheme' => env('PUSHER_SCHEME', 'https')
            ]
        );
    }

    /**
     * Send notification through WebSocket
     */
    public function sendNotification(Notification $notification): bool
    {
        try {
            $channel = $this->getChannelName($notification);
            $event = $this->getEventName($notification->type);
            $data = $this->formatNotificationData($notification);

            $result = $this->pusher->trigger($channel, $event, $data);

            if ($result === true) {
                Log::info('WebSocket notification sent', [
                    'notification_id' => $notification->id,
                    'channel' => $channel,
                    'event' => $event
                ]);
                return true;
            }

            Log::error('Failed to send WebSocket notification', [
                'notification_id' => $notification->id,
                'result' => $result
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('WebSocket notification error', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function broadcastToUsers(array $userIds, string $event, array $data): bool
    {
        try {
            $channels = array_map(fn($id) => "private-user-{$id}", $userIds);

            $result = $this->pusher->trigger($channels, $event, $data);

            if ($result === true) {
                Log::info('Broadcast sent to users', [
                    'user_ids' => $userIds,
                    'event' => $event
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Broadcast error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send notification to a department/team
     */
    public function broadcastToDepartment(string $departmentId, string $event, array $data): bool
    {
        try {
            $channel = "private-department-{$departmentId}";

            $result = $this->pusher->trigger($channel, $event, $data);

            return $result === true;
        } catch (\Exception $e) {
            Log::error('Department broadcast error', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send global notification (public channel)
     */
    public function broadcastGlobal(string $event, array $data): bool
    {
        try {
            $result = $this->pusher->trigger('global', $event, $data);
            return $result === true;
        } catch (\Exception $e) {
            Log::error('Global broadcast error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Authenticate user for private channel
     */
    public function authenticateChannel(string $channelName, string $socketId, $user): array
    {
        // Validate user can access channel
        if (!$this->canAccessChannel($channelName, $user)) {
            throw new \Exception('Unauthorized channel access');
        }

        return $this->pusher->authorizeChannel($channelName, $socketId);
    }

    /**
     * Authenticate user for presence channel
     */
    public function authenticatePresenceChannel(string $channelName, string $socketId, $user): array
    {
        // Validate user can access channel
        if (!$this->canAccessChannel($channelName, $user)) {
            throw new \Exception('Unauthorized channel access');
        }

        $userData = [
            'user_id' => $user->id,
            'user_info' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url
            ]
        ];

        return $this->pusher->authorizePresenceChannel($channelName, $socketId, $user->id, $userData);
    }

    /**
     * Get channel statistics
     */
    public function getChannelInfo(string $channel): ?array
    {
        try {
            $result = $this->pusher->getChannelInfo($channel);
            return $result ? json_decode($result, true) : null;
        } catch (\Exception $e) {
            Log::error('Failed to get channel info', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get presence channel users
     */
    public function getPresenceUsers(string $channel): ?array
    {
        try {
            $result = $this->pusher->getPresenceUsers($channel);
            return $result ? json_decode($result, true) : null;
        } catch (\Exception $e) {
            Log::error('Failed to get presence users', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Trigger batch events
     */
    public function triggerBatch(array $batch): bool
    {
        try {
            $result = $this->pusher->triggerBatch($batch);
            return $result === true;
        } catch (\Exception $e) {
            Log::error('Batch trigger error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get channel name for notification
     */
    private function getChannelName(Notification $notification): string
    {
        if ($notification->notifiable_type === 'user') {
            return "private-user-{$notification->notifiable_id}";
        } elseif ($notification->notifiable_type === 'client') {
            return "private-client-{$notification->notifiable_id}";
        }

        return 'global';
    }

    /**
     * Get event name from notification type
     */
    private function getEventName(string $type): string
    {
        return str_replace('_', '-', $type);
    }

    /**
     * Format notification data for WebSocket
     */
    private function formatNotificationData(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'action_url' => $notification->action_url,
            'action_text' => $notification->action_text,
            'priority' => $notification->priority,
            'data' => $notification->data,
            'timestamp' => $notification->created_at->toIso8601String()
        ];
    }

    /**
     * Check if user can access channel
     */
    private function canAccessChannel(string $channelName, $user): bool
    {
        // Check private user channel
        if (preg_match('/^private-user-(.+)$/', $channelName, $matches)) {
            return $user->id === $matches[1];
        }

        // Check private department channel
        if (preg_match('/^private-department-(.+)$/', $channelName, $matches)) {
            return $user->department_id === $matches[1];
        }

        // Check presence channel for online agents
        if ($channelName === 'presence-online-agents') {
            return $user->role === 'agent' || $user->role === 'admin';
        }

        // Global channel is accessible to all authenticated users
        if ($channelName === 'global') {
            return true;
        }

        return false;
    }
}