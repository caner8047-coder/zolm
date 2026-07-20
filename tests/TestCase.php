<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $reflection = new \ReflectionClass(\Illuminate\Database\Eloquent\Model::class);
        if ($reflection->hasProperty('guardableColumns')) {
            $property = $reflection->getProperty('guardableColumns');
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
    }
}
