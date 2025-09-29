<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Get all roles
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $roles = [
            ['id' => 'admin', 'name' => 'Administrator', 'description' => 'Full system access'],
            ['id' => 'supervisor', 'name' => 'Supervisor', 'description' => 'Team management access'],
            ['id' => 'agent', 'name' => 'Support Agent', 'description' => 'Ticket handling access'],
            ['id' => 'customer', 'name' => 'Customer', 'description' => 'Customer portal access'],
        ];

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get permissions for a specific role
     *
     * @param string $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPermissions($role)
    {
        $permissions = [
            'admin' => [
                'users.manage',
                'tickets.manage',
                'settings.manage',
                'reports.view',
                'system.configure'
            ],
            'supervisor' => [
                'tickets.manage',
                'reports.view',
                'team.manage',
                'tickets.assign'
            ],
            'agent' => [
                'tickets.view',
                'tickets.update',
                'tickets.comment',
                'customers.view'
            ],
            'customer' => [
                'tickets.create',
                'tickets.view.own',
                'tickets.comment.own'
            ]
        ];

        if (!isset($permissions[$role])) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $permissions[$role]
        ]);
    }

    /**
     * Assign permissions to a role
     *
     * @param Request $request
     * @param string $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermissions(Request $request, $role)
    {
        // In a real implementation, this would save to database
        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully'
        ]);
    }

    /**
     * Remove permission from a role
     *
     * @param string $role
     * @param string $permissionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removePermission($role, $permissionId)
    {
        // In a real implementation, this would remove from database
        return response()->json([
            'success' => true,
            'message' => 'Permission removed successfully'
        ]);
    }
}