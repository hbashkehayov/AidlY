<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PreferenceController extends Controller
{
    /**
     * Get user notification preferences
     */
    public function show(Request $request)
    {
        try {
            $userId = $request->get('user_id') ?? $request->header('X-User-Id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $preferences = DB::table('notification_preferences')
                ->where('user_id', $userId)
                ->first();

            if (!$preferences) {
                // Create default preferences
                $defaultPreferences = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $userId,
                    'email_enabled' => true,
                    'sms_enabled' => false,
                    'push_enabled' => true,
                    'events' => json_encode([
                        'ticket_created' => true,
                        'ticket_assigned' => true,
                        'ticket_updated' => true,
                        'comment_added' => true,
                        'sla_breach' => true,
                        'ticket_resolved' => true
                    ]),
                    'quiet_hours_enabled' => false,
                    'quiet_hours_start' => '22:00',
                    'quiet_hours_end' => '08:00',
                    'digest_enabled' => false,
                    'email_frequency' => 'daily',
                    'digest_time' => '09:00',
                    'dnd_enabled' => false,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now()
                ];

                DB::table('notification_preferences')->insert($defaultPreferences);
                $preferences = (object) $defaultPreferences;
            }

            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user notification preferences
     */
    public function update(Request $request)
    {
        try {
            $userId = $request->get('user_id') ?? $request->header('X-User-Id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'email_enabled' => 'sometimes|boolean',
                'sms_enabled' => 'sometimes|boolean',
                'push_enabled' => 'sometimes|boolean',
                'events' => 'sometimes|array',
                'quiet_hours_enabled' => 'sometimes|boolean',
                'quiet_hours_start' => 'sometimes|date_format:H:i',
                'quiet_hours_end' => 'sometimes|date_format:H:i',
                'digest_enabled' => 'sometimes|boolean',
                'email_frequency' => 'sometimes|in:immediate,hourly,daily,weekly',
                'digest_time' => 'sometimes|date_format:H:i',
                'dnd_enabled' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = array_filter([
                'email_enabled' => $request->get('email_enabled'),
                'sms_enabled' => $request->get('sms_enabled'),
                'push_enabled' => $request->get('push_enabled'),
                'events' => $request->has('events') ? json_encode($request->events) : null,
                'quiet_hours_enabled' => $request->get('quiet_hours_enabled'),
                'quiet_hours_start' => $request->get('quiet_hours_start'),
                'quiet_hours_end' => $request->get('quiet_hours_end'),
                'digest_enabled' => $request->get('digest_enabled'),
                'email_frequency' => $request->get('email_frequency'),
                'digest_time' => $request->get('digest_time'),
                'dnd_enabled' => $request->get('dnd_enabled'),
                'updated_at' => \Carbon\Carbon::now()
            ], function($value) { return $value !== null; });

            // Add ID for new records
            if (!DB::table('notification_preferences')->where('user_id', $userId)->exists()) {
                $updateData['id'] = \Illuminate\Support\Str::uuid();
            }

            DB::table('notification_preferences')
                ->updateOrInsert(['user_id' => $userId], $updateData);

            $preferences = DB::table('notification_preferences')
                ->where('user_id', $userId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => $preferences
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update preference for a specific event
     */
    public function updateEventPreference(Request $request, $event)
    {
        try {
            $userId = $request->get('user_id') ?? $request->header('X-User-Id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $preferences = DB::table('notification_preferences')
                ->where('user_id', $userId)
                ->first();

            $events = $preferences ? json_decode($preferences->events, true) : [];
            $events[$event] = $request->get('enabled');

            $updateData = [
                'events' => json_encode($events),
                'updated_at' => \Carbon\Carbon::now()
            ];

            // Add ID for new records
            if (!$preferences) {
                $updateData['id'] = \Illuminate\Support\Str::uuid();
            }

            DB::table('notification_preferences')
                ->updateOrInsert(['user_id' => $userId], $updateData);

            return response()->json([
                'success' => true,
                'message' => "Event preference for '$event' updated successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle Do Not Disturb mode
     */
    public function toggleDND(Request $request)
    {
        try {
            $userId = $request->get('user_id') ?? $request->header('X-User-Id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $currentPrefs = DB::table('notification_preferences')
                ->where('user_id', $userId)
                ->first();

            $newDNDStatus = !($currentPrefs->dnd_enabled ?? false);

            $updateData = [
                'dnd_enabled' => $newDNDStatus,
                'updated_at' => \Carbon\Carbon::now()
            ];

            // Add ID for new records
            if (!$currentPrefs) {
                $updateData['id'] = \Illuminate\Support\Str::uuid();
            }

            DB::table('notification_preferences')
                ->updateOrInsert(['user_id' => $userId], $updateData);

            return response()->json([
                'success' => true,
                'message' => 'Do Not Disturb ' . ($newDNDStatus ? 'enabled' : 'disabled'),
                'dnd_enabled' => $newDNDStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update digest settings
     */
    public function updateDigestSettings(Request $request)
    {
        try {
            $userId = $request->get('user_id') ?? $request->header('X-User-Id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'frequency' => 'required|in:hourly,daily,weekly',
                'time' => 'required|date_format:H:i'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::table('notification_preferences')
                ->updateOrInsert(
                    ['user_id' => $userId],
                    [
                        'digest_enabled' => $request->get('enabled'),
                        'email_frequency' => $request->get('frequency'),
                        'digest_time' => $request->get('time'),
                        'updated_at' => \Carbon\Carbon::now()
                    ]
                );

            return response()->json([
                'success' => true,
                'message' => 'Digest settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update quiet hours settings
     */
    public function updateQuietHours(Request $request)
    {
        try {
            $userId = $request->get('user_id') ?? $request->header('X-User-Id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'start' => 'required|date_format:H:i',
                'end' => 'required|date_format:H:i'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::table('notification_preferences')
                ->updateOrInsert(
                    ['user_id' => $userId],
                    [
                        'quiet_hours_enabled' => $request->get('enabled'),
                        'quiet_hours_start' => $request->get('start'),
                        'quiet_hours_end' => $request->get('end'),
                        'updated_at' => \Carbon\Carbon::now()
                    ]
                );

            return response()->json([
                'success' => true,
                'message' => 'Quiet hours settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}