<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\AIProviderService;
use App\Models\AIProcessingQueue;
use Exception;

class ProcessAIRequestJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $requestId;
    protected $provider;
    protected $action;
    protected $data;
    protected $options;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(string $requestId, string $provider, string $action, array $data, array $options = [])
    {
        $this->requestId = $requestId;
        $this->provider = $provider;
        $this->action = $action;
        $this->data = $data;
        $this->options = $options;

        // Set queue based on priority
        $priority = $options['priority'] ?? 'default';
        $this->onQueue(config("queue.priorities.{$priority}", 'ai_processing'));
    }

    /**
     * Execute the job.
     */
    public function handle(AIProviderService $providerService)
    {
        try {
            Log::info("Processing AI request", [
                'request_id' => $this->requestId,
                'provider' => $this->provider,
                'action' => $this->action
            ]);

            // Update status to processing
            $this->updateQueueStatus('processing');

            // Get the appropriate provider adapter
            $adapter = $providerService->getAdapter($this->provider);

            // Process the request based on action
            $result = match($this->action) {
                'categorize' => $adapter->categorizeTicket($this->data),
                'prioritize' => $adapter->prioritizeTicket($this->data),
                'suggest_response' => $adapter->suggestResponse($this->data),
                'analyze_sentiment' => $adapter->analyzeSentiment($this->data),
                'extract_entities' => $adapter->extractEntities($this->data),
                'summarize' => $adapter->summarize($this->data),
                'generate_kb_article' => $adapter->generateKBArticle($this->data),
                default => throw new Exception("Unknown action: {$this->action}")
            };

            // Store the result
            $this->storeResult($result);

            // Update status to completed
            $this->updateQueueStatus('completed', $result);

            // Trigger any callbacks if specified
            $this->triggerCallback($result);

            Log::info("AI request processed successfully", [
                'request_id' => $this->requestId
            ]);

        } catch (Exception $e) {
            Log::error("AI request processing failed", [
                'request_id' => $this->requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update status to failed
            $this->updateQueueStatus('failed', null, $e->getMessage());

            // Rethrow for retry mechanism
            throw $e;
        }
    }

    /**
     * Update queue status in database
     */
    protected function updateQueueStatus($status, $result = null, $error = null)
    {
        AIProcessingQueue::where('id', $this->requestId)->update([
            'status' => $status,
            'result' => $result ? json_encode($result) : null,
            'error_message' => $error,
            'processed_at' => $status === 'completed' ? now() : null,
            'updated_at' => now()
        ]);
    }

    /**
     * Store the AI processing result
     */
    protected function storeResult($result)
    {
        // Store based on action type
        switch ($this->action) {
            case 'categorize':
                $this->storeCategorization($result);
                break;

            case 'prioritize':
                $this->storePrioritization($result);
                break;

            case 'suggest_response':
                $this->storeResponseSuggestion($result);
                break;

            case 'analyze_sentiment':
                $this->storeSentimentAnalysis($result);
                break;

            default:
                // Generic storage for other actions
                $this->storeGenericResult($result);
        }
    }

    /**
     * Store categorization result
     */
    protected function storeCategorization($result)
    {
        if (isset($this->data['ticket_id']) && isset($result['category_id'])) {
            // Update ticket with AI suggested category
            \DB::table('tickets')
                ->where('id', $this->data['ticket_id'])
                ->update([
                    'ai_suggested_category_id' => $result['category_id'],
                    'ai_confidence_score' => $result['confidence'] ?? null,
                    'ai_processed_at' => now(),
                    'ai_provider' => $this->provider
                ]);
        }
    }

    /**
     * Store prioritization result
     */
    protected function storePrioritization($result)
    {
        if (isset($this->data['ticket_id']) && isset($result['priority'])) {
            \DB::table('tickets')
                ->where('id', $this->data['ticket_id'])
                ->update([
                    'ai_suggested_priority' => $result['priority'],
                    'ai_confidence_score' => $result['confidence'] ?? null,
                    'ai_processed_at' => now(),
                    'ai_provider' => $this->provider
                ]);
        }
    }

    /**
     * Store response suggestion
     */
    protected function storeResponseSuggestion($result)
    {
        if (isset($this->data['ticket_id']) && isset($result['suggestion'])) {
            \DB::table('tickets')
                ->where('id', $this->data['ticket_id'])
                ->update([
                    'ai_suggestion' => $result['suggestion'],
                    'ai_confidence_score' => $result['confidence'] ?? null,
                    'ai_processed_at' => now(),
                    'ai_provider' => $this->provider
                ]);
        }
    }

    /**
     * Store sentiment analysis result
     */
    protected function storeSentimentAnalysis($result)
    {
        if (isset($this->data['ticket_id'])) {
            \DB::table('tickets')
                ->where('id', $this->data['ticket_id'])
                ->update([
                    'ai_suggestion' => json_encode([
                        'sentiment' => $result['sentiment'],
                        'score' => $result['score'] ?? null
                    ]),
                    'ai_processed_at' => now(),
                    'ai_provider' => $this->provider
                ]);
        }
    }

    /**
     * Store generic result
     */
    protected function storeGenericResult($result)
    {
        // Store in ai_processing_queue result field
        AIProcessingQueue::where('id', $this->requestId)->update([
            'result' => json_encode($result)
        ]);
    }

    /**
     * Trigger callback if specified
     */
    protected function triggerCallback($result)
    {
        if (isset($this->options['callback_url'])) {
            dispatch(new SendWebhookCallbackJob(
                $this->options['callback_url'],
                [
                    'request_id' => $this->requestId,
                    'status' => 'completed',
                    'result' => $result
                ]
            ));
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception)
    {
        Log::error("AI processing job failed permanently", [
            'request_id' => $this->requestId,
            'provider' => $this->provider,
            'action' => $this->action,
            'error' => $exception->getMessage()
        ]);

        // Update status to failed
        $this->updateQueueStatus('failed', null, $exception->getMessage());

        // Trigger failure callback if specified
        if (isset($this->options['callback_url'])) {
            dispatch(new SendWebhookCallbackJob(
                $this->options['callback_url'],
                [
                    'request_id' => $this->requestId,
                    'status' => 'failed',
                    'error' => $exception->getMessage()
                ]
            ));
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 300]; // 1 min, 2 min, 5 min
    }
}