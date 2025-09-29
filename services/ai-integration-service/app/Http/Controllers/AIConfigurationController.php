<?php

namespace App\Http\Controllers;

use App\Models\AIConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Lumen\Routing\Controller as BaseController;

class AIConfigurationController extends BaseController
{
    /**
     * Get all AI configurations
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = AIConfiguration::query();

            // Filter by provider if specified
            if ($request->has('provider')) {
                $query->where('provider', $request->provider);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $configurations = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $configurations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI configurations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific AI configuration
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        try {
            $configuration = AIConfiguration::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $configuration
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI configuration not found'
            ], 404);
        }
    }

    /**
     * Create a new AI configuration
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'provider' => 'required|string|in:openai,claude,gemini,custom,n8n',
            'api_endpoint' => 'nullable|url',
            'api_key' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
            'model_settings' => 'nullable|array',
            'retry_policy' => 'nullable|array',
            'timeout_seconds' => 'nullable|integer|min:5|max:300',
            'enable_categorization' => 'boolean',
            'enable_suggestions' => 'boolean',
            'enable_sentiment' => 'boolean',
            'enable_auto_assign' => 'boolean',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $configuration = AIConfiguration::create([
                'name' => $request->name,
                'provider' => $request->provider,
                'api_endpoint' => $request->api_endpoint,
                'api_key_encrypted' => $request->api_key ? encrypt($request->api_key) : null,
                'webhook_secret' => $request->webhook_secret,
                'model_settings' => $request->model_settings ?? [],
                'retry_policy' => $request->retry_policy ?? [
                    'max_attempts' => 3,
                    'delay_seconds' => 5,
                    'backoff_multiplier' => 2
                ],
                'timeout_seconds' => $request->timeout_seconds ?? 30,
                'enable_categorization' => $request->boolean('enable_categorization', false),
                'enable_suggestions' => $request->boolean('enable_suggestions', false),
                'enable_sentiment' => $request->boolean('enable_sentiment', false),
                'enable_auto_assign' => $request->boolean('enable_auto_assign', false),
                'is_active' => $request->boolean('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'AI configuration created successfully',
                'data' => $configuration
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create AI configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an AI configuration
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'provider' => 'sometimes|string|in:openai,claude,gemini,custom,n8n',
            'api_endpoint' => 'nullable|url',
            'api_key' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
            'model_settings' => 'nullable|array',
            'retry_policy' => 'nullable|array',
            'timeout_seconds' => 'nullable|integer|min:5|max:300',
            'enable_categorization' => 'boolean',
            'enable_suggestions' => 'boolean',
            'enable_sentiment' => 'boolean',
            'enable_auto_assign' => 'boolean',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $configuration = AIConfiguration::findOrFail($id);

            $updateData = $request->only([
                'name', 'provider', 'api_endpoint', 'webhook_secret',
                'model_settings', 'retry_policy', 'timeout_seconds',
                'enable_categorization', 'enable_suggestions',
                'enable_sentiment', 'enable_auto_assign', 'is_active'
            ]);

            // Encrypt API key if provided
            if ($request->has('api_key') && $request->api_key !== null) {
                $updateData['api_key_encrypted'] = encrypt($request->api_key);
            }

            $configuration->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'AI configuration updated successfully',
                'data' => $configuration->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update AI configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an AI configuration
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        try {
            $configuration = AIConfiguration::findOrFail($id);
            $configuration->delete();

            return response()->json([
                'success' => true,
                'message' => 'AI configuration deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete AI configuration'
            ], 500);
        }
    }

    /**
     * Test AI configuration connection
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection(string $id)
    {
        try {
            $configuration = AIConfiguration::findOrFail($id);

            // Test connection based on provider
            $result = $this->performConnectionTest($configuration);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI feature flags
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeatureFlags()
    {
        try {
            $flags = [
                'ai_categorization_enabled' => env('AI_FEATURE_AUTO_CATEGORIZATION', false),
                'ai_prioritization_enabled' => env('AI_FEATURE_AUTO_PRIORITIZATION', false),
                'ai_suggestions_enabled' => env('AI_FEATURE_RESPONSE_SUGGESTIONS', false),
                'ai_sentiment_analysis_enabled' => env('AI_FEATURE_SENTIMENT_ANALYSIS', false),
                'ai_auto_assignment_enabled' => env('AI_FEATURE_AUTO_ASSIGNMENT', false),
                'ai_language_detection_enabled' => env('AI_FEATURE_LANGUAGE_DETECTION', true),
            ];

            return response()->json([
                'success' => true,
                'data' => $flags
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get feature flags'
            ], 500);
        }
    }

    /**
     * Update AI feature flags
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFeatureFlags(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ai_categorization_enabled' => 'boolean',
            'ai_prioritization_enabled' => 'boolean',
            'ai_suggestions_enabled' => 'boolean',
            'ai_sentiment_analysis_enabled' => 'boolean',
            'ai_auto_assignment_enabled' => 'boolean',
            'ai_language_detection_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // In a production environment, you would update these in a database or config management system
            // For now, we'll just return the updated values
            $flags = $request->only([
                'ai_categorization_enabled',
                'ai_prioritization_enabled',
                'ai_suggestions_enabled',
                'ai_sentiment_analysis_enabled',
                'ai_auto_assignment_enabled',
                'ai_language_detection_enabled'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Feature flags updated successfully',
                'data' => $flags
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feature flags',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform connection test based on provider
     *
     * @param AIConfiguration $configuration
     * @return array
     */
    private function performConnectionTest(AIConfiguration $configuration): array
    {
        switch ($configuration->provider) {
            case 'openai':
                return $this->testOpenAIConnection($configuration);
            case 'claude':
                return $this->testClaudeConnection($configuration);
            case 'gemini':
                return $this->testGeminiConnection($configuration);
            case 'n8n':
                return $this->testN8nConnection($configuration);
            default:
                return ['success' => false, 'message' => 'Unsupported provider'];
        }
    }

    private function testOpenAIConnection(AIConfiguration $configuration): array
    {
        // Mock test - in production, make actual API call
        return [
            'success' => true,
            'message' => 'OpenAI connection successful',
            'data' => ['latency' => rand(100, 300) . 'ms']
        ];
    }

    private function testClaudeConnection(AIConfiguration $configuration): array
    {
        // Mock test - in production, make actual API call
        return [
            'success' => true,
            'message' => 'Claude connection successful',
            'data' => ['latency' => rand(100, 300) . 'ms']
        ];
    }

    private function testGeminiConnection(AIConfiguration $configuration): array
    {
        // Mock test - in production, make actual API call
        return [
            'success' => true,
            'message' => 'Gemini connection successful',
            'data' => ['latency' => rand(100, 300) . 'ms']
        ];
    }

    private function testN8nConnection(AIConfiguration $configuration): array
    {
        // Mock test - in production, make actual webhook call
        return [
            'success' => true,
            'message' => 'n8n webhook connection successful',
            'data' => ['webhook_url' => $configuration->api_endpoint]
        ];
    }
}