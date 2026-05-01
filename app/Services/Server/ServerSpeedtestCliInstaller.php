<?php

namespace App\Services\Server;

use App\Dto\Server\ServerFactory;
use App\Models\Server\Server;
use App\Services\Panel\marzban\MarzbanService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * Установка speedtest-cli (пакеты ОС) по SSH для блока --- speedtest --- в panel-stub-test-speed.sh (/test-speed).
 */
class ServerSpeedtestCliInstaller
{
    private MarzbanService $marzbanService;

    public function __construct(MarzbanService $marzbanService)
    {
        $this->marzbanService = $marzbanService;
    }

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function install(Server $server): array
    {
        if (empty($server->login) || $server->password === null || $server->password === '') {
            return ['success' => false, 'message' => 'Нет логина или пароля SSH.', 'output' => ''];
        }
        if (trim((string) $server->ip) === '') {
            return ['success' => false, 'message' => 'Не задан IP.', 'output' => ''];
        }

        $dto = ServerFactory::fromEntity($server);
        try {
            $ssh = $this->marzbanService->connectSshAdapter($dto);
        } catch (Throwable $e) {
            Log::warning('Speedtest-cli install: SSH connect failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SSH: '.$e->getMessage(),
                'output' => '',
            ];
        }

        $oldTimeout = (int) ($ssh->getTimeout() ?? 300);
        $ssh->setTimeout(900);

        try {
            $raw = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->execRemoteBashScript($ssh, $this->remoteInstallScript())));
            $exit = (int) $ssh->getExitStatus();
            $pathCli = trim((string) $ssh->exec('command -v speedtest-cli 2>/dev/null || true'));
            $pathOokla = trim((string) $ssh->exec('command -v speedtest 2>/dev/null || true'));
        } finally {
            $ssh->setTimeout($oldTimeout);
        }

        $hasBin = $pathCli !== '' || $pathOokla !== '';
        if ($hasBin) {
            $msg = Str::contains($raw, 'ALREADY_HAVE')
                ? 'Уже есть speedtest-cli или speedtest в PATH.'
                : 'Пакет установлен; '.$this->formatFoundPaths($pathCli, $pathOokla);

            return ['success' => true, 'message' => $msg, 'output' => Str::limit($raw, 2500)];
        }

        $hint = 'Нужны root или sudo без запроса пароля для установки пакетов.';
        if (Str::contains($raw, 'NO_PM')) {
            $hint = 'Не найден пакетный менеджер (apt/dnf/yum/apk/zypper).';
        }

        return [
            'success' => false,
            'message' => ($exit !== 0 ? 'Код '.$exit.'. ' : '').$hint.' '.Str::limit($raw !== '' ? $raw : '(нет вывода)', 500),
            'output' => Str::limit($raw, 2500),
        ];
    }

    private function formatFoundPaths(string $pathCli, string $pathOokla): string
    {
        $parts = [];
        if ($pathCli !== '') {
            $parts[] = 'speedtest-cli: '.$pathCli;
        }
        if ($pathOokla !== '') {
            $parts[] = 'speedtest: '.$pathOokla;
        }

        return $parts !== [] ? implode('; ', $parts) : '';
    }

    private function execRemoteBashScript(SSH2 $ssh, string $script): string
    {
        $script = str_replace(["\r\n", "\r"], "\n", $script);
        $b64 = base64_encode($script);

        return (string) $ssh->exec('printf %s '.var_export($b64, true).' | base64 -d | /bin/bash 2>&1');
    }

    private function remoteInstallScript(): string
    {
        return <<<'BASH'
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
set -e
if [ "$(id -u)" -ne 0 ]; then
  if sudo -n true 2>/dev/null; then
    SUDO="sudo -n"
  else
    SUDO="sudo"
  fi
else
  SUDO=""
fi

if command -v speedtest >/dev/null 2>&1 || command -v speedtest-cli >/dev/null 2>&1; then
  echo "ALREADY_HAVE"
  command -v speedtest-cli 2>/dev/null || true
  command -v speedtest 2>/dev/null || true
  exit 0
fi

if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  $SUDO apt-get update -qq
  $SUDO apt-get install -y speedtest-cli
elif command -v dnf >/dev/null 2>&1; then
  $SUDO dnf install -y speedtest-cli || $SUDO dnf install -y python3-speedtest-cli
elif command -v yum >/dev/null 2>&1; then
  $SUDO yum install -y epel-release 2>/dev/null || true
  $SUDO yum install -y speedtest-cli || $SUDO yum install -y python3-speedtest-cli
elif command -v apk >/dev/null 2>&1; then
  $SUDO apk add --no-cache speedtest-cli
elif command -v zypper >/dev/null 2>&1; then
  $SUDO zypper --non-interactive install -y speedtest-cli || $SUDO zypper --non-interactive install -y python3-speedtest-cli
else
  echo "NO_PM" >&2
  exit 42
fi

command -v speedtest-cli >/dev/null 2>&1 || command -v speedtest >/dev/null 2>&1 || { echo "MISSING_AFTER_INSTALL" >&2; exit 1; }
echo "DONE"
exit 0
BASH;
    }
}
