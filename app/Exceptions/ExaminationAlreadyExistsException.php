<?php

namespace App\Exceptions;

use Exception;

class ExaminationAlreadyExistsException extends Exception
{
    /**
     * Create a new examination already exists exception.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  \Throwable|null  $previous
     * @return void
     */
    public function __construct(
        string $message = 'Pemeriksaan untuk pasien ini pada tanggal tersebut sudah ada.',
        int $code = 409,
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
            'error' => 'examination_already_exists',
        ], $this->getCode());
    }
}