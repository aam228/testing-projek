<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfPublicPath
{
    public function handle(Request $request, Closure $next)
    {
        $uri = $request->getRequestUri();

        if (strpos($uri, '/public/') === 0) {
            $redirectUrl = str_replace('/public/', '/', $request->fullUrl());
            return redirect()->to($redirectUrl, 301);
        }

        return $next($request);
    }
}
