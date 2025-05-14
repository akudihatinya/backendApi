<?php

namespace App\Http\Middleware\ApiProtection;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ApiResponseFormat
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
        // Proceed with the request
        $response = $next($request);

        // Check if response is JSON
        if (!$this->isJsonResponse($response)) {
            return $response;
        }

        // Get the original content
        $content = $response->getContent();
        $data = json_decode($content, true);

        // If JSON is invalid, return the original response
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $response;
        }

        // Format the response
        $formatted = [
            'success' => $response->isSuccessful(),
            'status_code' => $response->getStatusCode(),
        ];

        // Add error key for non-successful responses
        if (!$response->isSuccessful()) {
            $formatted['error'] = $data['message'] ?? 'Unknown error';
        }

        // Add data to the response
        if ($response->isSuccessful()) {
            // Keep all data fields
            foreach ($data as $key => $value) {
                if ($key !== 'success' && $key !== 'status_code') {
                    $formatted[$key] = $value;
                }
            }
        }

        // Return the formatted response
        return Response::json($formatted, $response->getStatusCode(), $response->headers->all());
    }

    /**
     * Determine if the response is a JSON response.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return bool
     */
    protected function isJsonResponse($response)
    {
        return $response->headers->get('Content-Type') === 'application/json';
    }
}