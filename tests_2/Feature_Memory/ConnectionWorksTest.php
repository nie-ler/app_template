<?php


use Tests\Feature_Memory;
use Tests\TestCase;

class LoginRouteTest extends TestCase
{
protected function setUp(): void
{
    parent::setUp();
    $this->app->register(\Nilit\LaraBoilerCore\BoilerplateServiceProvider::class);
    $this->withoutMiddleware();
}

public function test_package_login_route()
{
    $route = \Route::getRoutes()->getByName('login');
    dump($route->uri(), $route->middleware(), $route->domain());
    //dd(\Route::getRoutes()->match(\Illuminate\Http\Request::create('/login')));

    // Pfad genau wie in Route
    $response = $this->get('/login'); // oder '/auth/login', je nach URI
    $response->assertStatus(200);
}
}