<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\AIProviderInterface;
use App\Services\Providers\OpenAIProvider;

class ProcessingController extends Controller
{
    /**
     * Auto-write text for rich text editor
     * This endpoint generates AI-powered text based on context
     */
    public function autoWrite(Request $request)
    {
        try {
            // Validate request
            $this->validate($request, [
                'ticket_id' => 'required|uuid',
                'context' => 'string|nullable',
                'tone' => 'string|nullable|in:professional,friendly,empathetic,formal',
                'length' => 'string|nullable|in:short,medium,long',
            ]);

            $ticketId = $request->input('ticket_id');
            $context = $request->input('context', '');
            $tone = $request->input('tone', 'professional');
            $length = $request->input('length', 'medium');

            Log::info('Auto-write request received', [
                'ticket_id' => $ticketId,
                'tone' => $tone,
                'length' => $length,
                'has_context' => !empty($context),
            ]);

            // Check if AI is enabled
            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                // Use real AI provider
                try {
                    $provider = new OpenAIProvider();

                    $prompt = $this->buildAutoWritePrompt($context, $tone, $length);

                    $result = $provider->suggestResponse([
                        'ticket' => [
                            'id' => $ticketId,
                            'context' => $context,
                        ],
                        'context' => [
                            'tone' => $tone,
                            'length' => $length,
                        ],
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'text' => $result['suggestion'] ?? '',
                            'confidence' => $result['confidence'] ?? 0,
                            'source' => 'ai',
                            'provider' => 'openai',
                        ],
                    ]);

                } catch (\Exception $e) {
                    // If AI fails, fall back to mock text
                    Log::warning('AI auto-write failed, using fallback', [
                        'error' => $e->getMessage(),
                    ]);
                    return $this->getFallbackResponse($tone, $length);
                }
            } else {
                // AI not enabled - use fallback text
                Log::info('AI not enabled, using fallback text');
                return $this->getFallbackResponse($tone, $length);
            }

        } catch (\Exception $e) {
            Log::error('Auto-write error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate text',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suggest response for a ticket
     */
    public function suggestResponse(Request $request)
    {
        try {
            $this->validate($request, [
                'ticket_id' => 'required|uuid',
                'ticket' => 'required|array',
                'context' => 'array',
            ]);

            $ticketData = $request->input('ticket');
            $context = $request->input('context', []);

            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                $provider = new OpenAIProvider();

                $result = $provider->suggestResponse([
                    'ticket' => $ticketData,
                    'context' => $context,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            } else {
                return $this->getFallbackResponse('professional', 'medium');
            }

        } catch (\Exception $e) {
            Log::error('Suggest response error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate response suggestion',
            ], 500);
        }
    }

    /**
     * Categorize a ticket
     */
    public function categorizeTicket(Request $request)
    {
        try {
            $this->validate($request, [
                'subject' => 'required|string',
                'description' => 'required|string',
                'categories' => 'array',
            ]);

            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                $provider = new OpenAIProvider();

                $result = $provider->categorizeTicket($request->all());

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'category' => 'General Support',
                        'confidence' => 0.75,
                        'source' => 'fallback',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Categorize ticket error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to categorize ticket',
            ], 500);
        }
    }

    /**
     * Prioritize a ticket
     */
    public function prioritizeTicket(Request $request)
    {
        try {
            $this->validate($request, [
                'subject' => 'required|string',
                'description' => 'required|string',
            ]);

            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                $provider = new OpenAIProvider();

                $result = $provider->prioritizeTicket($request->all());

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'priority' => 'medium',
                        'confidence' => 0.70,
                        'source' => 'fallback',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Prioritize ticket error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to prioritize ticket',
            ], 500);
        }
    }

    /**
     * Analyze sentiment
     */
    public function analyzeSentiment(Request $request)
    {
        try {
            $this->validate($request, [
                'text' => 'required|string',
            ]);

            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                $provider = new OpenAIProvider();

                $result = $provider->analyzeSentiment($request->all());

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'sentiment' => 'neutral',
                        'score' => 0.50,
                        'source' => 'fallback',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Analyze sentiment error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to analyze sentiment',
            ], 500);
        }
    }

    /**
     * Extract entities
     */
    public function extractEntities(Request $request)
    {
        try {
            $this->validate($request, [
                'text' => 'required|string',
            ]);

            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                $provider = new OpenAIProvider();

                $result = $provider->extractEntities($request->all());

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'entities' => [],
                        'source' => 'fallback',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Extract entities error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to extract entities',
            ], 500);
        }
    }

    /**
     * Summarize ticket
     */
    public function summarizeTicket(Request $request)
    {
        try {
            $this->validate($request, [
                'text' => 'required|string',
            ]);

            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                $provider = new OpenAIProvider();

                $result = $provider->summarize($request->all());

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'summary' => 'This is a summary placeholder. Enable AI to get real summaries.',
                        'source' => 'fallback',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Summarize ticket error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to summarize ticket',
            ], 500);
        }
    }

    /**
     * Generate knowledge base article
     */
    public function generateKBArticle(Request $request)
    {
        try {
            $this->validate($request, [
                'topic' => 'required|string',
                'common_issues' => 'array',
                'solutions' => 'array',
            ]);

            $aiEnabled = config('ai.providers.openai.enabled', false);

            if ($aiEnabled && !empty(config('ai.providers.openai.api_key'))) {
                $provider = new OpenAIProvider();

                $result = $provider->generateKBArticle($request->all());

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'article' => '# Knowledge Base Article\n\nThis is a placeholder article. Enable AI to generate real articles.',
                        'source' => 'fallback',
                    ],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Generate KB article error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate KB article',
            ], 500);
        }
    }

    /**
     * Build auto-write prompt based on context
     */
    protected function buildAutoWritePrompt($context, $tone, $length): string
    {
        $prompt = "Generate a ";

        // Add tone
        $toneDescriptions = [
            'professional' => 'professional and courteous',
            'friendly' => 'warm and friendly',
            'empathetic' => 'empathetic and understanding',
            'formal' => 'formal and business-like',
        ];
        $prompt .= ($toneDescriptions[$tone] ?? 'professional') . ' ';

        // Add length
        $lengthDescriptions = [
            'short' => 'brief (2-3 sentences)',
            'medium' => 'moderate length (4-6 sentences)',
            'long' => 'detailed (7-10 sentences)',
        ];
        $prompt .= $lengthDescriptions[$length] ?? 'moderate length';

        $prompt .= " response for a customer support ticket";

        if (!empty($context)) {
            $prompt .= " with the following context:\n\n" . $context;
        }

        return $prompt;
    }

    /**
     * Get fallback response when AI is not available
     */
    protected function getFallbackResponse($tone = 'professional', $length = 'medium')
    {
        $responses = [
            'professional' => [
                'short' => 'Thank you for reaching out to our support team. We have received your inquiry and will review it shortly. We appreciate your patience.',
                'medium' => 'Thank you for contacting our support team. We have received your inquiry and our team is currently reviewing the details you provided. We understand the importance of resolving this matter promptly and will get back to you with a comprehensive response as soon as possible. We appreciate your patience and understanding.',
                'long' => 'Thank you for reaching out to our support team regarding your inquiry. We have received your message and want to assure you that we are taking your concern seriously. Our dedicated support specialists are currently reviewing all the details you provided to ensure we can offer you the most accurate and helpful solution. We understand that timely resolution is important to you, and we are committed to addressing your needs as quickly as possible. You can expect to hear back from us within the next 24-48 hours. In the meantime, if you have any additional information that might help us serve you better, please feel free to reply to this message. We appreciate your patience and look forward to resolving this matter to your satisfaction.',
            ],
            'friendly' => [
                'short' => 'Hi there! Thanks so much for getting in touch with us. We\'ve got your message and we\'re on it! We\'ll get back to you very soon.',
                'medium' => 'Hey! Thanks for reaching out to us - we really appreciate you taking the time to contact our support team. We\'ve received your message and our team is already looking into it. We know your time is valuable, so we\'re working to get you a helpful response as quickly as we can. Hang tight, and we\'ll be in touch soon!',
                'long' => 'Hello! Thank you so much for getting in touch with our support team - we really value hearing from our customers. We want you to know that we\'ve received your message and our team is already diving into the details to help you out. We completely understand how important it is to get this sorted out quickly, and we\'re committed to providing you with a thorough and helpful response. You can expect to hear back from us within the next day or two at the latest. If anything else comes to mind that you think might be helpful for us to know, don\'t hesitate to send it our way - we\'re here to help! Thanks again for your patience, and we\'re looking forward to getting this resolved for you.',
            ],
            'empathetic' => [
                'short' => 'Thank you for reaching out to us. We understand this situation may be frustrating, and we\'re here to help. Our team is reviewing your concern and will respond soon.',
                'medium' => 'Thank you for contacting us about this matter. We truly understand how concerning and frustrating this situation must be for you, and we want you to know that we\'re here to support you. Our team has received your message and is carefully reviewing everything you\'ve shared with us. We\'re committed to finding a solution that works for you, and we\'ll be in touch with a detailed response as soon as possible. Thank you for your patience and for giving us the opportunity to help.',
                'long' => 'Thank you so much for taking the time to reach out to us about this issue. We genuinely understand how frustrating and concerning this situation must be for you, and we want you to know that your feelings are completely valid. Please know that we\'re here to support you every step of the way. Our support team has received your message and is giving it the careful attention it deserves. We recognize the impact this has had on you, and we\'re fully committed to working with you to find a resolution that addresses your needs. You can expect to hear back from us within the next 24-48 hours with a comprehensive response. In the meantime, if you think of anything else that might help us better understand your situation, please don\'t hesitate to share it with us. We\'re in your corner and we\'re going to work together to get this sorted out. Thank you for your patience and for trusting us to help you through this.',
            ],
            'formal' => [
                'short' => 'Dear Customer, we acknowledge receipt of your inquiry. Your matter is currently under review by our support department. You will receive a formal response shortly.',
                'medium' => 'Dear Valued Customer, we acknowledge receipt of your inquiry dated today. Your matter has been assigned to our support department for thorough review and analysis. We are committed to providing you with a comprehensive and accurate response. You may expect to receive our formal reply within the next business day. We thank you for your patience and for choosing our services.',
                'long' => 'Dear Valued Customer, we wish to acknowledge receipt of your inquiry submitted to our support department. Please be assured that your matter has been duly noted and assigned to the appropriate team for comprehensive review and analysis. We understand the importance of this issue to you and wish to assure you that we are committed to providing a thorough and professional response. Our support specialists are currently examining all relevant details to ensure we can offer you the most accurate and effective solution. You may expect to receive our formal response within the next 24 to 48 business hours. Should you have any additional information or documentation that you believe may assist us in addressing your inquiry, please do not hesitate to provide it at your earliest convenience. We thank you for your patience and understanding, and we appreciate the opportunity to be of service. We remain committed to maintaining the highest standards of customer support and look forward to resolving this matter to your complete satisfaction.',
            ],
        ];

        $text = $responses[$tone][$length] ?? $responses['professional']['medium'];

        return response()->json([
            'success' => true,
            'data' => [
                'text' => $text,
                'confidence' => 1.0,
                'source' => 'fallback',
                'provider' => 'none',
                'message' => 'AI is not enabled. Using fallback text. To enable AI, add your API key to the environment configuration.',
            ],
        ]);
    }

    /**
     * Process batch AI requests
     */
    public function processBatch(Request $request)
    {
        try {
            $this->validate($request, [
                'operations' => 'required|array',
            ]);

            // This would process multiple AI operations in batch
            // For now, return a simple response

            return response()->json([
                'success' => true,
                'message' => 'Batch processing is not yet implemented',
            ]);

        } catch (\Exception $e) {
            Log::error('Batch processing error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process batch',
            ], 500);
        }
    }

    /**
     * Improve existing KB article
     */
    public function improveKBArticle(Request $request)
    {
        try {
            $this->validate($request, [
                'article_content' => 'required|string',
                'improvements' => 'array',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KB article improvement is not yet implemented',
            ]);

        } catch (\Exception $e) {
            Log::error('Improve KB article error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to improve KB article',
            ], 500);
        }
    }

    /**
     * Suggest related KB articles
     */
    public function suggestRelatedArticles(Request $request)
    {
        try {
            $this->validate($request, [
                'ticket_content' => 'required|string',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'articles' => [],
                    'source' => 'fallback',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Suggest related articles error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to suggest related articles',
            ], 500);
        }
    }
}
