<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\ClientMerge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Get all clients with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Client::active();

        // Apply filters
        if ($request->has('search')) {
            $query->search($request->get('search'));
        }

        if ($request->has('company')) {
            $query->byCompany($request->get('company'));
        }

        if ($request->has('email')) {
            $query->byEmail($request->get('email'));
        }

        if ($request->has('name')) {
            $query->byName($request->get('name'));
        }

        if ($request->has('is_vip')) {
            if ($request->boolean('is_vip')) {
                $query->vip();
            }
        }

        if ($request->has('is_blocked')) {
            if ($request->boolean('is_blocked')) {
                $query->blocked();
            }
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->get('tags'));
            $query->whereJsonContains('tags', $tags);
        }

        // Apply sorting
        $sortBy = $request->get('sort', '-created_at');
        $sortDirection = str_starts_with($sortBy, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sortBy, '-');

        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = min($request->get('limit', 20), 100);
        $clients = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $clients->items(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'last_page' => $clients->lastPage(),
                'from' => $clients->firstItem(),
                'to' => $clients->lastItem(),
            ]
        ]);
    }

    /**
     * Get a single client by ID
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $query = Client::active()->where('id', $id);

        // Include relationships if requested
        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));

            foreach ($includes as $include) {
                switch (trim($include)) {
                    case 'notes':
                        $query->with('notes');
                        break;
                    case 'merges':
                        $query->with('merges');
                        break;
                }
            }
        }

        $client = $query->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $client
        ]);
    }

    /**
     * Create a new client
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'email' => 'required|email|unique:clients,email',
            'name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'crm_id' => 'nullable|string|max:255',
            'crm_type' => 'nullable|string|max:50',
            'lead_score' => 'nullable|integer|min:0|max:100',
            'lifetime_value' => 'nullable|numeric|min:0',
            'notification_preferences' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_vip' => 'nullable|boolean'
        ]);

        $client = Client::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => 'Client created successfully'
        ], 201);
    }

    /**
     * Update an existing client
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $client = Client::active()->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('clients')->ignore($client->id)
            ],
            'name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'crm_id' => 'nullable|string|max:255',
            'crm_type' => 'nullable|string|max:50',
            'lead_score' => 'nullable|integer|min:0|max:100',
            'lifetime_value' => 'nullable|numeric|min:0',
            'notification_preferences' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_vip' => 'nullable|boolean'
        ]);

        $client->fill($request->all());
        $client->save();

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => 'Client updated successfully'
        ]);
    }

    /**
     * Soft delete a client
     */
    public function destroy(string $id): JsonResponse
    {
        $client = Client::active()->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        $client->softDelete();

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully'
        ]);
    }

    /**
     * Get client tickets (from ticket service)
     */
    public function tickets(string $id, Request $request): JsonResponse
    {
        $client = Client::active()->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        // TODO: Make API call to ticket service to get tickets for this client
        // For now, return placeholder response
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'Ticket service integration pending'
        ]);
    }

    /**
     * Block/unblock a client
     */
    public function toggleBlock(Request $request, string $id): JsonResponse
    {
        $client = Client::active()->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'reason' => 'nullable|string|max:500'
        ]);

        if ($client->is_blocked) {
            $client->unblock();
            $message = 'Client unblocked successfully';
        } else {
            $client->block($request->get('reason'));
            $message = 'Client blocked successfully';
        }

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => $message
        ]);
    }

    /**
     * Set/remove VIP status
     */
    public function toggleVip(Request $request, string $id): JsonResponse
    {
        $client = Client::active()->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'reason' => 'nullable|string|max:500'
        ]);

        if ($client->is_vip) {
            $client->removeVip();
            $message = 'VIP status removed successfully';
        } else {
            $client->setAsVip($request->get('reason'));
            $message = 'VIP status set successfully';
        }

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => $message
        ]);
    }

    /**
     * Add tag to client
     */
    public function addTag(Request $request, string $id): JsonResponse
    {
        $client = Client::active()->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'tag' => 'required|string|max:50'
        ]);

        $client->addTag($request->get('tag'));

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => 'Tag added successfully'
        ]);
    }

    /**
     * Remove tag from client
     */
    public function removeTag(Request $request, string $id): JsonResponse
    {
        $client = Client::active()->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'tag' => 'required|string|max:50'
        ]);

        $client->removeTag($request->get('tag'));

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => 'Tag removed successfully'
        ]);
    }
}