<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Tools\AiTool;
use App\Services\WhatsApp\Tools\ProductLookupTool;
use App\Services\WhatsApp\Tools\StockAvailabilityTool;
use App\Services\WhatsApp\Tools\OrderStatusTool;
use App\Services\WhatsApp\Tools\ReturnStatusTool;
use App\Services\WhatsApp\Tools\PolicyKnowledgeTool;
use App\Services\WhatsApp\Tools\HumanHandoffTool;

class ToolRouter
{
    private array $tools = [];

    public function __construct()
    {
        $this->register(new ProductLookupTool());
        $this->register(new StockAvailabilityTool());
        $this->register(new OrderStatusTool());
        $this->register(new ReturnStatusTool());
        $this->register(new PolicyKnowledgeTool());
        $this->register(new HumanHandoffTool());
    }

    private function register(AiTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function getTool(string $name): ?AiTool
    {
        return $this->tools[$name] ?? null;
    }

    public function getAvailableTools(): array
    {
        return array_map(fn (AiTool $t) => [
            'name' => $t->name(),
            'description' => $t->description(),
        ], array_values($this->tools));
    }

    public function execute(string $toolName, array $params, int $storeId, ?int $contactId = null): array
    {
        $tool = $this->getTool($toolName);
        if (!$tool) {
            return ['error' => "Tool '{$toolName}' bulunamadı."];
        }

        $startTime = microtime(true);
        $result = $tool->execute($params, $storeId, $contactId);
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        $result['execution_time_ms'] = $executionTime;

        return $result;
    }
}
