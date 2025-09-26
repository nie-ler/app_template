<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function getEnvironmentSetUp($app)
    {
        /*
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
        }*/
    }

    protected function isMysqlSuite(): bool
    {
        return !str_contains($_SERVER['argv'][1] ?? '', 'Memory');
    }
}
