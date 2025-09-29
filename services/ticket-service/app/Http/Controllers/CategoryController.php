<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index(): JsonResponse
    {
        try {
            $categories = Category::active()
                ->with(['parent', 'children'])
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve categories',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Get root categories with their children
     */
    public function tree(): JsonResponse
    {
        try {
            $categories = Category::active()
                ->rootCategories()
                ->with(['children' => function ($query) {
                    $query->active()->ordered();
                }])
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve category tree',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Get a specific category
     */
    public function show(string $id): JsonResponse
    {
        try {
            $category = Category::active()
                ->with(['parent', 'children', 'tickets'])
                ->find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'Category not found'
                    ]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve category',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Create a new category
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_category_id' => 'nullable|string|uuid|exists:categories,id',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_order' => 'nullable|integer|min:0'
        ]);

        try {
            $category = Category::create($request->only([
                'name',
                'description',
                'parent_category_id',
                'icon',
                'color',
                'display_order'
            ]));

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to create category',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Update a category
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::active()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Category not found'
                ]
            ], 404);
        }

        $this->validate($request, [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'parent_category_id' => 'nullable|string|uuid|exists:categories,id',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Prevent circular references
        if ($request->parent_category_id && $request->parent_category_id === $category->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Category cannot be its own parent'
                ]
            ], 422);
        }

        try {
            $category->update($request->only([
                'name',
                'description',
                'parent_category_id',
                'icon',
                'color',
                'display_order',
                'is_active'
            ]));

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to update category',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Delete a category
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::active()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Category not found'
                ]
            ], 404);
        }

        // Check if category has tickets
        $ticketCount = $category->tickets()->count();
        if ($ticketCount > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Cannot delete category with associated tickets',
                    'details' => "Category has {$ticketCount} tickets"
                ]
            ], 422);
        }

        // Check if category has children
        if ($category->hasChildren()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Cannot delete category with subcategories'
                ]
            ], 422);
        }

        try {
            $category->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Category deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to delete category',
                    'details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }
}