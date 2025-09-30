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

        // Add ticket counts for each client
        $clientsWithTickets = $this->enrichClientsWithTicketCounts($clients->items());

        // Get total ticket count across ALL customers (not just current page)
        $overallTicketCount = $this->getTotalTicketCount();

        return response()->json([
            'success' => true,
            'data' => $clientsWithTickets,
            'meta' => [
                'current_page' => $clients->currentPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'last_page' => $clients->lastPage(),
                'from' => $clients->firstItem(),
                'to' => $clients->lastItem(),
                'total_tickets_overall' => $overallTicketCount,
            ]
        ]);
    }

    /**
     * Get total ticket count across all clients
     */
    protected function getTotalTicketCount(): int
    {
        try {
            // Get ticket service URL from env
            $ticketServiceUrl = env('TICKET_SERVICE_URL', 'http://localhost:8002');

            // Fetch total ticket count from ticket service
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get("{$ticketServiceUrl}/api/v1/public/tickets/stats/total");

            if ($response->successful()) {
                $responseData = $response->json();
                if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data'])) {
                    return $responseData['data']['total'] ?? 0;
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch total ticket count', [
                'error' => $e->getMessage()
            ]);
        }

        return 0;
    }

    /**
     * Enrich clients with ticket counts from ticket service
     */
    protected function enrichClientsWithTicketCounts(array $clients): array
    {
        if (empty($clients)) {
            return $clients;
        }

        try {
            // Get ticket service URL from env
            $ticketServiceUrl = env('TICKET_SERVICE_URL', 'http://localhost:8002');

            // Fetch ticket counts for all clients
            $clientIds = array_column($clients, 'id');

            \Illuminate\Support\Facades\Log::info('Fetching ticket stats', [
                'url' => "{$ticketServiceUrl}/api/v1/public/tickets/stats/by-clients",
                'client_ids' => $clientIds
            ]);

            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get("{$ticketServiceUrl}/api/v1/public/tickets/stats/by-clients", [
                    'client_ids' => implode(',', $clientIds)
                ]);

            \Illuminate\Support\Facades\Log::info('Ticket stats response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                \Illuminate\Support\Facades\Log::info('Response data structure', [
                    'keys' => array_keys($responseData),
                    'success' => $responseData['success'] ?? 'not set',
                    'data_type' => gettype($responseData['data'] ?? null)
                ]);

                if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data'])) {
                    $ticketStats = $responseData['data'];

                    // Map ticket counts to clients
                    foreach ($clients as &$client) {
                        $stats = $ticketStats[$client['id']] ?? null;

                        if ($stats) {
                            $client['total_tickets'] = $stats['total'] ?? 0;
                            $client['new_tickets'] = $stats['new'] ?? 0;
                            $client['open_tickets'] = $stats['open'] ?? 0;
                            $client['pending_tickets'] = $stats['pending'] ?? 0;
                            $client['on_hold_tickets'] = $stats['on_hold'] ?? 0;
                            $client['resolved_tickets'] = $stats['resolved'] ?? 0;
                            $client['closed_tickets'] = $stats['closed'] ?? 0;
                            $client['cancelled_tickets'] = $stats['cancelled'] ?? 0;
                            $client['active_tickets'] = $stats['active'] ?? 0;
                            $client['inactive_tickets'] = $stats['inactive'] ?? 0;
                        } else {
                            $client['total_tickets'] = 0;
                            $client['new_tickets'] = 0;
                            $client['open_tickets'] = 0;
                            $client['pending_tickets'] = 0;
                            $client['on_hold_tickets'] = 0;
                            $client['resolved_tickets'] = 0;
                            $client['closed_tickets'] = 0;
                            $client['cancelled_tickets'] = 0;
                            $client['active_tickets'] = 0;
                            $client['inactive_tickets'] = 0;
                        }
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning('Invalid response format from ticket service');
                    // Set counts to 0
                    foreach ($clients as &$client) {
                        $client['total_tickets'] = 0;
                        $client['new_tickets'] = 0;
                        $client['open_tickets'] = 0;
                        $client['pending_tickets'] = 0;
                        $client['on_hold_tickets'] = 0;
                        $client['resolved_tickets'] = 0;
                        $client['closed_tickets'] = 0;
                        $client['cancelled_tickets'] = 0;
                        $client['active_tickets'] = 0;
                        $client['inactive_tickets'] = 0;
                    }
                }
            } else {
                \Illuminate\Support\Facades\Log::warning('Ticket service request failed', [
                    'status' => $response->status()
                ]);
                // If ticket service is unavailable, set counts to 0
                foreach ($clients as &$client) {
                    $client['total_tickets'] = 0;
                    $client['new_tickets'] = 0;
                    $client['open_tickets'] = 0;
                    $client['pending_tickets'] = 0;
                    $client['on_hold_tickets'] = 0;
                    $client['resolved_tickets'] = 0;
                    $client['closed_tickets'] = 0;
                    $client['cancelled_tickets'] = 0;
                    $client['active_tickets'] = 0;
                    $client['inactive_tickets'] = 0;
                }
            }
        } catch (\Exception $e) {
            // On error, set counts to 0
            \Illuminate\Support\Facades\Log::warning('Failed to fetch ticket counts', [
                'error' => $e->getMessage()
            ]);

            foreach ($clients as &$client) {
                $client['total_tickets'] = 0;
                $client['new_tickets'] = 0;
                $client['open_tickets'] = 0;
                $client['pending_tickets'] = 0;
                $client['on_hold_tickets'] = 0;
                $client['resolved_tickets'] = 0;
                $client['closed_tickets'] = 0;
                $client['cancelled_tickets'] = 0;
                $client['active_tickets'] = 0;
                $client['inactive_tickets'] = 0;
            }
        }

        return $clients;
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
     * Delete a client and all related data
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

        try {
            // IMPORTANT: Delete tickets FIRST before starting transaction
            // This is because tickets are in a different service/database
            // and may have foreign key constraints referencing the client
            $ticketDeletionResult = $this->deleteClientTickets($id);

            if (!$ticketDeletionResult) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TICKET_DELETE_FAILED',
                        'message' => 'Failed to delete client tickets. Client deletion aborted.'
                    ]
                ], 500);
            }

            \Illuminate\Support\Facades\DB::beginTransaction();

            // 1. Delete client notes (CASCADE will handle this, but explicit for clarity)
            ClientNote::where('client_id', $id)->delete();

            // 2. Delete client merge records
            ClientMerge::where('primary_client_id', $id)
                ->orWhere('merged_client_id', $id)
                ->delete();

            // 3. Hard delete the client from database
            $client->delete();

            \Illuminate\Support\Facades\DB::commit();

            \Illuminate\Support\Facades\Log::info('Client deleted successfully', [
                'client_id' => $id,
                'email' => $client->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Client and all related data deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            \Illuminate\Support\Facades\Log::error('Failed to delete client', [
                'client_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => 'Failed to delete client: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Delete all tickets for a client from ticket service
     * Returns true on success, false on failure
     */
    protected function deleteClientTickets(string $clientId): bool
    {
        try {
            $ticketServiceUrl = env('TICKET_SERVICE_URL', 'http://localhost:8002');

            // Call ticket service to delete all tickets for this client
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->delete("{$ticketServiceUrl}/api/v1/public/clients/{$clientId}/tickets");

            if ($response->successful()) {
                $data = $response->json();
                \Illuminate\Support\Facades\Log::info('Client tickets deleted', [
                    'client_id' => $clientId,
                    'deleted_count' => $data['deleted_count'] ?? 0
                ]);
                return true;
            } else {
                \Illuminate\Support\Facades\Log::error('Failed to delete client tickets', [
                    'client_id' => $clientId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error deleting client tickets', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
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