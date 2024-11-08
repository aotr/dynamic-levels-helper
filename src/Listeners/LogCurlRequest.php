<?php

namespace Aotr\DynamicLevelHelper\Listeners;

use Aotr\DynamicLevelHelper\Events\CurlRequestMade;
use Illuminate\Support\Facades\Log;
class LogCurlRequest
{
    public function handle(CurlRequestMade $event)
    {
        // Log the request to your custom log channel
        if ($event->error) {
            Log::channel('curl')->error(
                'CurlRequestMade: ' . $event->url,
                [
                    'url' => $event->url,
                    'headers' => $event->headers,
                    'request' => $event->request,
                    'response' => $event->response,
                    'error' => $event->error,
                ]
            );
        } else {
            Log::channel('curl')->info(
                'CurlRequestMade: ' . $event->url,
                [
                    'url' => $event->url,
                    'headers' => $event->headers,
                    'request' => $event->request,
                    'response' => $event->response,
                    'error' => $event->error,
                ]
            );
        }
    }
}
