<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Compose injects the app database name; tests must never migrate it.
        putenv('DB_CONNECTION=pgsql');
        putenv('DB_DATABASE=partshelf_test');
        putenv('DB_URL=');
        $_ENV['DB_CONNECTION'] = 'pgsql';
        $_ENV['DB_DATABASE'] = 'partshelf_test';
        $_ENV['DB_URL'] = '';
        $_SERVER['DB_CONNECTION'] = 'pgsql';
        $_SERVER['DB_DATABASE'] = 'partshelf_test';
        $_SERVER['DB_URL'] = '';

        $app = parent::createApplication();
        $connection = $app['db']->connection();

        if ($connection->getName() !== 'pgsql' || $connection->getDatabaseName() !== 'partshelf_test') {
            throw new \RuntimeException('Tests may only run against the isolated partshelf_test PostgreSQL database.');
        }

        return $app;
    }
}
