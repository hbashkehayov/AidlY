<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientMerge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClientMergeController extends Controller
{
    /**
     * Get merge history for a client
     */
    public function index(string $clientId, Request $request): JsonResponse
    {
        $client = Client::active()->find($clientId);

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => 'Client not found'
                ]
            ], 404);
        }

        $query = ClientMerge::byPrimaryClient($clientId);

        // Apply sorting
        $sortBy = $request->get('sort', '-created_at');
        $sortDirection = str_starts_with($sortBy, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sortBy, '-');

        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = min($request->get('limit', 20), 100);
        $merges = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $merges->items(),
            'meta' => [
                'current_page' => $merges->currentPage(),
                'per_page' => $merges->perPage(),
                'total' => $merges->total(),
                'last_page' => $merges->lastPage(),
            ]
        ]);
    }

    /**
     * Merge two clients
     */
    public function merge(Request $request): JsonResponse
    {
        $this->validate($request, [
            'primary_client_id' => 'required|string|exists:clients,id',
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'required|string|exists:clients,id',
            'merge_strategy' => 'required|in:keep_primary,prefer_newest,prefer_complete',
            'fields_to_merge' => 'nullable|array'
        ]);

        $primaryClientId = $request->get('primary_client_id');
        $clientIdsToMerge = $request->get('client_ids');

        // Check if primary client exists and is active
        $primaryClient = Client::active()->find($primaryClientId);
        if (!$primaryClient) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRIMARY_CLIENT_NOT_FOUND',
                    'message' => 'Primary client not found'
                ]
            ], 404);
        }

        // Check if all clients to merge exist and are active
        $clientsToMerge = Client::active()->whereIn('id', $clientIdsToMerge)->get();
        if ($clientsToMerge->count() !== count($clientIdsToMerge)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SOME_CLIENTS_NOT_FOUND',
                    'message' => 'Some clients to merge were not found'
                ]
            ], 404);
        }

        // Prevent merging client with itself
        if (in_array($primaryClientId, $clientIdsToMerge)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_MERGE_WITH_SELF',
                    'message' => 'Cannot merge client with itself'
                ]
            ], 422);
        }

        DB::beginTransaction();

        try {
            $mergeData = [];

            foreach ($clientsToMerge as $clientToMerge) {
                // Store original data before merge
                $originalData = $clientToMerge->toArray();

                // Apply merge strategy
                $mergedData = $this->applyMergeStrategy(
                    $primaryClient,
                    $clientToMerge,
                    $request->get('merge_strategy'),
                    $request->get('fields_to_merge', [])
                );

                // Update primary client with merged data
                $primaryClient->fill($mergedData);
                $primaryClient->save();

                // Create merge record
                ClientMerge::create([
                    'primary_client_id' => $primaryClientId,
                    'merged_client_id' => $clientToMerge->id,
                    'merged_by' => auth()->id() ?? 'system', // TODO: Get from JWT
                    'merge_data' => [
                        'original_client_data' => $originalData,
                        'merge_strategy' => $request->get('merge_strategy'),
                        'fields_merged' => array_keys($mergedData),
                        'merged_at' => now()->toISOString()
                    ]
                ]);

                // Move notes from merged client to primary client
                $clientToMerge->notes()->update(['client_id' => $primaryClientId]);

                // Soft delete the merged client
                $clientToMerge->softDelete();

                $mergeData[] = [
                    'merged_client_id' => $clientToMerge->id,
                    'merged_client_email' => $clientToMerge->email,
                    'fields_updated' => array_keys($mergedData)
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'primary_client' => $primaryClient->fresh(),
                    'merge_details' => $mergeData
                ],
                'message' => 'Clients merged successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MERGE_FAILED',
                    'message' => 'Failed to merge clients: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Preview merge (dry run)
     */
    public function previewMerge(Request $request): JsonResponse
    {
        $this->validate($request, [
            'primary_client_id' => 'required|string|exists:clients,id',
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'required|string|exists:clients,id',
            'merge_strategy' => 'required|in:keep_primary,prefer_newest,prefer_complete'
        ]);

        $primaryClientId = $request->get('primary_client_id');
        $clientIdsToMerge = $request->get('client_ids');

        $primaryClient = Client::active()->find($primaryClientId);
        if (!$primaryClient) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRIMARY_CLIENT_NOT_FOUND',
                    'message' => 'Primary client not found'
                ]
            ], 404);
        }

        $clientsToMerge = Client::active()->whereIn('id', $clientIdsToMerge)->get();

        $preview = [];
        $mergedData = $primaryClient->toArray();

        foreach ($clientsToMerge as $clientToMerge) {
            $changes = $this->applyMergeStrategy(
                $primaryClient,
                $clientToMerge,
                $request->get('merge_strategy')
            );

            $preview[] = [
                'client_id' => $clientToMerge->id,
                'client_email' => $clientToMerge->email,
                'changes_to_apply' => $changes,
                'notes_count' => $clientToMerge->notes()->count()
            ];

            // Apply changes for next iteration
            $mergedData = array_merge($mergedData, $changes);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'primary_client' => $primaryClient,
                'final_merged_data' => $mergedData,
                'merge_preview' => $preview
            ]
        ]);
    }

    /**
     * Apply merge strategy to determine which data to keep
     */
    private function applyMergeStrategy(Client $primary, Client $secondary, string $strategy, array $fieldsToMerge = []): array
    {
        $changes = [];

        $mergeableFields = [
            'name', 'company', 'phone', 'mobile', 'timezone', 'language',
            'address_line1', 'address_line2', 'city', 'state', 'country', 'postal_code',
            'crm_id', 'crm_type', 'lead_score', 'lifetime_value',
            'notification_preferences', 'custom_fields', 'tags'
        ];

        // If specific fields are requested, only merge those
        if (!empty($fieldsToMerge)) {
            $mergeableFields = array_intersect($mergeableFields, $fieldsToMerge);
        }

        foreach ($mergeableFields as $field) {
            $primaryValue = $primary->$field;
            $secondaryValue = $secondary->$field;

            switch ($strategy) {
                case 'keep_primary':
                    // Only fill empty fields in primary with secondary data
                    if (empty($primaryValue) && !empty($secondaryValue)) {
                        $changes[$field] = $secondaryValue;
                    }
                    break;

                case 'prefer_newest':
                    // Use data from the most recently updated client
                    if ($secondary->updated_at > $primary->updated_at && !empty($secondaryValue)) {
                        $changes[$field] = $secondaryValue;
                    }
                    break;

                case 'prefer_complete':
                    // Use the most complete data (non-empty values)
                    if (!empty($secondaryValue) && empty($primaryValue)) {
                        $changes[$field] = $secondaryValue;
                    }
                    break;
            }
        }

        // Special handling for arrays (tags, custom_fields, etc.)
        if (isset($changes['tags'])) {
            $primaryTags = $primary->tags ?: [];
            $secondaryTags = $changes['tags'] ?: [];
            $changes['tags'] = array_unique(array_merge($primaryTags, $secondaryTags));
        }

        if (isset($changes['custom_fields'])) {
            $primaryFields = $primary->custom_fields ?: [];
            $secondaryFields = $changes['custom_fields'] ?: [];
            $changes['custom_fields'] = array_merge($primaryFields, $secondaryFields);
        }

        // Update contact timestamps
        if ($secondary->first_contact_at < $primary->first_contact_at) {
            $changes['first_contact_at'] = $secondary->first_contact_at;
        }

        if ($secondary->last_contact_at > $primary->last_contact_at) {
            $changes['last_contact_at'] = $secondary->last_contact_at;
        }

        // Update VIP status if secondary is VIP
        if ($secondary->is_vip && !$primary->is_vip) {
            $changes['is_vip'] = true;
        }

        return $changes;
    }
}