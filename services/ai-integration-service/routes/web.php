<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| AidlY AI Integration Service Routes
|--------------------------------------------------------------------------
|
| API routes for the AI integration service with webhook support
|
*/

// Health check endpoint
$router->get('/', function () use ($router) {
    return response()->json([
        'service' => 'AidlY AI Integration Service',
        'version' => $router->app->version(),
        'status' => 'healthy',
        'timestamp' => \Illuminate\Support\Carbon::now()->toISOString()
    ]);
});

$router->get('/health', function () use ($router) {
    return response()->json([
        'status' => 'healthy',
        'service' => 'ai-integration-service',
        'version' => '1.0.0',
        'timestamp' => \Illuminate\Support\Carbon::now()->toISOString(),
        'checks' => [
            'database' => 'ok',
            'redis' => 'ok',
            'queue' => 'ok'
        ]
    ]);
});

// API v1 routes
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Webhook endpoints (public with signature verification)
    $router->group(['prefix' => 'webhooks', 'middleware' => 'webhook.verify'], function () use ($router) {

        // AI Provider Webhooks
        $router->post('openai', 'WebhookController@handleOpenAI');
        $router->post('anthropic', 'WebhookController@handleAnthropic');
        $router->post('gemini', 'WebhookController@handleGemini');
        $router->post('custom/{provider}', 'WebhookController@handleCustom');

        // Processing status callbacks
        $router->post('callback/{jobId}', 'WebhookController@handleCallback');
    });

    // AI Processing Requests (Public - accessible from other services)
    $router->group(['prefix' => 'process'], function () use ($router) {

        // Auto-write for rich text editor
        $router->post('auto-write', 'ProcessingController@autoWrite');

        // Ticket Processing
        $router->post('ticket/categorize', 'ProcessingController@categorizeTicket');
        $router->post('ticket/prioritize', 'ProcessingController@prioritizeTicket');
        $router->post('ticket/suggest-response', 'ProcessingController@suggestResponse');
        $router->post('ticket/analyze-sentiment', 'ProcessingController@analyzeSentiment');
        $router->post('ticket/extract-entities', 'ProcessingController@extractEntities');
        $router->post('ticket/summarize', 'ProcessingController@summarizeTicket');

        // Batch Processing
        $router->post('batch', 'ProcessingController@processBatch');

        // Knowledge Base
        $router->post('kb/generate-article', 'ProcessingController@generateKBArticle');
        $router->post('kb/improve-article', 'ProcessingController@improveKBArticle');
        $router->post('kb/suggest-articles', 'ProcessingController@suggestRelatedArticles');
    });

    // Protected routes (authentication required)
    $router->group(['middleware' => 'auth'], function () use ($router) {

        // AI Configuration Management
        $router->group(['prefix' => 'configurations'], function () use ($router) {
            $router->get('/', 'AIConfigurationController@index');
            $router->post('/', 'AIConfigurationController@store');
            $router->get('{id}', 'AIConfigurationController@show');
            $router->put('{id}', 'AIConfigurationController@update');
            $router->delete('{id}', 'AIConfigurationController@destroy');
            $router->post('{id}/test-connection', 'AIConfigurationController@testConnection');
        });

        // Queue Management
        $router->group(['prefix' => 'queue'], function () use ($router) {
            $router->get('/', 'QueueController@index');
            $router->get('stats', 'QueueController@stats');
            $router->get('{id}', 'QueueController@show');
            $router->post('{id}/retry', 'QueueController@retry');
            $router->post('{id}/cancel', 'QueueController@cancel');
            $router->delete('{id}', 'QueueController@destroy');
        });

        // Provider Management
        $router->group(['prefix' => 'providers'], function () use ($router) {
            $router->get('/', 'ProviderController@index');
            $router->get('{provider}/status', 'ProviderController@status');
            $router->get('{provider}/usage', 'ProviderController@usage');
            $router->get('{provider}/models', 'ProviderController@models');
            $router->post('{provider}/validate', 'ProviderController@validate');
        });

        // Monitoring & Analytics
        $router->group(['prefix' => 'monitoring'], function () use ($router) {
            $router->get('health', 'AIMonitoringController@health');
            $router->get('metrics', 'AIMonitoringController@metrics');
            $router->get('providers/status', 'AIMonitoringController@providerStatus');
            $router->get('queue/status', 'AIMonitoringController@queueStatus');
            $router->get('logs', 'AIMonitoringController@logs');
        });

        // Feature Flags
        $router->group(['prefix' => 'features'], function () use ($router) {
            $router->get('flags', 'AIConfigurationController@getFeatureFlags');
            $router->put('flags', 'AIConfigurationController@updateFeatureFlags');
        });
    });
});