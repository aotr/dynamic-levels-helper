<?php

namespace Aotr\DynamicLevelHelper;

class DynamicHelpersLoader
{
    public function __call($method, $arguments)
    {
        if (function_exists($method)) {
            return call_user_func_array($method, $arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
