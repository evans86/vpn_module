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
 * Заглушка Nginx (default_server) на 80/443: сначала nginx в ОС, иначе контейнер Docker с образом nginx/openresty
 * (Marzban и др. — часто без бинарника на хосте).
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

            $hostNginx = $this->resolveNginxBinary($ssh);
            if ($hostNginx !== null) {
                $this->applyOnHostNginx($ssh, $sftp, $indexPath, $nginxPath, $include123Rar, $hostNginx);
            } else {
                $docker = $this->resolveDockerNginxContext($ssh);
                if ($docker === null) {
                    throw $this->notFoundException();
                }
                $this->applyInDockerNginx(
                    $ssh,
                    $sftp,
                    $indexPath,
                    $nginxPath,
                    $include123Rar,
                    $docker['container_id'],
                    $docker['docker_argv']
                );
            }

            $server->decoy_stub_last_applied_at = now();
            $server->decoy_stub_last_message = 'Заглушка применена (default_server 80/443).';
            $server->save();

            Log::info('Decoy stub applied', ['server_id' => $server->id, 'include_123' => $include123Rar]);

            return ['success' => true, 'message' => 'Готово: заглушка на 80/443. Проверьте запрос к IP.'];
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

    private function notFoundException(): RuntimeException
    {
        return new RuntimeException(
            'Нет nginx в ОС и нет Docker-контейнера с образом nginx/openresty. '
            .'Marzban на 80/443 часто отдаёт Caddy, а не nginx — кнопка не сможет выставить эту nginx-заглушку сама. '
            .'Варианты: apt install nginx на хост (если порты 80/443 свободны), отдельный контейнер nginx, либо вручную в том, что слушает 80/443.'
        );
    }

    private function applyOnHostNginx(
        SSH2 $ssh,
        SFTP $sftp,
        string $indexPath,
        string $nginxPath,
        bool $include123Rar,
        string $nginxBin
    ): void {
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
    }

    /**
     * @param  list<string>  $dockerArgv  например [ 'docker' ] или [ 'sudo', 'docker' ]
     */
    private function applyInDockerNginx(
        SSH2 $ssh,
        SFTP $sftp,
        string $indexPath,
        string $nginxPath,
        bool $include123Rar,
        string $containerId,
        array $dockerArgv
    ): void {
        $dc = $this->shellJoinEscaped($dockerArgv);
        $tmp = '/tmp/panel-stub-'.bin2hex(random_bytes(4));

        $ssh->exec('mkdir -p '.escapeshellarg($tmp).' 2>&1');
        if (! $sftp->put($tmp.'/index.html', $indexPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException('Не удалось загрузить index.html (этап /tmp)');
        }
        if (! $sftp->put($tmp.'/00-panel-stub.conf', $nginxPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException('Не удалось загрузить конфиг nginx (этап /tmp)');
        }

        $c = escapeshellarg($containerId);
        $mkdir = $dc.' exec '.$c.' sh -c '.escapeshellarg(
            'mkdir -p /var/www/panel-stub /etc/nginx/ssl /etc/nginx/conf.d'
        );
        $mout = (string) $ssh->exec($mkdir.' 2>&1');
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('docker exec (mkdir): '.Str::limit($mout, 600));
        }

        $cp1 = $dc.' cp '.escapeshellarg($tmp.'/index.html')." {$c}:/var/www/panel-stub/index.html";
        $c1 = (string) $ssh->exec($cp1.' 2>&1');
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('docker cp index.html: '.Str::limit($c1, 600));
        }

        $cp2 = $dc.' cp '.escapeshellarg($tmp.'/00-panel-stub.conf')." {$c}:".self::NGINX_CONF;
        $ssh->exec($cp2.' 2>&1');
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('docker cp config: сбой (нужен доступ к docker: группа docker или root).');
        }

        if ($include123Rar) {
            $dd = $dc.' exec '.$c.' sh -c '.escapeshellarg(
                'dd if=/dev/zero of=/var/www/panel-stub/123.rar bs=1M count=15 status=none && chmod 644 /var/www/panel-stub/123.rar'
            );
            $dout = (string) $ssh->exec($dd.' 2>&1');
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException('dd 123.rar в контейнере: '.Str::limit($dout, 800));
            }
        } else {
            $ssh->exec($dc.' exec '.$c.' rm -f /var/www/panel-stub/123.rar 2>/dev/null; true');
        }

        $ssl = $dc.' exec '.$c.' sh -c '.escapeshellarg(
            'if test -f /etc/nginx/ssl/panel-stub.crt && test -f /etc/nginx/ssl/panel-stub.key; then exit 0; fi; '
            .'openssl req -x509 -nodes -days 3650 -newkey rsa:2048 '
            .'-keyout /etc/nginx/ssl/panel-stub.key -out /etc/nginx/ssl/panel-stub.crt -subj "/CN=localhost"'
        );
        $sslOut = trim((string) $ssh->exec($ssl.' 2>&1'));
        if ($ssh->getExitStatus() !== 0 && ! str_contains($sslOut, 'writing')) {
            throw new RuntimeException('openssl в контейнере: '.Str::limit($sslOut, 800));
        }

        $nt = $this->dockerNginxTestCmd($dc, $c);
        $ntOut = trim((string) $ssh->exec($nt.' 2>&1'));
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('nginx -t (контейнер): '.Str::limit($ntOut, 800));
        }

        $reloadInner = 'export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"; '
            .'if command -v nginx >/dev/null 2>&1; then nginx -s reload;'
            .' elif [ -x /usr/sbin/nginx ]; then /usr/sbin/nginx -s reload; else nginx -s reload; fi';
        $reload = $dc.' exec '.$c.' sh -c '.escapeshellarg($reloadInner);
        $rOut = trim((string) $ssh->exec($reload.' 2>&1'));
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('nginx reload (контейнер): '.Str::limit($rOut, 800));
        }

        $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');
    }

    private function dockerNginxTestCmd(string $dc, string $cArg): string
    {
        $inner = 'export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"; '
            .'if command -v nginx >/dev/null 2>&1; then nginx -t;'
            .' elif [ -x /usr/sbin/nginx ]; then /usr/sbin/nginx -t;'
            .' else nginx -t; fi';

        return $dc.' exec '.$cArg.' sh -c '.escapeshellarg($inner);
    }

    /**
     * @param  list<string>  $argv
     */
    private function shellJoinEscaped(array $argv): string
    {
        $parts = [];
        foreach ($argv as $a) {
            $parts[] = escapeshellarg($a);
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{container_id: string, docker_argv: list<string>}|null
     */
    private function resolveDockerNginxContext(SSH2 $ssh): ?array
    {
        $script = <<<'BASH'
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
if docker info >/dev/null 2>&1; then
  MODE=direct
  LINE=$(docker ps --no-trunc --format '{{.ID}}|{{.Image}}' 2>/dev/null | grep -iE 'nginx|openresty' | head -1)
elif command -v sudo >/dev/null 2>&1 && sudo -n docker info >/dev/null 2>&1; then
  MODE=sudo
  LINE=$(sudo docker ps --no-trunc --format '{{.ID}}|{{.Image}}' 2>/dev/null | grep -iE 'nginx|openresty' | head -1)
else
  exit 1
fi
if [ -z "$LINE" ]; then
  exit 1
fi
CID=${LINE%%|*}
echo "$MODE"
echo "$CID"
BASH;

        $out = trim((string) $ssh->exec('bash -lc '.escapeshellarg($script)));
        if ($ssh->getExitStatus() !== 0 || $out === '') {
            return null;
        }
        $lines = preg_split('/\R/', $out, -1, PREG_SPLIT_NO_EMPTY);
        if (count($lines) < 2) {
            return null;
        }
        $mode = $lines[0];
        $containerId = $lines[1];
        if (! preg_match('/^[a-f0-9]{4,64}$/i', $containerId)) {
            return null;
        }
        if ($mode === 'direct') {
            return ['container_id' => $containerId, 'docker_argv' => ['docker']];
        }
        if ($mode === 'sudo') {
            return ['container_id' => $containerId, 'docker_argv' => ['sudo', 'docker']];
        }

        return null;
    }

    /**
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
