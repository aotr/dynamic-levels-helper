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
     * Call a stored procedure with the provided name and parameters.
     *
     * @param string $storedProcedureName
     * @param array $parameters
     * @return array
     * @throws RuntimeException
     */
    public function callStoredProcedure(string $storedProcedureName, array $parameters = [])
    {
        if (!$this->checkStoredProcedure($storedProcedureName)) {
            $errorMessage = $storedProcedureName . ' - Stored Procedure does not exist';
            Log::critical($this->buildLogMessage($errorMessage, $storedProcedureName, $parameters));
            throw new RuntimeException($errorMessage);
        }

        $placeholders = implode(',', array_fill(0, count($parameters), '?'));
        $sql = "CALL {$storedProcedureName}({$placeholders})";

        try {
            $results = DB::connection($this->connection)->select($sql, $parameters);
            return $results;
        } catch (\Exception $e) {
            // Include $storedProcedureName, $parameters, and $sql in the error message
            $errorMessage = sprintf(
                "Error: %s. Stored Procedure: %s, Parameters: %s, SQL: %s",
                $e->getMessage(),
                $storedProcedureName,
                json_encode($parameters),
                $sql
            );
            Log::critical($this->buildLogMessage($errorMessage, $storedProcedureName, $parameters, $sql));
            throw new RuntimeException($errorMessage);  // Now throwing the detailed error message
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
