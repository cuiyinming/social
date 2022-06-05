<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Jewdore\ErrorDing\ErrorDing;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param \Exception $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if ($this->shouldntReport($exception)) {
            return;
        }
        if ($handler = ErrorDing::channel('common.error_handler')) {
            $handler->errorMsg($exception);
//            $handler->popUp($exception);
        }
        parent::report($exception);
    }


    public function render($request, Exception $exception)
    {
        if ($exception instanceof QueryException && !config('app.debug')) {
            return response()->view('errors.500', [], 500);
        }
        return parent::render($request, $exception);
    }
}
