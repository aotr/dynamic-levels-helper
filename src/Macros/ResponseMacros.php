<?php

namespace Aotr\DynamicLevelHelper\Macros;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Class ResponseMacros
 *
 * Defines custom response macros for the application.
 */
class ResponseMacros
{
    /**
     * Registers all response macros.
     *
     * @return void
     */
    public static function register()
    {
        // Register the base API response macro
        Response::macro('api', function (
            $data = null,
            int $errorCode = 0,
            string $errorMessage = '',
            array $additionalData = []
        ): JsonResponse {
            $response = [
                'ack'      => $errorCode === 0 ? 1 : 0,
                'error'    => $errorCode,
                'errmsg'   => $errorMessage,
                'response' => $data,
            ];

            // Merge any additional data into the response
            if (!empty($additionalData)) {
                $response = array_merge($response, $additionalData);
            }

            return Response::json($response);
        });

        // Register the processed API response macro
        Response::macro('apiProcess', function (
            $data = null,
            int $errorCode = 0,
            string $errorMessage = '',
            array $additionalData = []
        ): JsonResponse {
            // Process the data if it's a collection or array
            if ($data instanceof Collection) {
                $data = $data->filter()->values();
            } elseif (is_array($data)) {
                $data = collect($data)->filter()->values();
            }

            // If the processed data has only one item, return it directly
            if (is_countable($data) && count($data) === 1) {
                $data = collect($data)->first();
            } else {
                $data = $data->all();
            }

            return Response::api($data, $errorCode, $errorMessage, $additionalData);
        });

        // Register the error API response macro
        Response::macro('apiError', function (
            string|array|Collection $errorMessage,
            int $errorCode = 0,
            array $additionalData = []
        ): JsonResponse {
            // If the error message is an array or collection, convert it to a string
            if (is_array($errorMessage) || $errorMessage instanceof Collection) {

                $errorMessage = implode(' | ', $errorMessage);
            }
            return Response::api(null, $errorCode, $errorMessage, $additionalData);
        });
    }
}
