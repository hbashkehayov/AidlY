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

$router->get('/', function () use ($router) {
    return response()->json([
        'service' => 'AidlY Client Service',
        'version' => $router->app->version(),
        'status' => 'operational'
    ]);
});

// Health check endpoint
$router->get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'service' => 'client-service'
    ]);
});

// API Version 1 Routes
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Client routes
    $router->get('/clients', 'ClientController@index');
    $router->post('/clients', 'ClientController@store');
    $router->get('/clients/{id}', 'ClientController@show');
    $router->put('/clients/{id}', 'ClientController@update');
    $router->delete('/clients/{id}', 'ClientController@destroy');

    // Client actions
    $router->post('/clients/{id}/block', 'ClientController@toggleBlock');
    $router->post('/clients/{id}/vip', 'ClientController@toggleVip');
    $router->post('/clients/{id}/tags', 'ClientController@addTag');
    $router->delete('/clients/{id}/tags', 'ClientController@removeTag');

    // Client tickets (integration with ticket service)
    $router->get('/clients/{id}/tickets', 'ClientController@tickets');

    // Client notes routes
    $router->get('/clients/{clientId}/notes', 'ClientNoteController@index');
    $router->post('/clients/{clientId}/notes', 'ClientNoteController@store');
    $router->get('/clients/{clientId}/notes/{noteId}', 'ClientNoteController@show');
    $router->put('/clients/{clientId}/notes/{noteId}', 'ClientNoteController@update');
    $router->delete('/clients/{clientId}/notes/{noteId}', 'ClientNoteController@destroy');
    $router->post('/clients/{clientId}/notes/{noteId}/pin', 'ClientNoteController@togglePin');

    // Client merge routes
    $router->get('/clients/{clientId}/merges', 'ClientMergeController@index');
    $router->post('/clients/merge', 'ClientMergeController@merge');
    $router->post('/clients/merge/preview', 'ClientMergeController@previewMerge');
});
