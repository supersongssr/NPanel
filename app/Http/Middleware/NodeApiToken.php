<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class NodeApiToken
{
    public function handle($request, Closure $next)
    {
        $token = $this->extractToken($request);

        if (!$token || $token !== env('API_TOKEN')) {
            if ($request->is('*/node/nginx_config')) {
                return response('Unauthorized', 401);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: invalid or missing token',
            ], 401);
        }

        return $next($request);
    }

    private function extractToken($request)
    {
        $header = $request->header('Authorization', '');
        if (stripos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }

        $customHeader = $request->header('X-API-Token');
        if ($customHeader) {
            return $customHeader;
        }

        return $request->input('token');
    }
}
