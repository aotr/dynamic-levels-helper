<?php

namespace Aotr\DynamicLevelHelper\Events;


use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CurlRequestMade
{
    use Dispatchable, SerializesModels;

    public $url;
    public $headers;
    public $response;
    public $request;
    public $error;

    public function __construct($url, $headers,$request, $response, $error)
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->response = $response;
        $this->request = $request;
        $this->error = $error;
    }
}
