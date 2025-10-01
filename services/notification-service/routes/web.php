<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

$router->get('/', function () use ($router) {
    return response()->json([
        'service' => 'AidlY Notification Service',
        'version' => $router->app->version(),
        'status' => 'healthy',
        'timestamp' => date('c')
    ]);
});

// Health check endpoint
$router->get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'notification-service',
        'timestamp' => date('c')
    ]);
});

// API v1 Routes
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Notifications endpoints
    $router->group(['prefix' => 'notifications'], function () use ($router) {
        // Public endpoints (require API key or basic auth)
        $router->post('/', ['middleware' => 'auth', 'uses' => 'NotificationController@store']);
        $router->post('/bulk', ['middleware' => 'auth', 'uses' => 'NotificationController@sendBulk']);

        // User-specific endpoints (require JWT auth)
        $router->get('/', ['middleware' => 'auth', 'uses' => 'NotificationController@index']);
        $router->get('/unread', ['middleware' => 'auth', 'uses' => 'NotificationController@unread']);
        $router->get('/stats', ['middleware' => 'auth', 'uses' => 'NotificationController@stats']);
        $router->post('/{id}/read', ['middleware' => 'auth', 'uses' => 'NotificationController@markAsRead']);
        $router->post('/{id}/unread', ['middleware' => 'auth', 'uses' => 'NotificationController@markAsUnread']);
        $router->post('/mark-read', ['middleware' => 'auth', 'uses' => 'NotificationController@markMultipleAsRead']);
        $router->delete('/{id}', ['middleware' => 'auth', 'uses' => 'NotificationController@destroy']);
    });

    // Notification preferences endpoints
    $router->group(['prefix' => 'preferences', 'middleware' => 'auth'], function () use ($router) {
        $router->get('/', 'PreferenceController@show');
        $router->put('/', 'PreferenceController@update');
        $router->post('/events/{event}', 'PreferenceController@updateEventPreference');
        $router->post('/dnd', 'PreferenceController@toggleDND');
        $router->post('/digest', 'PreferenceController@updateDigestSettings');
        $router->post('/quiet-hours', 'PreferenceController@updateQuietHours');
    });

    // WebSocket authentication
    $router->post('/websocket/auth', ['middleware' => 'auth', 'uses' => 'NotificationController@authenticateWebSocket']);

    // Template management (admin only)
    $router->group(['prefix' => 'templates', 'middleware' => ['auth', 'admin']], function () use ($router) {
        $router->get('/', 'TemplateController@index');
        $router->get('/{id}', 'TemplateController@show');
        $router->post('/', 'TemplateController@store');
        $router->put('/{id}', 'TemplateController@update');
        $router->delete('/{id}', 'TemplateController@destroy');
        $router->post('/seed-defaults', 'TemplateController@seedDefaults');
    });

    // Queue processing (internal use)
    $router->group(['prefix' => 'queue'], function () use ($router) {
        $router->post('/process', 'QueueController@processNotifications');
        $router->post('/send-digests', 'QueueController@sendDigests');
        $router->post('/retry-failed', 'QueueController@retryFailed');
        $router->get('/stats', 'QueueController@queueStats');
    });

    // Webhook endpoints for external triggers
    $router->group(['prefix' => 'webhooks'], function () use ($router) {
        $router->post('/ticket-created', 'WebhookController@ticketCreated');
        $router->post('/ticket-updated', 'WebhookController@ticketUpdated');
        $router->post('/ticket-assigned', 'WebhookController@ticketAssigned');
        $router->post('/comment-added', 'WebhookController@commentAdded');
        $router->post('/sla-breach', 'WebhookController@slaBreach');
    });
});
