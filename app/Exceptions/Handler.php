<?php

namespace App\Exceptions;

use App\Models\Log\ApplicationLog;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Дополнительное логирование критических ошибок
            if ($this->shouldReport($e)) {
                Log::critical('Unhandled exception - system critical error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'source' => 'system'
                ]);
            }
        });

        // Обработка 404 ошибок
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found',
                    'error' => 'Not Found'
                ], 404);
            }
            return null;
        });

        // Обработка HTTP исключений
        $this->renderable(function (HttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'An error occurred',
                    'error' => class_basename($e)
                ], $e->getStatusCode());
            }
            return null;
        });

        // Для любых необработанных ошибок при JSON-запросе возвращаем JSON и пишем в application_logs
        $this->renderable(function (Throwable $e, Request $request) {
            if ($e instanceof ValidationException) {
                return null; // Laravel сам вернёт 422
            }
            if ($request->expectsJson() || $request->ajax()) {
                $message = $e->getMessage() ?: 'Внутренняя ошибка сервера';
                // Всегда пишем в application_logs, чтобы ошибка была видна в админке → Логи
                try {
                    $trace = $e->getTraceAsString();
                    ApplicationLog::create([
                        'level' => 'error',
                        'source' => 'exception_handler',
                        'message' => 'Ошибка (не обработана контроллером): ' . $message,
                        'context' => [
                            'error_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => strlen($trace) > 8000 ? substr($trace, 0, 8000) . "\n...[обрезано]" : $trace,
                            'url' => $request->fullUrl(),
                            'method' => $request->method(),
                        ],
                        'user_id' => $request->user() ? (string) $request->user()->id : null,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                } catch (Throwable $logEx) {
                    Log::error('Не удалось записать в application_logs: ' . $logEx->getMessage());
                }
                return response()->json([
                    'message' => $message,
                    'error' => class_basename($e),
                ], 500);
            }
            return null;
        });
    }
}
