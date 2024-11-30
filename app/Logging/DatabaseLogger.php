<?php

namespace App\Logging;

use App\Models\Log\ApplicationLog;
use Illuminate\Support\Facades\Auth;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Throwable;

class DatabaseLogger
{
    /**
     * Create a custom Monolog instance.
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
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
            \Illuminate\Support\Facades\Log::error('Failed to write to database log: ' . $e->getMessage(), [
                'original_message' => $message,
                'original_context' => $context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
}

class DatabaseLoggerHandler extends AbstractProcessingHandler
{
    /**
     * Write the log record to the database
     *
     * @param array $record
     * @return void
     */
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

            $data = [
                'level' => strtolower($record['level_name']),
                'message' => mb_convert_encoding($record['message'], 'UTF-8', 'UTF-8'),
                'source' => isset($context['source']) ? $context['source'] : 'system',
                'context' => $context,
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ];

            ApplicationLog::create($data);
        } catch (Throwable $e) {
            // В случае ошибки записи лога, записываем в стандартный error_log
            error_log(sprintf(
                'Failed to write log entry: %s. Error: %s',
                json_encode($record),
                $e->getMessage()
            ));
        }
    }
}
