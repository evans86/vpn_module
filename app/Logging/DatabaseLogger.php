<?php

namespace App\Logging;

use App\Models\Log\ApplicationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Throwable;

class DatabaseLogger
{
    /**
     * Create a custom Monolog instance.
     *
     * @param  array  $config
     * @return Logger
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('database');
        $handler = new DatabaseLoggerHandler();

        if (isset($config['level'])) {
            $handler->setLevel($config['level']);
        }

        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Write a log entry to the database
     *
     * @param string $message
     * @param array $context
     * @param string $level
     * @return void
     */
    public function write(string $message, array $context = [], string $level = 'info'): void
    {
        try {
            $user_id = Auth::id() ?? 0;

            ApplicationLog::create([
                'message' => $message,
                'level' => $level,
                'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
                'user_id' => $user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // В случае ошибки записи в БД, логируем в файл
            // Это критическая ошибка, т.к. система логирования не может работать
            error_log('CRITICAL: Failed to write to database log: ' . $e->getMessage() . ' | Original message: ' . $message);
        }
    }

    /**
     * Log an info message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->write($message, $context, 'info');
    }

    /**
     * Log an error message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->write($message, $context, 'error');
    }

    /**
     * Log a warning message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write($message, $context, 'warning');
    }

    /**
     * Log a debug message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write($message, $context, 'debug');
    }

    /**
     * Log a critical message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->write($message, $context, 'critical');
    }
}

class DatabaseLoggerHandler extends AbstractProcessingHandler
{
    /**
     * Write the log record to the database
     *
     * @param array $record
     * @return void
     */
    /** Max size of stored context (bytes) to avoid memory exhaustion when logging */
    private const MAX_CONTEXT_SIZE = 50000;

    protected function write(array $record): void
    {
        try {
            // Prepare context data
            $context = isset($record['context']) ? $record['context'] : [];

            // If there's an exception in the context, format it properly
            if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
                $exception = $context['exception'];
                $context['exception'] = [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ];
            }

            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($contextJson !== false && strlen($contextJson) > self::MAX_CONTEXT_SIZE) {
                $context = [
                    '_truncated' => true,
                    '_original_size' => strlen($contextJson),
                    'preview' => substr($contextJson, 0, self::MAX_CONTEXT_SIZE - 100) . '...'
                ];
            }

            $data = [
                'level' => strtolower($record['level_name']),
                'message' => mb_convert_encoding(mb_substr($record['message'], 0, 65535), 'UTF-8', 'UTF-8'),
                'source' => isset($record['context']['source']) ? $record['context']['source'] : 'system',
                'context' => $context,
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ];

            ApplicationLog::create($data);
        } catch (Throwable $e) {
            // При недоступности БД не используем error_log — иначе Nginx error.log забивается дубликатами.
            // Пишем одну короткую строку в отдельный файл.
            $failLog = storage_path('logs/database_logger_failures.log');
            if (function_exists('storage_path') && is_writable(dirname($failLog))) {
                @file_put_contents(
                    $failLog,
                    date('c') . ' DB log write failed: ' . $e->getMessage() . "\n",
                    LOCK_EX | FILE_APPEND
                );
            }
        }
    }
}
