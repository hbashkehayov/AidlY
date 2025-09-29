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
        'service' => 'AidlY Auth Service',
        'version' => '1.0.0',
        'framework' => $router->app->version()
    ]);
});

// Health check endpoint
$router->get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'auth-service',
        'timestamp' => \Illuminate\Support\Carbon::now()->toIso8601String()
    ]);
});

// Authentication routes (public)
$router->group(['prefix' => 'api/v1/auth'], function () use ($router) {
    // Public auth routes
    $router->post('register', 'AuthController@register');
    $router->post('login', 'AuthController@login');
    $router->post('refresh', 'AuthController@refresh');
    $router->post('forgot-password', 'AuthController@forgotPassword');
    $router->post('reset-password', 'AuthController@resetPassword');

    // Protected auth routes
    $router->group(['middleware' => 'jwt'], function () use ($router) {
        $router->post('logout', 'AuthController@logout');
        $router->get('me', 'AuthController@me');
        $router->post('change-password', 'AuthController@changePassword');
    });
});

// User management routes (admin only)
$router->group(['prefix' => 'api/v1/users', 'middleware' => ['jwt', 'role:admin,supervisor']], function () use ($router) {
    $router->get('/', 'UserController@index');
    $router->get('/{id}', 'UserController@show');
    $router->post('/', 'UserController@create');
    $router->put('/{id}', 'UserController@update');
    $router->delete('/{id}', 'UserController@delete');
    $router->post('/{id}/activate', 'UserController@activate');
    $router->post('/{id}/deactivate', 'UserController@deactivate');
    $router->post('/{id}/unlock', 'UserController@unlock');
});

// Role and permission management (admin only)
$router->group(['prefix' => 'api/v1/roles', 'middleware' => ['jwt', 'role:admin']], function () use ($router) {
    $router->get('/', 'RoleController@index');
    $router->get('/{role}/permissions', 'RoleController@getPermissions');
    $router->post('/{role}/permissions', 'RoleController@assignPermissions');
    $router->delete('/{role}/permissions/{permissionId}', 'RoleController@removePermission');
});

$router->group(['prefix' => 'api/v1/permissions', 'middleware' => ['jwt', 'role:admin']], function () use ($router) {
    $router->get('/', 'PermissionController@index');
    $router->post('/', 'PermissionController@create');
    $router->put('/{id}', 'PermissionController@update');
    $router->delete('/{id}', 'PermissionController@delete');
});

// Session management
$router->group(['prefix' => 'api/v1/sessions', 'middleware' => 'jwt'], function () use ($router) {
    $router->get('/', 'SessionController@index');
    $router->delete('/all', 'SessionController@destroyAll');
    $router->delete('/{id}', 'SessionController@destroy');
});

// Two-factor authentication
$router->group(['prefix' => 'api/v1/2fa', 'middleware' => 'jwt'], function () use ($router) {
    $router->post('enable', 'TwoFactorController@enable');
    $router->post('disable', 'TwoFactorController@disable');
    $router->post('verify', 'TwoFactorController@verify');
    $router->get('recovery-codes', 'TwoFactorController@getRecoveryCodes');
    $router->post('recovery-codes/regenerate', 'TwoFactorController@regenerateRecoveryCodes');
});