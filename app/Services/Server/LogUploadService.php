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
     * Путь к скрипту выгрузки на сервере (cron и ручной запуск)
     */
    public const UPLOAD_SCRIPT_PATH = '/root/upload-logs.sh';
    
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
            $alreadyEnabledInDb = (bool) $server->logs_upload_enabled;

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

            // Проверяем доступ к бакету с сервера (ключи, сеть, имя бакета)
            $verify = $this->verifyS3AccessFromHost($ssh);
            if (!$verify['ok']) {
                throw new RuntimeException(
                    'Не удалось прочитать бакет с сервера (s3cmd ls). Выгрузка не будет работать. '.$verify['message']
                );
            }

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
                'message' => $alreadyEnabledInDb
                    ? 'Скрипт /root/upload-logs.sh, ~/.s3cfg и cron обновлены из настроек панели.'
                    : 'Выгрузка логов успешно включена.',
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
     * Один запуск скрипта выгрузки на сервере (текущие access.log/error.log как при cron).
     *
     * @return array{success: bool, message: string, output: string, skipped: bool}
     */
    public function runUploadScriptNow(Server $server): array
    {
        if (!$server->logs_upload_enabled) {
            return [
                'success' => false,
                'message' => 'В БД не включена выгрузка логов для этого сервера.',
                'output' => '',
                'skipped' => true,
            ];
        }
        if (empty($server->login) || $server->password === null || $server->password === '') {
            return [
                'success' => false,
                'message' => 'Нет SSH логина или пароля.',
                'output' => '',
                'skipped' => true,
            ];
        }

        try {
            $serverDto = ServerFactory::fromEntity($server);
            $ssh = $this->marzbanService->connectSshAdapter($serverDto);

            $path = escapeshellarg(self::UPLOAD_SCRIPT_PATH);
            $checkCmd = "test -f {$path} && test -x {$path} && echo SCRIPT_OK";
            $checkOut = trim($ssh->exec($checkCmd));
            if (strpos($checkOut, 'SCRIPT_OK') === false) {
                return [
                    'success' => false,
                    'message' => 'Скрипт '.self::UPLOAD_SCRIPT_PATH.' не найден. Сначала включите выгрузку на этом сервере.',
                    'output' => $checkOut,
                    'skipped' => false,
                ];
            }

            $bash = '(bash '.$path.' 2>&1); echo LOGUP_EXIT:$?';
            $raw = trim($ssh->exec($bash));
            $exit = null;
            if (preg_match('/LOGUP_EXIT:(\d+)\s*$/', $raw, $m)) {
                $exit = (int) $m[1];
                $out = preg_replace('/\s*LOGUP_EXIT:\d+\s*$/', '', $raw);
                $out = $out !== null ? trim((string) $out) : '';
            } else {
                $out = $raw;
            }

            $maxLen = 12000;
            if (strlen($out) > $maxLen) {
                $out = substr($out, 0, $maxLen)."\n… (вывод обрезан)";
            }

            $ok = $exit === 0;
            // Старые версии скрипта на нодах могли не проверять s3cmd и S3_BUCKET: выход 0 при ERROR в выводе
            $s3OutputFailure = strpos($out, 'Destination must be S3Uri') !== false
                || strpos($out, 'Parameter problem:') !== false;
            if ($ok && $s3OutputFailure) {
                $ok = false;
            }
            Log::info('Manual log upload script run finished', [
                'server_id' => $server->id,
                'exit_code' => $exit,
                'source' => 'server',
                'success' => $ok,
            ]);

            $message = $ok
                ? 'Выгрузка выполнена (код выхода 0).'
                : ('Ненулевой код выхода'.($exit !== null ? ": {$exit}" : '').'. См. вывод.');
            if (!$ok && $exit === 0 && $s3OutputFailure) {
                $message = 'В выводе s3cmd ошибка (часто пустой или неверный S3_BUCKET на сервере). В панели заново включите выгрузку логов, чтобы обновить /root/upload-logs.sh.';
            }

            return [
                'success' => $ok,
                'message' => $message,
                'output' => $out,
                'skipped' => false,
            ];
        } catch (Exception $e) {
            Log::error('Manual log upload script failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'source' => 'server',
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'output' => '',
                'skipped' => false,
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
        
        if (strpos($checkResult, 'ok') === false) {
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
        // Код выхода в выводе: getExitStatus() у phpseclib после длинного bash+apt часто неверен
        $bash = 'bash /tmp/upload-logs-install.sh 2>&1; printf "\n__LOGUP_INSTALL_EXIT__:%s\n" "$?"';
        $raw = trim((string) $ssh->exec($bash));
        $exit = null;
        if (preg_match_all('/__LOGUP_INSTALL_EXIT__:(\d+)/', $raw, $mm) && !empty($mm[1])) {
            $exit = (int) end($mm[1]);
        }
        $result = trim((string) preg_replace('/\s*__LOGUP_INSTALL_EXIT__:\d+\s*\z/', '', $raw));

        Log::info('Installation script executed', [
            'output' => $result,
            'parsed_exit' => $exit,
            'ssh_get_exit_status' => $ssh->getExitStatus(),
            'source' => 'server',
        ]);

        if ($exit !== null) {
            if ($exit !== 0) {
                throw new RuntimeException('Installation script failed: '.$result);
            }

            return;
        }

        $sshExit = $ssh->getExitStatus();
        if ($sshExit === 0) {
            return;
        }
        if (strpos($result, '[+] Setup complete.') !== false) {
            Log::warning('Log upload install: install exit marker missing in SSH output; accepted by Setup complete marker', [
                'source' => 'server',
                'ssh_get_exit_status' => $sshExit,
            ]);

            return;
        }

        throw new RuntimeException(
            'Installation script failed'.($sshExit !== null && $sshExit !== false ? ' (SSH exit '.$sshExit.')' : '').': '.$result
        );
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
        
        return strpos($result, 'exists') !== false;
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

        return strpos($result, 'configured') !== false;
    }

    /**
     * Проверка: с сервера виден объектный стор через s3cmd (после записи ~/.s3cfg установщиком).
     *
     * @return array{ok: bool, message: string}
     */
    private function verifyS3AccessFromHost(SSH2 $ssh): array
    {
        $bucket = (string) config('services.s3_logs.bucket', 's3://logsvpn');
        $escaped = escapeshellarg($bucket);
        $bash = '(s3cmd ls '.$escaped.' 2>&1); echo S3LS_EXIT:$?';
        $raw = trim($ssh->exec($bash));
        if (preg_match('/S3LS_EXIT:(\d+)\s*$/', $raw, $m)) {
            $exit = (int) $m[1];
            $out = trim(preg_replace('/\s*S3LS_EXIT:\d+\s*$/', '', $raw));
            if ($exit === 0) {
                return ['ok' => true, 'message' => $out !== '' ? $out : $bucket];
            }

            return ['ok' => false, 'message' => $out !== '' ? $out : ('код '.$exit)];
        }

        return ['ok' => false, 'message' => $raw !== '' ? $raw : 'нет вывода s3cmd'];
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

