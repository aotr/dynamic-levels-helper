<?php

namespace Aotr\DynamicLevelHelper\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $username = config('dynamic-levels-helper.basic_auth_username');
        $password = config('dynamic-levels-helper.basic_auth_password');

        if ($request->getUser() !== $username || $request->getPassword() !== $password) {
            return response('Unauthorized', 401)->header('WWW-Authenticate', 'Basic realm="My App"');
        }

        return $next($request);
    }
}
