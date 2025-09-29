<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function currentStats(Request $request)
    {
        return response()->json([
            'success' => true,
            'stats' => [
                'active_users' => 15,
                'open_tickets' => 42
            ]
        ]);
    }

    public function activeAgents(Request $request)
    {
        return response()->json([
            'success' => true,
            'agents' => []
        ]);
    }

    public function queueStatus(Request $request)
    {
        return response()->json([
            'success' => true,
            'queue' => [
                'total_unassigned' => 12,
                'urgent' => 3
            ]
        ]);
    }
}
