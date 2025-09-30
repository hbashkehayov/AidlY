<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| AidlY Ticket Service Routes
|--------------------------------------------------------------------------
|
| API routes for the ticket management service
|
*/

// Health check endpoint
$router->get('/', function () use ($router) {
    return response()->json([
        'service' => 'AidlY Ticket Service',
        'version' => $router->app->version(),
        'status' => 'healthy',
        'timestamp' => \Illuminate\Support\Carbon::now()->toISOString()
    ]);
});

$router->get('/health', function () use ($router) {
    return response()->json([
        'status' => 'healthy',
        'service' => 'ticket-service',
        'version' => '1.0.0',
        'timestamp' => \Illuminate\Support\Carbon::now()->toISOString(),
        'checks' => [
            'database' => 'ok', // TODO: Add actual DB health check
            'cache' => 'ok',    // TODO: Add actual cache health check
        ]
    ]);
});

// API v1 routes
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Public routes (no authentication required)
    $router->group(['prefix' => 'public'], function () use ($router) {

        // Categories (public read-only access)
        $router->get('categories', 'CategoryController@index');
        $router->get('categories/tree', 'CategoryController@tree');
        $router->get('categories/{id}', 'CategoryController@show');

        // Temporary public route for marking comments as read (for testing)
        $router->post('comments/{id}/read', 'CommentController@markRead');

        // Temporary public ticket access for debugging
        $router->get('tickets', 'TicketController@index');

        // Get ticket by ticket number (for email threading) - MUST come before {id} route
        $router->get('tickets/by-number/{ticketNumber}', 'TicketController@getByNumber');

        // Get ticket by message ID (for email threading) - MUST come before {id} route
        $router->get('tickets/by-message-id', 'TicketController@getByMessageId');

        // Get specific ticket by ID
        $router->get('tickets/{id}', 'TicketController@show');

        // Temporary public update for development
        $router->put('tickets/{id}', 'TicketController@update');
        $router->post('tickets/{id}/assign', 'TicketController@assign');
        $router->delete('tickets/{id}', 'TicketController@destroy');
        $router->post('tickets/{id}/comments', 'TicketController@addComment');

        // Public ticket creation for email service integration
        $router->post('tickets', 'TicketController@store');

        // Ticket statistics by clients (for client service) - Public for inter-service communication
        $router->get('tickets/stats/by-clients', 'TicketController@getTicketStatsByClients');

        // Overall ticket count - Public for inter-service communication
        $router->get('tickets/stats/total', 'TicketController@getTotalTicketCount');

        // Delete all tickets for a client (for client service) - Public for inter-service communication
        $router->delete('clients/{clientId}/tickets', 'TicketController@deleteClientTickets');

    });

    // Protected routes (authentication required)
    $router->group(['middleware' => 'auth'], function () use ($router) {

        // Statistics routes
        $router->group(['prefix' => 'stats'], function () use ($router) {
            $router->get('dashboard', 'StatsController@getDashboardStats');
            $router->get('trends', 'StatsController@getTicketTrends');
            $router->get('recent', 'StatsController@getRecentTickets');
            $router->get('notification-counts', 'StatsController@getNotificationCounts');
        });

        // Ticket management routes
        $router->group(['prefix' => 'tickets'], function () use ($router) {
            $router->get('/', 'TicketController@index');
            $router->post('/', 'TicketController@store');
            $router->get('stats', 'TicketController@stats');
            $router->get('{id}', 'TicketController@show');
            $router->put('{id}', 'TicketController@update');
            $router->delete('{id}', 'TicketController@destroy');

            // Ticket actions
            $router->post('{id}/assign', 'TicketController@assign');
            $router->post('{id}/comments', 'TicketController@addComment');
            $router->get('{id}/comments', 'CommentController@index'); // Get comments for specific ticket
            $router->get('{id}/history', 'TicketController@history');
        });

        // Assignment management routes
        $router->group(['prefix' => 'assignments'], function () use ($router) {
            $router->get('agents/workload', 'TicketController@getAgentWorkloads');
            $router->get('agents/available', 'TicketController@getAvailableAgents');
            $router->post('auto-assign', 'TicketController@bulkAutoAssign');
            $router->post('rebalance', 'TicketController@rebalanceWorkload');
        });

        // Comment management routes (global comments access)
        $router->group(['prefix' => 'comments'], function () use ($router) {
            $router->get('/', 'CommentController@index');
            $router->post('/', 'CommentController@store');
            $router->get('{id}', 'CommentController@show');
            $router->put('{id}', 'CommentController@update');
            $router->delete('{id}', 'CommentController@destroy');
            $router->post('{id}/read', 'CommentController@markRead');
        });

        // Category management routes (admin access)
        $router->group(['prefix' => 'categories', 'middleware' => 'role:admin'], function () use ($router) {
            $router->post('/', 'CategoryController@store');
            $router->put('{id}', 'CategoryController@update');
            $router->delete('{id}', 'CategoryController@destroy');
        });

        // Protected category access
        $router->get('categories', 'CategoryController@index');
        $router->get('categories/tree', 'CategoryController@tree');
        $router->get('categories/{id}', 'CategoryController@show');

    });

});
