<?php

namespace Tests\Unit;

use App\Models\TrendyolBoosterTrendKeyword;
use App\Services\Marketplace\TrendyolBoosterTrendKeywordService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TrendyolBoosterKeywordOpportunityServiceTest extends TestCase
{
    public function test_it_prioritizes_rising_low_competition_terms(): void
    {
        $rows = new Collection([
            new TrendyolBoosterTrendKeyword(['keyword' => 'oyuncu masası', 'signal_score' => 55, 'competition_level' => 'low', 'trend_direction' => 'rising']),
            new TrendyolBoosterTrendKeyword(['keyword' => 'çalışma masası', 'signal_score' => 65, 'competition_level' => 'high', 'trend_direction' => 'stable']),
            new TrendyolBoosterTrendKeyword(['keyword' => 'puf', 'signal_score' => 10, 'competition_level' => 'low', 'trend_direction' => 'new']),
        ]);

        $result = app(TrendyolBoosterTrendKeywordService::class)->opportunityPlaybook($rows);

        $this->assertCount(2, $result);
        $this->assertSame('oyuncu masası', $result[0]['keyword']);
        $this->assertSame('Başlık + reklam testi', $result[0]['action']);
        $this->assertGreaterThan($result[1]['opportunity_score'], $result[0]['opportunity_score']);
    }
}
