<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

trait RefreshHrDatabase
{
    use RefreshDatabase;

    /**
     * Faz rollback testleri, hedef faza ulaşmak için migration adımlarını
     * bilinçli olarak tek tek sayıyor. Faz 8 sonrasında gelen puantaj kontrol
     * migration'larını önce ayırarak bu tarihsel sınırı sabit tutarız.
     */
    protected function setUpRefreshHrDatabase(): void
    {
        $isPhaseRollbackTest = str_contains(class_basename(static::class), 'Phase')
            && str_contains($this->name(), 'rollback');

        if ($isPhaseRollbackTest) {
            Artisan::call('migrate:rollback', ['--step' => 7]);
        }
    }
}
