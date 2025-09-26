<?php

namespace Tests;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Nilit\LaraBoilerCore\BoilerplateServiceProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(BoilerplateServiceProvider::class);
        
        // Test-Routen frühzeitig definieren
        Route::middleware('web')->group(function () {
            Route::get('/login2', fn() => 'ok')->name('login2');
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        // Prüfen, in welcher Testsuite wir sind
        if ($this->isMysqlSuite()) {
            config()->set('database.default', 'mysql');
            config()->set('database.connections.mysql.host', env('MYSQL_CENTRAL_HOST'));
            config()->set('database.connections.mysql.port', env('MYSQL_CENTRAL_PORT'));
            config()->set('database.connections.mysql.database', env('MYSQL_CENTRAL_DATABASE'));
            config()->set('database.connections.mysql.username', env('MYSQL_CENTRAL_USERNAME'));
            config()->set('database.connections.mysql.password', env('MYSQL_CENTRAL_PASSWORD'));
        } else {
            config()->set('database.default', 'sqlite');
            config()->set('database.connections.sqlite.database', ':memory:');
        }
    }

    protected function isMysqlSuite(): bool
    {
        return str_contains($_SERVER['argv'][1] ?? '', 'Mysql');
    }
/*
    protected function getPackageProviders($app)
    {
        return [
            BoilerplateServiceProvider::class,
        ];
    }*/





}