<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientNote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClientNoteController extends Controller
{
    /**
     * Get all notes for a client
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

        $query = ClientNote::byClient($clientId);

        // Filter by pinned if requested
        if ($request->has('pinned')) {
            if ($request->boolean('pinned')) {
                $query->pinned();
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort', '-created_at');
        $sortDirection = str_starts_with($sortBy, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sortBy, '-');

        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = min($request->get('limit', 20), 100);
        $notes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notes->items(),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'last_page' => $notes->lastPage(),
            ]
        ]);
    }

    /**
     * Create a new note for a client
     */
    public function store(Request $request, string $clientId): JsonResponse
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

        $this->validate($request, [
            'note' => 'required|string',
            'is_pinned' => 'nullable|boolean'
        ]);

        $note = ClientNote::create([
            'client_id' => $clientId,
            'created_by' => auth()->id() ?? 'system', // TODO: Get from JWT
            'note' => $request->get('note'),
            'is_pinned' => $request->boolean('is_pinned', false)
        ]);

        return response()->json([
            'success' => true,
            'data' => $note,
            'message' => 'Note created successfully'
        ], 201);
    }

    /**
     * Get a specific note
     */
    public function show(string $clientId, string $noteId): JsonResponse
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

        $note = ClientNote::where('client_id', $clientId)
                         ->where('id', $noteId)
                         ->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOTE_NOT_FOUND',
                    'message' => 'Note not found'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $note
        ]);
    }

    /**
     * Update a note
     */
    public function update(Request $request, string $clientId, string $noteId): JsonResponse
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

        $note = ClientNote::where('client_id', $clientId)
                         ->where('id', $noteId)
                         ->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOTE_NOT_FOUND',
                    'message' => 'Note not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'note' => 'sometimes|required|string',
            'is_pinned' => 'nullable|boolean'
        ]);

        $note->fill($request->all());
        $note->save();

        return response()->json([
            'success' => true,
            'data' => $note,
            'message' => 'Note updated successfully'
        ]);
    }

    /**
     * Delete a note
     */
    public function destroy(string $clientId, string $noteId): JsonResponse
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

        $note = ClientNote::where('client_id', $clientId)
                         ->where('id', $noteId)
                         ->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOTE_NOT_FOUND',
                    'message' => 'Note not found'
                ]
            ], 404);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
    }

    /**
     * Toggle pin status of a note
     */
    public function togglePin(string $clientId, string $noteId): JsonResponse
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

        $note = ClientNote::where('client_id', $clientId)
                         ->where('id', $noteId)
                         ->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOTE_NOT_FOUND',
                    'message' => 'Note not found'
                ]
            ], 404);
        }

        if ($note->isPinned()) {
            $note->unpin();
            $message = 'Note unpinned successfully';
        } else {
            $note->pin();
            $message = 'Note pinned successfully';
        }

        return response()->json([
            'success' => true,
            'data' => $note,
            'message' => $message
        ]);
    }
}