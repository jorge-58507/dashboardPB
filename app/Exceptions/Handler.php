<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $levels = [
        //
    ];

    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Handle unauthenticated users.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => 'Unauthenticated.',
                'message' => 'Por favor inicia sesión.'
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Manejar TokenMismatchException como si fuera falta de autenticación
        if ($exception instanceof TokenMismatchException) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'Unauthenticated.',
                    'message' => 'Sesión expirada. Por favor inicia sesión.'
                ], 401);
            }
            return redirect()->guest(route('login'));
        }

        return parent::render($request, $exception);
    }

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}


