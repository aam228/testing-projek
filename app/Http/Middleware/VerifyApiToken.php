<?php

namespace App\Http\Middleware;

use App\Services\CustomerAccessToken;
use Closure;
use Illuminate\Http\Request;

class VerifyApiToken
{
    public function __construct(private CustomerAccessToken $tokens)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $customerId = $token ? $this->tokens->customerId($token) : null;
        if ($customerId === null) {
            return response()->json(['message' => 'Token autentikasi tidak ada, tidak valid, atau kedaluwarsa.'], 401, [
                'WWW-Authenticate' => 'Bearer',
            ]);
        }

        $requestedId = $request->input('cust_id');
        if ((!is_int($requestedId) && !is_string($requestedId)) || (string) $requestedId !== (string) $customerId) {
            return response()->json(['message' => 'Identitas pelanggan tidak sesuai dengan token.'], 403);
        }

        $request->attributes->set('authenticated_customer_id', $customerId);

        return $next($request);
    }
}
