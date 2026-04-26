<?php

namespace App\Services\Server;

use App\Dto\Server\ServerFactory;
use App\Models\Server\Server;
use App\Services\Panel\marzban\MarzbanService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use RuntimeException;

/**
 * Заглушка Nginx (default_server) на 80/443 по SSH: антискан по IP, как в deploy/nginx/panel-stub.default-server.conf.
 * Нужен nginx в ОС (не в Docker) и root или пользователь с правом писать в /etc/nginx и /var/www.
 */
class ServerDecoyStubService
{
    private const STUB_ROOT = '/var/www/panel-stub';

    private const NGINX_CONF = '/etc/nginx/conf.d/00-panel-stub.conf';

    private MarzbanService $marzbanService;

    public function __construct(MarzbanService $marzbanService)
    {
        $this->marzbanService = $marzbanService;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function apply(Server $server, bool $include123Rar): array
    {
        if (empty($server->login) || $server->password === null || $server->password === '') {
            return ['success' => false, 'message' => 'Укажите логин и пароль SSH в карточке сервера.'];
        }
        if (empty($server->ip)) {
            return ['success' => false, 'message' => 'Не задан IP сервера.'];
        }

        $indexPath = base_path('deploy/stub-assets/index.html');
        $nginxPath = base_path('deploy/nginx/panel-stub.default-server.conf');
        if (! is_readable($indexPath) || ! is_readable($nginxPath)) {
            return ['success' => false, 'message' => 'В проекте нет deploy/stub-assets/index.html или deploy/nginx/panel-stub.default-server.conf.'];
        }

        $server->decoy_stub_include_123_rar = $include123Rar;
        $server->save();

        $dto = ServerFactory::fromEntity($server);
        $oldTimeout = 100000;
        $ssh = null;
        try {
            $ssh = $this->marzbanService->connectSshAdapter($dto);
            $oldTimeout = $ssh->getTimeout() ?? 100000;
            $ssh->setTimeout(300);
            $sftp = new SFTP($dto->ip, $dto->ssh_port ?? 22);
            if (! $sftp->login($dto->login, $dto->password)) {
                throw new RuntimeException('SFTP: не удалось войти');
            }

            $nginxBin = $this->resolveNginxBinary($ssh);
            if ($nginxBin === null) {
                throw new RuntimeException(
                    'На хосте (по SSH) не найден исполняемый nginx. Установите пакет в ОС, например: '
                    .'Debian/Ubuntu: `sudo apt update && sudo apt install -y nginx`, затем: `ls -la /usr/sbin/nginx`. '
                    .'Если панель только в Docker и на ВМ нет системного nginx — эта кнопка не подойдёт, пока на хост не поставлен nginx (или иные ручные шаги).'
                );
            }

            $ssh->exec('mkdir -p '.escapeshellarg(self::STUB_ROOT).' /etc/nginx/ssl 2>&1');
            if (! $sftp->put(self::STUB_ROOT.'/index.html', $indexPath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new RuntimeException('Не удалось загрузить index.html');
            }
            $ssh->exec('chmod 644 '.escapeshellarg(self::STUB_ROOT.'/index.html').' 2>&1');

            if ($include123Rar) {
                $out = $ssh->exec('dd if=/dev/zero of='.escapeshellarg(self::STUB_ROOT.'/123.rar').' bs=1M count=15 status=none 2>&1 && chmod 644 '.escapeshellarg(self::STUB_ROOT.'/123.rar'));
                if ($ssh->getExitStatus() !== 0) {
                    throw new RuntimeException('dd 123.rar: '.Str::limit($out, 800));
                }
            } else {
                $ssh->exec('rm -f '.escapeshellarg(self::STUB_ROOT.'/123.rar').' 2>/dev/null; true');
            }

            if (! $sftp->put('/tmp/00-panel-stub.conf', $nginxPath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new RuntimeException('Не удалось загрузить конфиг nginx');
            }
            $mv = $ssh->exec(
                'mv -f /tmp/00-panel-stub.conf '.escapeshellarg(self::NGINX_CONF)
                .' && chmod 644 '.escapeshellarg(self::NGINX_CONF).' 2>&1'
            );
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException('Нет прав записи в /etc/nginx (нужен root): '.Str::limit($mv, 800));
            }

            $sslOut = trim((string) $ssh->exec(
                'if test -f /etc/nginx/ssl/panel-stub.crt && test -f /etc/nginx/ssl/panel-stub.key; then echo ok; else '
                .'openssl req -x509 -nodes -days 3650 -newkey rsa:2048 '
                .'-keyout /etc/nginx/ssl/panel-stub.key -out /etc/nginx/ssl/panel-stub.crt '
                .'-subj "/CN=localhost" 2>&1; fi'
            ));
            if ($ssh->getExitStatus() !== 0 && ! str_contains($sslOut, 'writing') && $sslOut !== 'ok') {
                throw new RuntimeException('openssl: '.Str::limit($sslOut, 800));
            }

            $nt = trim((string) $ssh->exec(escapeshellarg($nginxBin).' -t 2>&1'));
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException('nginx -t: '.Str::limit($nt, 800));
            }
            $re = trim((string) $ssh->exec('( systemctl reload nginx 2>&1 || service nginx reload 2>&1 )'));
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException('reload nginx: '.Str::limit($re, 800));
            }

            $server->decoy_stub_last_applied_at = now();
            $server->decoy_stub_last_message = 'Заглушка применена (default_server 80/443).';
            $server->save();

            Log::info('Decoy stub applied', ['server_id' => $server->id, 'include_123' => $include123Rar]);

            return ['success' => true, 'message' => 'Готово: заглушка на 80/443, конфиг '.self::NGINX_CONF.'. Проверьте запрос к IP.'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $server->decoy_stub_last_message = 'Ошибка: '.$msg;
            $server->save();
            Log::error('Decoy stub failed', ['server_id' => $server->id, 'error' => $msg]);

            return ['success' => false, 'message' => $msg];
        } finally {
            if ($ssh !== null) {
                $ssh->setTimeout($oldTimeout);
            }
        }
    }

    /**
     * В неинтерактивной SSH-сессии PATH часто пустой. Задаём типичный PATH и перебираем известные пути.
     *
     * @return non-empty-string|null
     */
    private function resolveNginxBinary(SSH2 $ssh): ?string
    {
        $script = <<<'BASH'
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
for p in \
  /usr/sbin/nginx \
  /usr/bin/nginx \
  /usr/local/nginx/sbin/nginx \
  /usr/local/openresty/nginx/sbin/nginx
do
  if [ -x "$p" ]; then
    printf %s "$p"
    exit 0
  fi
done
c=$(command -v nginx 2>/dev/null)
if [ -n "$c" ] && { [ -x "$c" ] || command -v "$c" >/dev/null 2>&1; }; then
  printf %s "$c"
  exit 0
fi
exit 1
BASH;

        $out = trim((string) $ssh->exec($script));
        if ($out === '' || $ssh->getExitStatus() !== 0) {
            return null;
        }

        return $out;
    }
}
