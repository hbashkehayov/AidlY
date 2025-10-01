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
                'notifiable_id' => 'sometimes|uuid',
                'notifiable_type' => 'sometimes|in:user,client',
                'status' => 'sometimes|in:pending,sent,delivered,read,failed',
                'type' => 'sometimes|string',
                'channel' => 'sometimes|string',
                'limit' => 'sometimes|integer|min:1|max:100',
                'offset' => 'sometimes|integer|min:0',
                'unread_only' => 'sometimes|in:0,1,true,false',
                'view_all' => 'sometimes|in:0,1,true,false', // For admin to view all
                'user_role' => 'sometimes|string' // User role for authorization
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $notifiableId = $request->get('notifiable_id');
            $notifiableType = $request->get('notifiable_type', 'user');
            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);
            $viewAll = in_array($request->get('view_all'), [1, '1', 'true', true], true);
            $userRole = $request->get('user_role');

            // Log for debugging
            \Log::info('Notification request', [
                'view_all' => $viewAll,
                'user_role' => $userRole,
                'notifiable_id' => $notifiableId
            ]);

            // Check if view_all is requested without proper authorization
            if ($viewAll && !in_array($userRole, ['admin', 'supervisor'], true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized: Only admin/supervisor can view all notifications',
                    'debug' => [
                        'user_role' => $userRole,
                        'view_all' => $viewAll
                    ]
                ], 403);
            }

            // Admin/Supervisor can view all notifications
            if ($viewAll && in_array($userRole, ['admin', 'supervisor'], true)) {
                $query = DB::table('notifications')
                    ->leftJoin('users', 'notifications.notifiable_id', '=', 'users.id')
                    ->select('notifications.*', 'users.name as user_name', 'users.email as user_email')
                    ->where('notifiable_type', $notifiableType);
            } else {
                // Regular users (agents) see only their notifications
                if (!$notifiableId) {
                    return response()->json([
                        'success' => false,
                        'error' => 'notifiable_id is required for non-admin users',
                        'debug' => [
                            'user_role' => $userRole,
                            'has_notifiable_id' => $notifiableId !== null
                        ]
                    ], 400);
                }

                $query = DB::table('notifications')
                    ->leftJoin('users', 'notifications.notifiable_id', '=', 'users.id')
                    ->select('notifications.*', 'users.name as user_name', 'users.email as user_email')
                    ->where('notifications.notifiable_id', $notifiableId)
                    ->where('notifications.notifiable_type', $notifiableType);
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('notifications.status', $request->get('status'));
            }

            if ($request->has('type')) {
                $query->where('notifications.type', $request->get('type'));
            }

            if ($request->has('channel')) {
                $query->where('notifications.channel', $request->get('channel'));
            }

            if ($request->has('unread_only') && in_array($request->get('unread_only'), [1, '1', 'true', true], true)) {
                $query->whereNull('notifications.read_at');
            }

            $notifications = $query
                ->orderBy('notifications.created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Count totals based on same query criteria
            if ($viewAll && in_array($userRole, ['admin', 'supervisor'], true)) {
                $total = DB::table('notifications')
                    ->where('notifiable_type', $notifiableType)
                    ->count();
                $unread = DB::table('notifications')
                    ->where('notifiable_type', $notifiableType)
                    ->whereNull('read_at')
                    ->count();
            } else {
                $total = DB::table('notifications')
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->count();
                $unread = DB::table('notifications')
                    ->where('notifiable_id', $notifiableId)
                    ->where('notifiable_type', $notifiableType)
                    ->whereNull('read_at')
                    ->count();
            }

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'meta' => [
                    'total' => $total,
                    'unread' => $unread,
                    'limit' => $limit,
                    'offset' => $offset,
                    'view_mode' => $viewAll ? 'all' : 'personal'
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
                ->where('notifiable_id', $userId)
                ->where('notifiable_type', 'user')
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
            $viewAll = in_array($request->get('view_all'), [1, '1', 'true', true], true);
            $userRole = $request->get('user_role');

            // Admin/Supervisor can view all stats
            if ($viewAll && in_array($userRole, ['admin', 'supervisor'], true)) {
                $stats = [
                    'total' => DB::table('notifications')
                        ->where('notifiable_type', $notifiableType)
                        ->count(),
                    'unread' => DB::table('notifications')
                        ->where('notifiable_type', $notifiableType)
                        ->whereNull('read_at')
                        ->count(),
                    'read' => DB::table('notifications')
                        ->where('notifiable_type', $notifiableType)
                        ->whereNotNull('read_at')
                        ->count(),
                    'by_type' => DB::table('notifications')
                        ->select('type', DB::raw('COUNT(*) as count'))
                        ->where('notifiable_type', $notifiableType)
                        ->groupBy('type')
                        ->pluck('count', 'type'),
                    'recent_activity' => DB::table('notifications')
                        ->where('notifiable_type', $notifiableType)
                        ->where('created_at', '>', \Carbon\Carbon::now()->subDays(7))
                        ->count(),
                    'view_mode' => 'all'
                ];
            } else {
                // Regular users (agents) see only their stats
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
                        ->count(),
                    'view_mode' => 'personal'
                ];
            }

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
                'notification_ids' => 'sometimes|array|max:100',
                'notification_ids.*' => 'uuid'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = $request->get('user_id');
            $notificationIds = $request->get('notification_ids', []);

            $query = DB::table('notifications')
                ->where('notifiable_id', $userId)
                ->where('notifiable_type', 'user')
                ->whereNull('read_at');

            // If specific IDs provided, only mark those
            // Otherwise mark ALL unread for this user
            if (!empty($notificationIds)) {
                $query->whereIn('id', $notificationIds);
            }

            $updated = $query->update(['read_at' => \Carbon\Carbon::now()]);

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
     * Mark notification as unread
     */
    public function markAsUnread(Request $request, $id): JsonResponse
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
                ->whereNotNull('read_at')
                ->update(['read_at' => null]);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notification not found or already unread'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as unread'
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
                ->where('notifiable_id', $userId)
                ->where('notifiable_type', 'user')
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