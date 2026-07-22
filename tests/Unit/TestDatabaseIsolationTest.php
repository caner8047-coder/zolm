<?php

namespace Tests\Unit;

use LogicException;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DB_TEST_DATABASE');

        parent::tearDown();
    }

    public function test_testing_database_is_the_safe_default(): void
    {
        putenv('DB_TEST_DATABASE');

        $this->assertSame('testing', $this->resolvedMysqlTestDatabaseName());
    }

    public function test_main_database_name_is_rejected(): void
    {
        putenv('DB_TEST_DATABASE=zolm');

        $this->expectException(LogicException::class);
        $this->resolvedMysqlTestDatabaseName();
    }

    private function resolvedMysqlTestDatabaseName(): string
    {
        return $this->mysqlTestDatabaseName();
    }
}
