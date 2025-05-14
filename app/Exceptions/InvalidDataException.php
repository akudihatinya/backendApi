<?php

namespace App\Exceptions;

use Exception;

class InvalidDataException extends Exception
{
    /**
     * The validation errors.
     *
     * @var array
     */
    protected $errors;

    /**
     * Create a new invalid data exception.
     *
     * @param  string  $message
     * @param  array  $errors
     * @param  int  $code
     * @param  \Throwable|null  $previous
     * @return void
     */
    public function __construct(
        string $message = 'Data yang diberikan tidak valid.',
        array $errors = [],
        int $code = 422,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Report the exception.
     *
     * @return bool
     */
    public function report(): bool
    {
        return false; // Don't report this exception
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
            'errors' => $this->getErrors(),
        ], $this->getCode());
    }
}