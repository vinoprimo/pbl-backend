<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Log the incoming request
        Log::info('API Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // Process the request
        $response = $next($request);

        // Only log the response for API requests
        if (strpos($request->path(), 'api/') === 0) {
            // Get the response content
            $content = $response->getContent();
            
            // Try to decode JSON response
            $decoded = json_decode($content, true);
            
            // Log the response data
            Log::info('API Response', [
                'status' => $response->getStatusCode(),
                'content' => is_array($decoded) ? $decoded : substr($content, 0, 500),
            ]);
        }

        return $response;
    }
}
