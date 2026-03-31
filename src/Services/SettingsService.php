<?php

namespace Aotr\DynamicLevelHelper\Services;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Models\SettingAuditLog;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    protected const CACHE_PREFIX = 'settings';

    public function get($key, $default = null, $scope = null)
    {
        $settings = $this->loadMerged($scope);
        return data_get($settings, $key, $default);
    }

    public function set($key, $value, $scope = null)
    {
        Setting::updateOrCreate(
            [
                'key' => $key,
                'scope_type' => $this->resolveScopeType($scope),
                'scope_id' => $this->resolveScopeId($scope),
            ],
            ['value' => $value, 'type' => 'string', 'display_name' => ucfirst(str_replace('.', ' ', $key))]
        );

        $this->invalidate($scope);
    }

    public function all($scope = null)
    {
        return collect($this->loadMerged($scope))
            ->map(fn($s) => $s['casted_value'] ?? $s['value'])
            ->toArray();
    }

    public function group($group, $scope = null)
    {
        return collect($this->loadMerged($scope))
            ->filter(fn($s) => ($s['group'] ?? null) === $group)
            ->map(fn($s) => $s['casted_value'] ?? $s['value']);
    }

    protected function load($scope = null)
    {
        $cacheKey = $this->resolveCacheKey($scope);

        return Cache::rememberForever($cacheKey, function () use ($scope) {
            return Setting::query()
                ->forScope($scope)
                ->get()
                ->map(fn($s) => [
                    'key' => $s->key,
                    'group' => $s->group,
                    'value' => $s->value,
                    'casted_value' => $s->getCastedValue(),
                    'type' => $s->type,
                    'meta' => $s->meta,
                ])
                ->keyBy('key')
                ->toArray();
        });
    }

    protected function loadMerged($scope = null)
    {
        $global = $this->load(null);

        if ($scope) {
            $scoped = $this->load($scope);
            return array_replace_recursive($global, $scoped);
        }

        return $global;
    }

    protected function resolveCacheKey($scope = null)
    {
        if (is_null($scope)) {
            return self::CACHE_PREFIX . ':global';
        }

        if (is_string($scope)) {
            return self::CACHE_PREFIX . ':' . str_replace(':', ':', $scope);
        }

        $type = class_basename($scope);
        $id = $scope->id;
        return self::CACHE_PREFIX . ":{$type}:{$id}";
    }

    public function invalidate($scope = null)
    {
        $cacheKey = $this->resolveCacheKey($scope);
        Cache::forget($cacheKey);
    }

    protected function resolveScopeType($scope)
    {
        if (is_null($scope)) return null;
        if (is_string($scope)) {
            [$type] = explode(':', $scope);
            return $type;
        }
        return class_basename($scope);
    }

    protected function resolveScopeId($scope)
    {
        if (is_null($scope)) return null;
        if (is_string($scope)) {
            $parts = explode(':', $scope);
            return $parts[1] ?? null;
        }
        return $scope->id ?? null;
    }

    public function warm()
    {
        $this->load(null);
    }

    public function flush()
    {
        Cache::flush();
    }

    // ===== Audit/History Methods =====

    /**
     * Get audit log entries for a specific setting key.
     *
     * @param string $key
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getHistory($key, $limit = 50)
    {
        return SettingAuditLog::forKey($key)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get full audit log with optional filters.
     *
     * @param array $filters ['group' => '', 'action' => '', 'from_date' => '', 'to_date' => '']
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAuditLog($filters = [], $limit = 100)
    {
        $query = SettingAuditLog::query();

        if (!empty($filters['group'])) {
            $query->inGroup($filters['group']);
        }

        if (!empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (!empty($filters['from_date'])) {
            $query->fromDate($filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->toDate($filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clear old audit logs (older than days).
     *
     * @param int $days
     * @return int
     */
    public function pruneAuditLogs($days = 90)
    {
        return SettingAuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
