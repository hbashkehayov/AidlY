<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessWebhookJob;
use App\Services\WebhookService;
use App\Services\AIProcessingService;

class WebhookController extends Controller
{
    protected $webhookService;
    protected $aiProcessingService;

    public function __construct(
        WebhookService $webhookService,
        AIProcessingService $aiProcessingService
    ) {
        $this->webhookService = $webhookService;
        $this->aiProcessingService = $aiProcessingService;
    }

    /**
     * Handle OpenAI webhook
     */
    public function handleOpenAI(Request $request)
    {
        try {
            // Log incoming webhook
            Log::info('OpenAI webhook received', [
                'headers' => $request->headers->all(),
                'ip' => $request->ip()
            ]);

            // Validate webhook payload
            if (!$this->webhookService->validateOpenAI($request)) {
                return response()->json(['error' => 'Invalid webhook signature'], 401);
            }

            // Extract webhook data
            $data = $request->all();
            $eventType = $data['type'] ?? null;
            $payload = $data['data'] ?? [];

            // Process webhook based on event type
            switch ($eventType) {
                case 'completion.created':
                    $this->processCompletion('openai', $payload);
                    break;

                case 'error':
                    $this->handleProviderError('openai', $payload);
                    break;

                default:
                    Log::warning('Unknown OpenAI webhook event', ['type' => $eventType]);
            }

            return response()->json(['status' => 'received'], 200);

        } catch (\Exception $e) {
            Log::error('OpenAI webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Anthropic webhook
     */
    public function handleAnthropic(Request $request)
    {
        try {
            Log::info('Anthropic webhook received');

            // Validate webhook
            if (!$this->webhookService->validateAnthropic($request)) {
                return response()->json(['error' => 'Invalid webhook signature'], 401);
            }

            $data = $request->all();
            $eventType = $data['event'] ?? null;
            $payload = $data['data'] ?? [];

            switch ($eventType) {
                case 'message.complete':
                    $this->processCompletion('anthropic', $payload);
                    break;

                case 'error':
                    $this->handleProviderError('anthropic', $payload);
                    break;

                default:
                    Log::warning('Unknown Anthropic webhook event', ['type' => $eventType]);
            }

            return response()->json(['status' => 'received'], 200);

        } catch (\Exception $e) {
            Log::error('Anthropic webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Google Gemini webhook
     */
    public function handleGemini(Request $request)
    {
        try {
            Log::info('Gemini webhook received');

            // Validate webhook
            if (!$this->webhookService->validateGemini($request)) {
                return response()->json(['error' => 'Invalid webhook signature'], 401);
            }

            $data = $request->all();
            $eventType = $data['eventType'] ?? null;
            $payload = $data['payload'] ?? [];

            switch ($eventType) {
                case 'generation.complete':
                    $this->processCompletion('gemini', $payload);
                    break;

                case 'error':
                    $this->handleProviderError('gemini', $payload);
                    break;

                default:
                    Log::warning('Unknown Gemini webhook event', ['type' => $eventType]);
            }

            return response()->json(['status' => 'received'], 200);

        } catch (\Exception $e) {
            Log::error('Gemini webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle custom provider webhook
     */
    public function handleCustom(Request $request, $provider)
    {
        try {
            Log::info("Custom webhook received from: $provider");

            // Validate custom webhook
            if (!$this->webhookService->validateCustom($request, $provider)) {
                return response()->json(['error' => 'Invalid webhook'], 401);
            }

            $data = $request->all();

            // Queue for processing
            Queue::push(new ProcessWebhookJob($provider, $data));

            return response()->json(['status' => 'queued'], 200);

        } catch (\Exception $e) {
            Log::error("Custom webhook error for $provider", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle processing callbacks
     */
    public function handleCallback(Request $request, $jobId)
    {
        try {
            Log::info("Processing callback for job: $jobId");

            $data = $request->all();
            $status = $data['status'] ?? 'unknown';
            $result = $data['result'] ?? null;

            // Update job status in database
            $this->aiProcessingService->updateJobStatus($jobId, $status, $result);

            // If job is complete, trigger any follow-up actions
            if ($status === 'completed') {
                $this->aiProcessingService->handleCompletedJob($jobId, $result);
            } elseif ($status === 'failed') {
                $this->aiProcessingService->handleFailedJob($jobId, $result);
            }

            return response()->json(['status' => 'processed'], 200);

        } catch (\Exception $e) {
            Log::error("Callback processing error for job $jobId", [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Process AI completion
     */
    protected function processCompletion($provider, $payload)
    {
        try {
            $jobId = $payload['job_id'] ?? $payload['request_id'] ?? null;
            $result = $payload['result'] ?? $payload['content'] ?? null;
            $metadata = $payload['metadata'] ?? [];

            if (!$jobId) {
                Log::warning("No job ID in completion webhook from $provider");
                return;
            }

            // Update job with result
            $this->aiProcessingService->completeJob($jobId, [
                'provider' => $provider,
                'result' => $result,
                'metadata' => $metadata,
                'completed_at' => now()
            ]);

            // Trigger any post-processing
            $this->aiProcessingService->postProcess($jobId, $result);

        } catch (\Exception $e) {
            Log::error("Error processing completion from $provider", [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
        }
    }

    /**
     * Handle provider errors
     */
    protected function handleProviderError($provider, $payload)
    {
        try {
            $jobId = $payload['job_id'] ?? $payload['request_id'] ?? null;
            $error = $payload['error'] ?? 'Unknown error';
            $code = $payload['error_code'] ?? null;

            Log::error("Provider error from $provider", [
                'job_id' => $jobId,
                'error' => $error,
                'code' => $code
            ]);

            if ($jobId) {
                // Mark job as failed
                $this->aiProcessingService->failJob($jobId, [
                    'provider' => $provider,
                    'error' => $error,
                    'error_code' => $code,
                    'failed_at' => now()
                ]);

                // Attempt retry if configured
                if ($this->shouldRetry($provider, $code)) {
                    $this->aiProcessingService->retryJob($jobId);
                }
            }

        } catch (\Exception $e) {
            Log::error("Error handling provider error from $provider", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine if job should be retried
     */
    protected function shouldRetry($provider, $errorCode)
    {
        // Rate limit and temporary errors should be retried
        $retryableCodes = ['rate_limit', 'timeout', 'temporary_failure', '503', '429'];

        return in_array($errorCode, $retryableCodes);
    }
}