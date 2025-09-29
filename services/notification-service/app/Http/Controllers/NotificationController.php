<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get notifications for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notifiable_id' => 'required|uuid',
                'notifiable_type' => 'required|in:user,client',
                'status' => 'sometimes|in:pending,sent,delivered,read,failed',
                'type' => 'sometimes|string',
                'channel' => 'sometimes|string',
                'limit' => 'sometimes|integer|min:1|max:100',
                'offset' => 'sometimes|integer|min:0',
                'unread_only' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $notifiableId = $request->get('notifiable_id');
            $notifiableType = $request->get('notifiable_type');
            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);

            $query = DB::table('notifications')
                ->where('notifiable_id', $notifiableId)
                ->where('notifiable_type', $notifiableType);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('type')) {
                $query->where('type', $request->get('type'));
            }

            if ($request->has('channel')) {
                $query->where('channel', $request->get('channel'));
            }

            if ($request->get('unread_only')) {
                $query->where('read_at', null);
            }

            $notifications = $query
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            $total = DB::table('notifications')
                ->where('notifiable_id', $notifiableId)
                ->where('notifiable_type', $notifiableType)
                ->count();
            $unread = DB::table('notifications')
                ->where('notifiable_id', $notifiableId)
                ->where('notifiable_type', $notifiableType)
                ->whereNull('read_at')
                ->count();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'meta' => [
                    'total' => $total,
                    'unread' => $unread,
                    'limit' => $limit,
                    'offset' => $offset
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
     * Get unread notifications
     */
    public function unread(Request $request): JsonResponse
    {
        try {
            $userId = $request->get('user_id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $notifications = DB::table('notifications')
                ->where('user_id', $userId)
                ->whereNull('read_at')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'count' => count($notifications)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $notifiableId = $request->get('notifiable_id');
            $notifiableType = $request->get('notifiable_type', 'user');

            if (!$notifiableId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notifiable ID required'
                ], 400);
            }

            $stats = [
                'total' => DB::table('notifications')
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->count(),
                'unread' => DB::table('notifications')
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->whereNull('read_at')
                    ->count(),
                'read' => DB::table('notifications')
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->whereNotNull('read_at')
                    ->count(),
                'by_type' => DB::table('notifications')
                    ->select('type', DB::raw('COUNT(*) as count'))
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'recent_activity' => DB::table('notifications')
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->where('created_at', '>', \Carbon\Carbon::now()->subDays(7))
                    ->count()
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
     * Create and queue a new notification
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notifiable_id' => 'required|uuid',
                'notifiable_type' => 'required|in:user,client',
                'type' => 'required|string',
                'channel' => 'required|string',
                'title' => 'required|string|max:500',
                'message' => 'required|string',
                'data' => 'sometimes|array',
                'priority' => 'sometimes|in:low,normal,high,urgent',
                'ticket_id' => 'sometimes|uuid',
                'comment_id' => 'sometimes|uuid',
                'triggered_by' => 'sometimes|uuid',
                'action_url' => 'sometimes|string',
                'action_text' => 'sometimes|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $notificationId = \Illuminate\Support\Str::uuid();

            // Store in notifications table
            DB::table('notifications')->insert([
                'id' => $notificationId,
                'notifiable_id' => $request->get('notifiable_id'),
                'notifiable_type' => $request->get('notifiable_type'),
                'type' => $request->get('type'),
                'channel' => $request->get('channel'),
                'title' => $request->get('title'),
                'message' => $request->get('message'),
                'data' => json_encode($request->get('data', [])),
                'ticket_id' => $request->get('ticket_id'),
                'comment_id' => $request->get('comment_id'),
                'triggered_by' => $request->get('triggered_by'),
                'action_url' => $request->get('action_url'),
                'action_text' => $request->get('action_text'),
                'priority' => $request->get('priority', 'normal'),
                'status' => 'pending',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification queued successfully',
                'notification_id' => $notificationId
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notifications' => 'required|array|max:100',
                'notifications.*.notifiable_type' => 'required|in:user,client',
                'notifications.*.notifiable_id' => 'required|uuid',
                'notifications.*.type' => 'required|string|max:100',
                'notifications.*.channel' => 'required|in:email,in_app,sms,push,slack,webhook',
                'notifications.*.title' => 'required|string|max:500',
                'notifications.*.message' => 'required|string',
                'notifications.*.priority' => 'sometimes|in:low,normal,high,urgent',
                'notifications.*.data' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $notifications = $request->get('notifications');
            $queued = 0;

            foreach ($notifications as $notification) {
                try {
                    $notificationId = \Illuminate\Support\Str::uuid();

                    DB::table('notification_queue')->insert([
                        'id' => $notificationId,
                        'notifiable_type' => $notification['notifiable_type'],
                        'notifiable_id' => $notification['notifiable_id'],
                        'type' => $notification['type'],
                        'channel' => $notification['channel'],
                        'title' => $notification['title'],
                        'message' => $notification['message'],
                        'data' => json_encode($notification['data'] ?? []),
                        'priority' => $this->mapPriorityToInt($notification['priority'] ?? 'normal'),
                        'status' => 'pending',
                        'scheduled_at' => \Carbon\Carbon::now(),
                        'created_at' => \Carbon\Carbon::now()
                    ]);

                    $queued++;
                } catch (\Exception $e) {
                    // Log error but continue with other notifications
                    error_log("Failed to queue notification: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk notifications queued',
                'queued_count' => $queued,
                'total_count' => count($notifications)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        try {
            if (!$request->get('notifiable_id')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notifiable ID required'
                ], 400);
            }

            $updated = DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', $request->get('notifiable_id'))
                ->where('notifiable_type', $request->get('notifiable_type', 'user'))
                ->whereNull('read_at')
                ->update(['read_at' => \Carbon\Carbon::now()]);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notification not found or already read'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|uuid',
                'notification_ids' => 'required|array|max:100',
                'notification_ids.*' => 'uuid'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updated = DB::table('notifications')
                ->where('user_id', $request->get('user_id'))
                ->whereIn('id', $request->get('notification_ids'))
                ->whereNull('read_at')
                ->update(['read_at' => \Carbon\Carbon::now()]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} notifications as read",
                'updated_count' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->get('user_id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $deleted = DB::table('notifications')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notification not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Authenticate WebSocket connection for real-time notifications
     */
    public function authenticateWebSocket(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|uuid',
                'socket_id' => 'required|string',
                'channel_name' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // In a real implementation, you would validate the user's permissions
            // and generate a proper authentication signature
            $auth = [
                'auth' => hash_hmac('sha256', $request->get('socket_id') . ':' . $request->get('channel_name'), 'websocket-secret'),
                'user_data' => json_encode([
                    'id' => $request->get('user_id'),
                    'permissions' => ['read_notifications', 'receive_realtime']
                ])
            ];

            return response()->json([
                'success' => true,
                'auth' => $auth
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map priority string to integer
     */
    private function mapPriorityToInt($priority): int
    {
        return match($priority) {
            'low' => 1,
            'normal' => 5,
            'high' => 10,
            'urgent' => 20,
            default => 5
        };
    }
}