<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

// Health check endpoint
$router->get('/', function () use ($router) {
    return response()->json([
        'service' => 'AidlY Email Service',
        'version' => $router->app->version(),
        'status' => 'healthy',
        'timestamp' => date('c')
    ]);
});

$router->get('/health', function () use ($router) {
    return response()->json([
        'service' => 'email-service',
        'status' => 'healthy',
        'version' => $router->app->version(),
        'timestamp' => date('c')
    ]);
});

// API Routes
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Email processing endpoints
    $router->group(['prefix' => 'emails'], function () use ($router) {
        $router->get('/', 'EmailController@queue');
        $router->get('/stats', 'EmailController@stats');
        $router->get('/{id}', 'EmailController@queueItem');

        $router->post('/fetch', 'EmailController@fetchAll');
        $router->post('/process', 'EmailController@processToTickets');
        $router->post('/send', 'EmailController@send');
        $router->post('/send-template', 'EmailController@sendTemplate');
        $router->post('/send-notification', 'EmailController@sendTicketNotification');
        $router->post('/{id}/retry', 'EmailController@retryEmail');
    });

    // Email accounts management
    $router->group(['prefix' => 'accounts'], function () use ($router) {
        $router->get('/', 'EmailAccountController@index');
        $router->get('/agents', 'EmailAccountController@agentAccounts');
        $router->get('/{id}', 'EmailAccountController@show');
        $router->post('/', 'EmailAccountController@store');
        $router->put('/{id}', 'EmailAccountController@update');
        $router->put('/user/{userId}', 'EmailAccountController@updateByUser');
        $router->put('/user/{userId}/disable', 'EmailAccountController@disableByUser');
        $router->delete('/{id}', 'EmailAccountController@destroy');

        // Account testing and operations
        $router->post('/{id}/test-imap', 'EmailAccountController@testImap');
        $router->post('/{id}/test-smtp', 'EmailAccountController@testSmtp');
        $router->get('/{id}/stats', 'EmailAccountController@stats');
        $router->post('/{id}/fetch', 'EmailAccountController@fetchEmails');
    });

    // Email templates management
    $router->group(['prefix' => 'templates'], function () use ($router) {
        $router->get('/', 'EmailTemplateController@index');
        $router->get('/categories', 'EmailTemplateController@categories');
        $router->post('/create-defaults', 'EmailTemplateController@createDefaults');

        $router->get('/{id}', 'EmailTemplateController@show');
        $router->post('/', 'EmailTemplateController@store');
        $router->put('/{id}', 'EmailTemplateController@update');
        $router->delete('/{id}', 'EmailTemplateController@destroy');

        $router->get('/{id}/variables', 'EmailTemplateController@variables');
        $router->post('/{id}/preview', 'EmailTemplateController@preview');
    });

    // Webhook endpoints for ticket system integration
    $router->group(['prefix' => 'webhooks'], function () use ($router) {
        $router->get('/health', 'WebhookController@health');
        $router->post('/ticket/comment', 'WebhookController@handleTicketComment');
        $router->post('/ticket/status-change', 'WebhookController@handleTicketStatusChange');
    });

    // Gmail setup helper endpoints
    $router->group(['prefix' => 'gmail'], function () use ($router) {
        $router->get('/instructions', 'GmailSetupController@instructions');
        $router->get('/recommended-settings', 'GmailSetupController@recommendedSettings');
        $router->post('/quick-setup', 'GmailSetupController@quickSetup');
        $router->post('/test-connection', 'GmailSetupController@testConnection');
        $router->post('/test-fetch', 'GmailSetupController@testFetch');
    });
});
