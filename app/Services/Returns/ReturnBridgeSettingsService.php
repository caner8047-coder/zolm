<?php

namespace App\Services\Returns;

use App\Models\ReturnBridgeSetting;
use Illuminate\Support\Facades\Cache;

class ReturnBridgeSettingsService
{
    protected const CACHE_KEY = 'return_bridge_settings_resolved';

    /**
     * @return array<string, mixed>
     */
    public function resolved(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $record = ReturnBridgeSetting::query()->with('systemUser:id,name')->first();
            $defaults = [
                'enabled' => (bool) config('returns.whatsapp_bridge_enabled', false),
                'system_user_id' => (int) config('returns.whatsapp_bridge_system_user_id', 0),
                'verify_token' => (string) config('returns.whatsapp_verify_token', ''),
                'access_token' => (string) config('returns.whatsapp_access_token', ''),
                'app_secret' => (string) config('returns.whatsapp_app_secret', ''),
                'graph_base_url' => (string) config('returns.whatsapp_graph_base_url', 'https://graph.facebook.com'),
                'graph_version' => (string) config('returns.whatsapp_graph_version', 'v23.0'),
                'message_window_minutes' => (int) config('returns.whatsapp_message_window_minutes', 8),
                'system_user_name' => null,
                'source' => 'env',
            ];

            if (!$record) {
                return $defaults;
            }

            $resolved = $defaults;
            $resolved['source'] = 'database';

            if ($record->whatsapp_bridge_enabled !== null) {
                $resolved['enabled'] = (bool) $record->whatsapp_bridge_enabled;
            }

            if ($record->system_user_id) {
                $resolved['system_user_id'] = (int) $record->system_user_id;
                $resolved['system_user_name'] = $record->systemUser?->name;
            }

            foreach (['verify_token', 'access_token', 'app_secret', 'graph_base_url', 'graph_version'] as $field) {
                $value = $record->{$field};

                if (is_string($value) && trim($value) !== '') {
                    $resolved[$field] = trim($value);
                }
            }

            if ($record->message_window_minutes !== null) {
                $resolved['message_window_minutes'] = max(1, (int) $record->message_window_minutes);
            }

            return $resolved;
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $resolved = $this->resolved();

        return $resolved[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): ReturnBridgeSetting
    {
        $record = ReturnBridgeSetting::query()->first() ?: new ReturnBridgeSetting();

        $record->fill([
            'whatsapp_bridge_enabled' => array_key_exists('enabled', $data) ? (bool) $data['enabled'] : $record->whatsapp_bridge_enabled,
            'system_user_id' => array_key_exists('system_user_id', $data) ? ($data['system_user_id'] ?: null) : $record->system_user_id,
            'verify_token' => array_key_exists('verify_token', $data) ? $this->nullableTrim($data['verify_token']) : $record->verify_token,
            'access_token' => array_key_exists('access_token', $data) ? $this->nullableTrim($data['access_token']) : $record->access_token,
            'app_secret' => array_key_exists('app_secret', $data) ? $this->nullableTrim($data['app_secret']) : $record->app_secret,
            'graph_base_url' => array_key_exists('graph_base_url', $data) ? $this->nullableTrim($data['graph_base_url']) : $record->graph_base_url,
            'graph_version' => array_key_exists('graph_version', $data) ? $this->nullableTrim($data['graph_version']) : $record->graph_version,
            'message_window_minutes' => array_key_exists('message_window_minutes', $data) ? max(1, (int) $data['message_window_minutes']) : $record->message_window_minutes,
        ]);

        $record->save();
        $this->forgetCache();

        return $record->fresh('systemUser');
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function nullableTrim(mixed $value): ?string
    {
        $resolved = trim((string) $value);

        return $resolved !== '' ? $resolved : null;
    }
}
