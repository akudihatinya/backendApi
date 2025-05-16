<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Custom handler for API responses
        $this->renderable(function (Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // Authentication exception
                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'message' => 'Unauthenticated.',
                    ], 401);
                }
                
                // Resource not found exception
                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'message' => 'Resource tidak ditemukan.',
                    ], 404);
                }
                
                // Validation exception
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'message' => 'Data yang diberikan tidak valid.',
                        'errors' => $e->errors(),
                    ], 422);
                }
                
                // Custom application exceptions
                if ($e instanceof ExaminationAlreadyExistsException ||
                    $e instanceof InvalidDataException ||
                    $e instanceof PatientNotFoundException ||
                    $e instanceof PermissionDeniedException) {
                    // These exceptions handle their own responses in their render method
                    return $e->render($request);
                }
                
                // General server error
                if (!config('app.debug')) {
                    return response()->json([
                        'message' => 'Server Error',
                    ], 500);
                }
            }
        });
    }
}