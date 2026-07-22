<?php

namespace Tests;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if (app()->environment('testing') && $event->connection->getDatabaseName() === 'zolm') {
                throw new RuntimeException(
                    'Testler ana `zolm` veritabanına bağlanamaz. Ayrı bir test veritabanı veya SQLite kullanın.'
                );
            }
        });
    }
}
