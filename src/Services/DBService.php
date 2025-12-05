<?php

namespace Aotr\DynamicLevelHelper\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DBService
{
    /**
     * @var string
     */
    private $connection;

    /**
     * DBService constructor.
     *
     * @param string $connection
     */
    public function __construct(string $connection = 'mysql')
    {
        $this->connection = $connection;
    }

    /**
     * Call a stored procedure with the provided name and parameters, supporting multiple result sets.
     * Additionally, logs the query execution time for visibility in Laravel Telescope.
     *
     * @param string $storedProcedureName The name of the stored procedure to call.
     * @param array $parameters Parameters to pass to the stored procedure.
     * @param array $config Configuration options for the method.
     *   - `checkStoredProcedure` (bool): Whether to check if the stored procedure exists before calling it. Defaults to false.
     * @return array An array of result sets, where each result set is an array of associative arrays.
     * @throws RuntimeException If the stored procedure doesn't exist (when `checkStoredProcedure` is true) or if an error occurs during execution.
     *
     * @example
     * $dbService = new DBService('mysql');
     * $results = $dbService->callStoredProcedure('my_stored_procedure', [1, 'example']);
     * $firstResultSet = $results[0];
     * $secondResultSet = $results[1];
     */

    public function callStoredProcedure(string $storedProcedureName, array $parameters = [], array $config = [])
    {
        $config = array_merge([
            'checkStoredProcedure' => false,
        ], $config);
        if($config["connection"]){
            $this->connection = $config["connection"];
        }

        if ($config["checkStoredProcedure"] && !$this->checkStoredProcedure($storedProcedureName)) {
            $errorMessage = $storedProcedureName . ' - Stored Procedure does not exist';
            Log::critical($this->buildLogMessage($errorMessage, $storedProcedureName, $parameters));
            throw new RuntimeException($errorMessage);
        }

        $placeholders = implode(',', array_fill(0, count($parameters), '?'));
        $sql = "CALL {$storedProcedureName}({$placeholders})";

        try {
            $pdo = DB::connection($this->connection)->getPdo();

            // Start time tracking
            $startTime = microtime(true);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parameters);

            $resultSets = [];
            do {
                $resultSets[] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } while ($stmt->nextRowset());

            // End time tracking
            $executionTime = microtime(true) - $startTime;

            // Log the query into Laravel's query log for Telescope
            DB::connection($this->connection)->logQuery(
                $sql,
                $parameters,
                $executionTime
            );

            return $resultSets;
        } catch (\Exception $e) {
            $errorMessage = sprintf(
                "Error: %s. Stored Procedure: %s, Parameters: %s, SQL: %s",
                $e->getMessage(),
                $storedProcedureName,
                json_encode($parameters),
                $sql
            );
            Log::critical($this->buildLogMessage($errorMessage, $storedProcedureName, $parameters, $sql));
            throw new RuntimeException($errorMessage);
        }
    }


    /**
     * Check if a stored procedure exists in the database.
     *
     * @param string $procedureName
     * @return bool
     */
    private function checkStoredProcedure(string $procedureName)
    {
        $cacheKey = 'sp_' . $procedureName;
        $exists = Cache::rememberForever($cacheKey, function () use ($procedureName) {
            return DB::connection($this->connection)
                ->table('information_schema.routines')
                ->where('SPECIFIC_NAME', $procedureName)
                ->exists();
        });

        if (!$exists) {
            Cache::forget($cacheKey);
        }

        return $exists;
    }

    /**
     * Build a log message for critical errors.
     *
     * @param string $message
     * @param string $storedProcedureName
     * @param array $parameters
     * @param string $sql
     * @return string
     */
    private function buildLogMessage(string $message, string $storedProcedureName, array $parameters, string $sql = '')
    {
        return collect([
            'message' => $message,
            'Stored_Procedure_Name' => $storedProcedureName,
            'parameters' => $parameters,
            'sql' => $sql,
            'user_session' => session()->getId(),
            'ip' => request()->ip(),
        ])->toJson();
    }
}
