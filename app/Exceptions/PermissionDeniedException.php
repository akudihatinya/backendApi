<?php

namespace App\Exceptions;

use Exception;

class PermissionDeniedException extends Exception
{
    /**
     * Create a new permission denied exception.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  \Throwable|null  $previous
     * @return void
     */
    public function __construct(
        string $message = 'Anda tidak memiliki izin untuk melakukan tindakan ini.',
        int $code = 403,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     *
     * @return bool
     */
    public function report(): bool
    {
        return true; // Report this exception
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'permission_denied',
        ], $this->getCode());
    }
}