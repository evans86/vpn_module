<?php

namespace App\Services\Server;

use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\Server\Server;
use App\Services\Panel\marzban\MarzbanService;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;
use RuntimeException;

class LogUploadService
{
    /**
     * @var MarzbanService
     */
    private MarzbanService $marzbanService;

    /**
     * Путь к скрипту установки на сервере
     */
    private const UPLOAD_SCRIPT_PATH = '/root/upload-logs.sh';
    
    /**
     * Путь к конфигурации s3cmd
     */
    private const S3_CONFIG_PATH = '/root/.s3cfg';

    public function __construct(MarzbanService $marzbanService)
    {
        $this->marzbanService = $marzbanService;
    }

    /**
     * Включить выгрузку логов на сервере
     *
     * @param Server $server
     * @return array
     * @throws Exception
     */
    public function enableLogUpload(Server $server): array
    {
        try {
            Log::info('Enabling log upload', ['server_id' => $server->id, 'source' => 'server']);

            $serverDto = ServerFactory::fromEntity($server);
            $ssh = $this->marzbanService->connectSshAdapter($serverDto);

            // Читаем скрипт из ресурсов
            $scriptContent = file_get_contents(resource_path('scripts/upload-logs.sh'));
            
            if ($scriptContent === false) {
                throw new RuntimeException('Failed to read upload script');
            }

            // Подставляем реальные значения из конфигурации
            $scriptContent = $this->replaceScriptPlaceholders($scriptContent);

            // Загружаем скрипт на сервер
            $this->uploadScript($ssh, $scriptContent);

            // Выполняем скрипт установки
            $this->executeInstallation($ssh);

            // Проверяем, что скрипт установлен
            $isInstalled = $this->checkInstallation($ssh);

            if (!$isInstalled) {
                throw new RuntimeException('Script installation verification failed');
            }

            // Обновляем статус в БД
            $server->logs_upload_enabled = true;
            $server->save();

            Log::info('Log upload enabled successfully', ['server_id' => $server->id, 'source' => 'server']);

            return [
                'success' => true,
                'message' => 'Выгрузка логов успешно включена'
            ];

        } catch (Exception $e) {
            Log::error('Failed to enable log upload', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'server'
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка при включении выгрузки логов: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Проверить статус выгрузки логов на сервере
     *
     * @param Server $server
     * @return array
     */
    public function checkLogUploadStatus(Server $server): array
    {
        try {
            Log::info('Checking log upload status', ['server_id' => $server->id, 'source' => 'server']);

            $serverDto = ServerFactory::fromEntity($server);
            $ssh = $this->marzbanService->connectSshAdapter($serverDto);

            $isInstalled = $this->checkInstallation($ssh);
            $isCronConfigured = $this->checkCronConfiguration($ssh);

            $status = [
                'installed' => $isInstalled,
                'cron_configured' => $isCronConfigured,
                'enabled_in_db' => (bool)$server->logs_upload_enabled,
                'active' => $isInstalled && $isCronConfigured
            ];

            // Обновляем статус в БД, если он не совпадает
            if ($status['active'] !== (bool)$server->logs_upload_enabled) {
                $server->logs_upload_enabled = $status['active'];
                $server->save();
            }

            Log::info('Log upload status checked', [
                'server_id' => $server->id,
                'status' => $status,
                'source' => 'server'
            ]);

            return [
                'success' => true,
                'status' => $status,
                'message' => $status['active'] 
                    ? 'Выгрузка логов активна и настроена' 
                    : 'Выгрузка логов не настроена или не активна'
            ];

        } catch (Exception $e) {
            Log::error('Failed to check log upload status', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'source' => 'server'
            ]);

            return [
                'success' => false,
                'status' => [
                    'installed' => false,
                    'cron_configured' => false,
                    'enabled_in_db' => (bool)$server->logs_upload_enabled,
                    'active' => false
                ],
                'message' => 'Ошибка при проверке статуса: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Загрузить скрипт на сервер
     *
     * @param SSH2 $ssh
     * @param string $scriptContent
     * @return void
     * @throws RuntimeException
     */
    private function uploadScript(SSH2 $ssh, string $scriptContent): void
    {
        // Кодируем скрипт в base64 для безопасной передачи
        $encodedScript = base64_encode($scriptContent);
        
        // Разбиваем на части для избежания проблем с длинными строками
        // Создаем команду для декодирования и сохранения скрипта
        $command = sprintf(
            "echo '%s' | base64 -d > /tmp/upload-logs-install.sh 2>&1 && chmod +x /tmp/upload-logs-install.sh 2>&1",
            addslashes($encodedScript)
        );
        
        $result = $ssh->exec($command);
        
        // Проверяем, что файл создан
        $checkCommand = "test -f /tmp/upload-logs-install.sh && test -x /tmp/upload-logs-install.sh && echo 'ok'";
        $checkResult = $ssh->exec($checkCommand);
        
        if (!str_contains($checkResult, 'ok')) {
            Log::error('Script upload verification failed', [
                'upload_result' => $result,
                'check_result' => $checkResult
            ]);
            throw new RuntimeException("Failed to upload script: $result");
        }

        Log::info('Script uploaded to server successfully', ['source' => 'server']);
    }

    /**
     * Выполнить установку скрипта
     *
     * @param SSH2 $ssh
     * @return void
     * @throws RuntimeException
     */
    private function executeInstallation(SSH2 $ssh): void
    {
        $command = 'bash /tmp/upload-logs-install.sh';
        $result = $ssh->exec($command);
        
        Log::info('Installation script executed', ['output' => $result, 'source' => 'server']);

        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException("Installation script failed: $result");
        }
    }

    /**
     * Проверить, установлен ли скрипт
     *
     * @param SSH2 $ssh
     * @return bool
     */
    private function checkInstallation(SSH2 $ssh): bool
    {
        $command = "test -f " . self::UPLOAD_SCRIPT_PATH . " && test -x " . self::UPLOAD_SCRIPT_PATH . " && echo 'exists'";
        $result = $ssh->exec($command);
        
        return str_contains($result, 'exists');
    }

    /**
     * Проверить, настроен ли cron
     *
     * @param SSH2 $ssh
     * @return bool
     */
    private function checkCronConfiguration(SSH2 $ssh): bool
    {
        $command = "crontab -l 2>/dev/null | grep -q 'upload-logs.sh' && echo 'configured'";
        $result = $ssh->exec($command);
        
        return str_contains($result, 'configured');
    }

    /**
     * Заменить плейсхолдеры в скрипте на реальные значения из конфигурации
     *
     * @param string $scriptContent
     * @return string
     * @throws RuntimeException
     */
    private function replaceScriptPlaceholders(string $scriptContent): string
    {
        $accessKey = config('services.s3_logs.access_key');
        $secretKey = config('services.s3_logs.secret_key');
        $bucket = config('services.s3_logs.bucket', 's3://logsvpn');

        if (empty($accessKey) || empty($secretKey)) {
            throw new RuntimeException('S3 credentials not configured. Please set S3_LOGS_ACCESS_KEY and S3_LOGS_SECRET_KEY in .env file');
        }

        $replacements = [
            '{{S3_ACCESS_KEY}}' => $accessKey,
            '{{S3_SECRET_KEY}}' => $secretKey,
            '{{S3_BUCKET}}' => $bucket,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $scriptContent);
    }
}

