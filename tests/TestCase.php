<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use LogicException;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if (app()->environment('testing')
                && strtolower((string) $event->connection->getDatabaseName()) === 'zolm') {
                throw new RuntimeException(
                    'Testler ana `zolm` veritabanına bağlanamaz. Ayrı bir test veritabanı veya SQLite kullanın.'
                );
            }
        });

        $reflection = new \ReflectionClass(Model::class);
        if ($reflection->hasProperty('guardableColumns')) {
            $property = $reflection->getProperty('guardableColumns');
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
    }

    protected function mysqlTestDatabaseName(): string
    {
        $database = trim((string) (getenv('DB_TEST_DATABASE') ?: 'testing'));

        if ($database === '' || strtolower($database) === 'zolm') {
            throw new LogicException('DB_TEST_DATABASE güvenli ve ayrı bir test veritabanını göstermelidir.');
        }

        return $database;
    }
}
