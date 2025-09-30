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
        'service' => 'AidlY Analytics Service',
        'version' => $router->app->version(),
        'status' => 'running',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

$router->get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => 'connected'
    ]);
});

// API v1 routes
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Dashboard analytics
    $router->group(['prefix' => 'dashboard'], function () use ($router) {
        $router->get('/stats', 'DashboardController@stats');
        $router->get('/trends', 'DashboardController@ticketTrends');
        $router->get('/activity', 'DashboardController@activityFeed');
        $router->get('/sla-compliance', 'DashboardController@slaCompliance');
        $router->get('/agent-performance', 'DashboardController@agentPerformance');
    });

    // Reports
    $router->group(['prefix' => 'reports'], function () use ($router) {
        $router->get('/', 'ReportController@index');
        $router->post('/', 'ReportController@store');
        $router->get('/{id}', 'ReportController@show');
        $router->put('/{id}', 'ReportController@update');
        $router->delete('/{id}', 'ReportController@destroy');
        $router->post('/{id}/execute', 'ReportController@execute');
        $router->post('/{id}/schedule', 'ReportController@schedule');
        $router->get('/{id}/executions', 'ReportController@executions');
    });

    // Exports
    $router->group(['prefix' => 'exports'], function () use ($router) {
        $router->post('/reports', 'ExportController@reports');
        $router->post('/tickets', 'ExportController@tickets');
        $router->post('/agents', 'ExportController@agents');
        $router->post('/custom', 'ExportController@custom');
        $router->get('/{id}/download', 'ExportController@download');
        $router->get('/{id}/status', 'ExportController@status');
    });

    // Metrics aggregation (for background jobs)
    $router->group(['prefix' => 'metrics'], function () use ($router) {
        $router->post('/aggregate/daily', 'MetricsController@aggregateDaily');
        $router->post('/aggregate/agent/{agentId}', 'MetricsController@aggregateAgent');
        $router->get('/ticket-metrics', 'MetricsController@ticketMetrics');
        $router->get('/agent-metrics', 'MetricsController@agentMetrics');
        $router->get('/client-metrics', 'MetricsController@clientMetrics');
    });

    // Analytics events (for tracking user actions)
    $router->group(['prefix' => 'events'], function () use ($router) {
        $router->post('/', 'EventController@track');
        $router->post('/batch', 'EventController@trackBatch');
        $router->get('/types', 'EventController@eventTypes');
        $router->get('/statistics', 'EventController@statistics');
    });

    // Real-time analytics
    $router->group(['prefix' => 'realtime'], function () use ($router) {
        $router->get('/current-stats', 'RealtimeController@currentStats');
        $router->get('/active-agents', 'RealtimeController@activeAgents');
        $router->get('/queue-status', 'RealtimeController@queueStatus');
    });
});
