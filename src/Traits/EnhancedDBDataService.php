<?php

namespace Aotr\DynamicLevelHelper\Traits;

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Exception;
use Illuminate\Support\Facades\Log;

trait EnhancedDBDataService
{
    protected EnhancedDBService $enhancedDbService;

    protected ?array $params;

    protected string $stpName;

    protected ?string $dbConnection;

    protected ?string $stpConfigPath;

    public function getData($stpName, $params, $stpConfig = [])
    {
        $this->stpConfigPath = $stpConfig['stp_config_path'] ?? $this->stpConfigPath ?? "dynamic-levels-helper-stp";
        $this->stpName = $stpName;
        $this->params = $this->processParams($params);
        $this->dbConnection = $stpConfig['connection'] ?? $this->dbConnection ?? config('dynamic-levels-helper.enhanced_db_service.default_connection');

        try {
            return $this->extractOutput($this->fetchData());
        } catch (Exception $e) {
            Log::channel('stp')->error($e->getMessage());

            return [
                'error' => 1,
                'errmsg' => $e->getMessage(),
                'response' => [],
                'request' => $this->params,
            ];
        }
    }

    private function processParams($params)
    {
        if (empty($params)) {
            return [];
        }

        $formattedParams = [];
        $stpConfig = config("{$this->stpConfigPath}.{$this->stpName}");

        foreach ($stpConfig as $key => $value) {
            if (array_key_exists($key, $params)) {
                $formattedParams[$value] = $params[$key] ?? '';
            } else {
                throw new Exception("{$key} is not found in the parameters for {$this->stpName} stored procedure.");
            }
        }

        return $formattedParams;
    }

    private function fetchData()
    {
        $this->enhancedDbService = EnhancedDBService::getInstance();
        $result = $this->enhancedDbService->callStoredProcedure(
            $this->stpName,
            collect($this->params)->values()->all(),
            [
                'connection' => $this->dbConnection,
                'checkStoredProcedure' => false,
                'enableLogging' => true,
                'timeout' => 30,
                'retryAttempts' => 3, // Enable retry for transient failures
                'retryDelay' => 100,  // Base delay in milliseconds
            ]
        );

        Log::channel('stp')->info('Enhanced DB Request: ', $this->params);
        Log::channel('stp')->info('Enhanced DB Response: ', $result);

        return $result;
    }

    private function extractOutput($result)
    {
        return [
            'error' => 0,
            'errmsg' => '',
            'response' => $result,
            'request' => $this->params,
        ];
    }

    public function handle(...$params)
    {
        $dynamicMethod = array_shift($params);

        if (!$dynamicMethod || !method_exists($this, $dynamicMethod)) {
            throw new Exception('The desired method not found');
        }

        return call_user_func_array([$this, $dynamicMethod], $params);
    }

    /**
     * Get connection pool statistics
     *
     * @return array
     */
    public function getConnectionPoolStats(): array
    {
        return $this->enhancedDbService->getConnectionPoolStats();
    }

    /**
     * Get performance metrics
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        return $this->enhancedDbService->getPerformanceMetrics();
    }

    /**
     * Clear performance metrics
     *
     * @return void
     */
    public function clearPerformanceMetrics(): void
    {
        $this->enhancedDbService->clearPerformanceMetrics();
    }
}
