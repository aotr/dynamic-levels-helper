<?php

namespace Aotr\DynamicLevelHelper\Traits;

use Aotr\DynamicLevelHelper\Services\DBService;
use Exception;
use Illuminate\Support\Facades\Log;

trait DBDataService
{
    protected DBService $dbService;

    protected ?array $params;

    protected string $stpName;

    protected ?string $dbConnection ;

    public function getData($stpName, $params, $stpConfig = [])
    {
        if (empty($dbConnection)) {
            $this->dbConnection = config('dynamic-levels-helper.db_connection_for_db_service');
        }
        $this->stpName = $stpName;
        $this->params = $this->processParams($params);
        $this->dbConnection = $stpConfig['connection'] ?? $this->dbConnection;

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
        $stpConfig = config("stp.{$this->stpName}");

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
        $this->dbService = new DBService($this->dbConnection);
        $result = $this->dbService->callStoredProcedure($this->stpName, collect($this->params)->values()->all());

        Log::channel('stp')->info('Request: ', $this->params);
        Log::channel('stp')->info('Response: ', $result);

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
}
