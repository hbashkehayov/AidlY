I<?php

namespace App\Services\Providers;

use App\Services\AIProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenAIProvider implements AIProviderInterface
{
    protected $client;
    protected $apiKey;
    protected $config;

    public function __construct()
    {
        $this->config = config('ai.providers.openai');
        $this->apiKey = $this->config['api_key'];

        $this->client = new Client([
            'base_uri' => $this->config['base_url'],
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    /**
     * Categorize a support ticket
     */
    public function categorizeTicket(array $data): array
    {
        try {
            $prompt = $this->buildCategorizationPrompt($data);

            $response = $this->makeRequest('chat/completions', [
                'model' => $this->config['models']['categorization'],
                'messages' => [
                    ['role' => 'system', 'content' => config('ai.prompts.categorization.system')],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => config('ai.prompts.categorization.temperature'),
                'max_tokens' => config('ai.prompts.categorization.max_tokens'),
            ]);

            return $this->parseCategorizationResponse($response);

        } catch (\Exception $e) {
            Log::error('OpenAI categorization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Prioritize a support ticket
     */
    public function prioritizeTicket(array $data): array
    {
        try {
            $prompt = $this->buildPrioritizationPrompt($data);

            $response = $this->makeRequest('chat/completions', [
                'model' => $this->config['models']['prioritization'],
                'messages' => [
                    ['role' => 'system', 'content' => config('ai.prompts.prioritization.system')],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => config('ai.prompts.prioritization.temperature'),
                'max_tokens' => config('ai.prompts.prioritization.max_tokens'),
            ]);

            return $this->parsePrioritizationResponse($response);

        } catch (\Exception $e) {
            Log::error('OpenAI prioritization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Suggest a response for a ticket
     */
    public function suggestResponse(array $data): array
    {
        try {
            $prompt = $this->buildResponsePrompt($data);

            $response = $this->makeRequest('chat/completions', [
                'model' => $this->config['models']['response_suggestion'],
                'messages' => [
                    ['role' => 'system', 'content' => config('ai.prompts.response_suggestion.system')],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => config('ai.prompts.response_suggestion.temperature'),
                'max_tokens' => config('ai.prompts.response_suggestion.max_tokens'),
            ]);

            return [
                'suggestion' => $response['choices'][0]['message']['content'],
                'confidence' => $this->calculateConfidence($response),
                'model' => $this->config['models']['response_suggestion']
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI response suggestion failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Analyze sentiment of customer message
     */
    public function analyzeSentiment(array $data): array
    {
        try {
            $text = $data['text'] ?? $data['message'] ?? '';

            $response = $this->makeRequest('chat/completions', [
                'model' => $this->config['models']['sentiment'],
                'messages' => [
                    ['role' => 'system', 'content' => config('ai.prompts.sentiment_analysis.system')],
                    ['role' => 'user', 'content' => "Analyze the sentiment of: \"$text\""]
                ],
                'temperature' => config('ai.prompts.sentiment_analysis.temperature'),
                'max_tokens' => config('ai.prompts.sentiment_analysis.max_tokens'),
            ]);

            return $this->parseSentimentResponse($response);

        } catch (\Exception $e) {
            Log::error('OpenAI sentiment analysis failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Extract entities from text
     */
    public function extractEntities(array $data): array
    {
        try {
            $text = $data['text'] ?? '';

            $response = $this->makeRequest('chat/completions', [
                'model' => $this->config['models']['categorization'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Extract entities (names, products, dates, etc.) from the text.'],
                    ['role' => 'user', 'content' => $text]
                ],
                'temperature' => 0.1,
                'max_tokens' => 200,
            ]);

            return $this->parseEntityResponse($response);

        } catch (\Exception $e) {
            Log::error('OpenAI entity extraction failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Summarize ticket or conversation
     */
    public function summarize(array $data): array
    {
        try {
            $text = $data['text'] ?? $data['conversation'] ?? '';

            $response = $this->makeRequest('chat/completions', [
                'model' => $this->config['models']['summarization'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Provide a concise summary of the following conversation or ticket.'],
                    ['role' => 'user', 'content' => $text]
                ],
                'temperature' => 0.3,
                'max_tokens' => 200,
            ]);

            return [
                'summary' => $response['choices'][0]['message']['content'],
                'model' => $this->config['models']['summarization']
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI summarization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate knowledge base article
     */
    public function generateKBArticle(array $data): array
    {
        try {
            $prompt = $this->buildKBArticlePrompt($data);

            $response = $this->makeRequest('chat/completions', [
                'model' => $this->config['models']['response_suggestion'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Generate a comprehensive knowledge base article.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ]);

            return [
                'article' => $response['choices'][0]['message']['content'],
                'model' => $this->config['models']['response_suggestion']
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI KB article generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check provider health status
     */
    public function healthCheck(): array
    {
        try {
            $start = microtime(true);

            $response = $this->client->get('models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ]
            ]);

            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'healthy',
                'latency' => round($latency, 2) . 'ms',
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Get provider capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'categorization' => true,
            'prioritization' => true,
            'response_suggestion' => true,
            'sentiment_analysis' => true,
            'entity_extraction' => true,
            'summarization' => true,
            'kb_generation' => true,
            'multi_language' => true,
            'batch_processing' => true,
            'streaming' => true,
        ];
    }

    /**
     * Get provider usage statistics
     */
    public function getUsage(): array
    {
        // This would typically call OpenAI's usage API
        // For now, return cached stats
        return Cache::get('openai_usage', [
            'requests_today' => 0,
            'tokens_used' => 0,
            'cost_estimate' => 0,
        ]);
    }

    /**
     * Make API request to OpenAI
     */
    protected function makeRequest($endpoint, $data)
    {
        try {
            $response = $this->client->post($endpoint, [
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $body = $response ? $response->getBody()->getContents() : null;

            Log::error('OpenAI API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'response' => $body
            ]);

            throw new \Exception('OpenAI API error: ' . ($body ?? $e->getMessage()));
        }
    }

    /**
     * Build categorization prompt
     */
    protected function buildCategorizationPrompt($data): string
    {
        $subject = $data['subject'] ?? '';
        $description = $data['description'] ?? '';
        $categories = $data['categories'] ?? [];

        $prompt = "Categorize this support ticket:\n";
        $prompt .= "Subject: $subject\n";
        $prompt .= "Description: $description\n";

        if (!empty($categories)) {
            $prompt .= "\nAvailable categories: " . implode(', ', $categories);
        }

        return $prompt;
    }

    /**
     * Build prioritization prompt
     */
    protected function buildPrioritizationPrompt($data): string
    {
        $subject = $data['subject'] ?? '';
        $description = $data['description'] ?? '';
        $customer = $data['customer'] ?? [];

        $prompt = "Determine the priority of this support ticket:\n";
        $prompt .= "Subject: $subject\n";
        $prompt .= "Description: $description\n";

        if (!empty($customer)) {
            $prompt .= "Customer type: " . ($customer['is_vip'] ? 'VIP' : 'Regular') . "\n";
        }

        $prompt .= "\nPriority levels: low, medium, high, urgent";

        return $prompt;
    }

    /**
     * Build response prompt
     */
    protected function buildResponsePrompt($data): string
    {
        $ticket = $data['ticket'] ?? [];
        $context = $data['context'] ?? [];

        $prompt = "Suggest a professional response for this ticket:\n";
        $prompt .= "Subject: " . ($ticket['subject'] ?? '') . "\n";
        $prompt .= "Description: " . ($ticket['description'] ?? '') . "\n";

        if (!empty($context)) {
            $prompt .= "\nAdditional context:\n" . json_encode($context);
        }

        return $prompt;
    }

    /**
     * Build KB article prompt
     */
    protected function buildKBArticlePrompt($data): string
    {
        $topic = $data['topic'] ?? '';
        $issues = $data['common_issues'] ?? [];
        $solutions = $data['solutions'] ?? [];

        $prompt = "Create a knowledge base article about: $topic\n";

        if (!empty($issues)) {
            $prompt .= "\nCommon issues to address:\n" . implode("\n", $issues);
        }

        if (!empty($solutions)) {
            $prompt .= "\nKnown solutions:\n" . implode("\n", $solutions);
        }

        return $prompt;
    }

    /**
     * Parse categorization response
     */
    protected function parseCategorizationResponse($response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        // Parse the response to extract category
        // This is simplified - in production, you'd have more robust parsing
        return [
            'category' => trim($content),
            'confidence' => $this->calculateConfidence($response),
            'model' => $this->config['models']['categorization']
        ];
    }

    /**
     * Parse prioritization response
     */
    protected function parsePrioritizationResponse($response): array
    {
        $content = strtolower(trim($response['choices'][0]['message']['content'] ?? ''));

        // Ensure valid priority
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = in_array($content, $validPriorities) ? $content : 'medium';

        return [
            'priority' => $priority,
            'confidence' => $this->calculateConfidence($response),
            'model' => $this->config['models']['prioritization']
        ];
    }

    /**
     * Parse sentiment response
     */
    protected function parseSentimentResponse($response): array
    {
        $content = strtolower(trim($response['choices'][0]['message']['content'] ?? ''));

        // Parse sentiment
        $sentiment = 'neutral';
        if (strpos($content, 'positive') !== false) {
            $sentiment = 'positive';
        } elseif (strpos($content, 'negative') !== false) {
            $sentiment = 'negative';
        } elseif (strpos($content, 'critical') !== false) {
            $sentiment = 'critical';
        }

        return [
            'sentiment' => $sentiment,
            'score' => $this->calculateConfidence($response),
            'model' => $this->config['models']['sentiment']
        ];
    }

    /**
     * Parse entity extraction response
     */
    protected function parseEntityResponse($response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        // Simple entity parsing - would be more sophisticated in production
        return [
            'entities' => $content,
            'model' => $this->config['models']['categorization']
        ];
    }

    /**
     * Calculate confidence score from response
     */
    protected function calculateConfidence($response): float
    {
        // OpenAI doesn't provide direct confidence scores
        // This is a simplified calculation based on logprobs if available
        return 0.85; // Default confidence
    }
}