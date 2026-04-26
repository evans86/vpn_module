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
 * Заглушка 80/443: nginx в ОС → docker nginx → docker Caddy (Marzban).
 */
class ServerDecoyStubService
{
    private const STUB_ROOT = '/var/www/panel-stub';

    private const NGINX_CONF = '/etc/nginx/conf.d/00-panel-stub.conf';

    private const DECOY_CADDY_MARKER = '# DECOY_STUB_ADMIN_IMPORT';

    /** @var string префикс export PATH в удалённых bash-скриптах (snap, полный /usr/bin) */
    private const DOCKER_BASH_PROLOG = <<<'SH'
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/snap/bin"
# Явно подхватить docker, если в неинтерактивной сессии пустой PATH
if ! command -v docker >/dev/null 2>&1; then
  for try in /usr/bin/docker /snap/bin/docker; do
    [ -x "$try" ] && { export PATH="${try%/*}:$PATH"; break; }
  done
fi
SH;

    private MarzbanService $marzbanService;

    /** @var string доп. текст к успеху (частичное применение Caddy) */
    private string $caddyPostscript = '';

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

            $this->caddyPostscript = '';
            $hostNginx = $this->resolveNginxBinary($ssh);
            if ($hostNginx !== null) {
                $this->applyOnHostNginx($ssh, $sftp, $indexPath, $nginxPath, $include123Rar, $hostNginx);
            } else {
                $docker = $this->resolveDockerNginxContext($ssh);
                if ($docker !== null) {
                    $this->applyInDockerNginx(
                        $ssh,
                        $sftp,
                        $indexPath,
                        $nginxPath,
                        $include123Rar,
                        $docker['container_id'],
                        $docker['docker_argv']
                    );
                } else {
                    $caddy = $this->resolveDockerCaddyContext($ssh);
                    if ($caddy === null) {
                        throw $this->notFoundException($ssh);
                    }
                    $caddyImport = base_path('deploy/caddy/panel-stub-import.caddy');
                    if (! is_readable($caddyImport)) {
                        throw new RuntimeException('В проекте нет deploy/caddy/panel-stub-import.caddy');
                    }
                    $this->applyInDockerCaddy(
                        $ssh,
                        $sftp,
                        $indexPath,
                        $caddyImport,
                        $include123Rar,
                        $caddy['container_id'],
                        $caddy['docker_argv']
                    );
                }
            }

            $server->decoy_stub_last_applied_at = now();
            $baseMsg = 'Готово: заглушка на 80/443. Проверьте запрос к IP.';
            if ($this->caddyPostscript !== '') {
                $baseMsg .= ' '.$this->caddyPostscript;
            }
            $this->caddyPostscript = '';
            $server->decoy_stub_last_message = $baseMsg;
            $server->save();

            Log::info('Decoy stub applied', ['server_id' => $server->id, 'include_123' => $include123Rar]);

            return ['success' => true, 'message' => $baseMsg];
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

    private function notFoundException(SSH2 $ssh): RuntimeException
    {
        return new RuntimeException(
            'Автозаглушка не сработала: нет подходящего nginx в ОС и не найден запущенный Docker-контейнер с образом '
            .'nginx / openresty / caddy (или `docker` недоступен этому SSH-пользователю). '
            .$this->buildNoStubHint($ssh)
        );
    }

    private function buildNoStubHint(SSH2 $ssh): string
    {
        $probeBash = self::DOCKER_BASH_PROLOG.<<<'BASH'
D=""
if command -v docker >/dev/null 2>&1; then D=$(command -v docker)
elif [ -x /usr/bin/docker ]; then D=/usr/bin/docker
elif [ -x /snap/bin/docker ]; then D=/snap/bin/docker; fi
if [ -n "$D" ]; then
  echo "docker_path=$D"
  "$D" info 2>&1 | head -2
  echo "--- running ---"
  "$D" ps --no-trunc --format 'status={{.Status}}\tname={{.Names}}\timage={{.Image}}' 2>&1 | head -15
  echo "--- all images (first 12) ---"
  "$D" ps -a --no-trunc --format '{{.Image}}' 2>&1 | head -12
else
  echo "no_docker_cli"
  id; groups
fi
BASH;
        $raw = trim((string) $ssh->exec('bash -lc '.escapeshellarg($probeBash)));
        if ($raw === '') {
            $raw = '(пусто)';
        }

        return 'Снимок: '.Str::limit(str_replace(["\r\n", "\n"], ' | ', $raw), 1000)
            .' | Нужен запущенный контейнер (в docker ps с образом, где встречается nginx, openresty или caddy) и права: root или группа docker. Либо apt install nginx на хост (если 80/443 свободны).';
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

    private function resolveDockerNginxContext(SSH2 $ssh): ?array
    {
        return $this->resolveFirstRunningContainer($ssh, 'nginx|openresty');
    }

    private function resolveDockerCaddyContext(SSH2 $ssh): ?array
    {
        return $this->resolveFirstRunningContainer($ssh, 'caddy');
    }

    /**
     * @param  string  $pattern  расширенное REGEXP для grep -iE (подставляем только из кода, не от пользователя)
     * @return array{container_id: string, docker_argv: list<string>}|null
     */
    private function resolveFirstRunningContainer(SSH2 $ssh, string $pattern): ?array
    {
        $g = escapeshellarg($pattern);
        $script = self::DOCKER_BASH_PROLOG."\n"
            ."LINE=''\nMODE=''\n"
            ."if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then\n"
            ."  MODE=direct\n"
            ."  LINE=\$(docker ps --no-trunc --format '{{.ID}}|{{.Image}}' 2>/dev/null | grep -iE $g | head -1)\n"
            ."elif [ -x /usr/bin/docker ] && /usr/bin/docker info >/dev/null 2>&1; then\n"
            ."  MODE=direct\n"
            ."  LINE=\$(/usr/bin/docker ps --no-trunc --format '{{.ID}}|{{.Image}}' 2>/dev/null | grep -iE $g | head -1)\n"
            ."elif [ -x /snap/bin/docker ] && /snap/bin/docker info >/dev/null 2>&1; then\n"
            ."  MODE=direct\n"
            ."  LINE=\$(/snap/bin/docker ps --no-trunc --format '{{.ID}}|{{.Image}}' 2>/dev/null | grep -iE $g | head -1)\n"
            ."elif command -v sudo >/dev/null 2>&1 && sudo -n docker info >/dev/null 2>&1; then\n"
            ."  MODE=sudo\n"
            ."  LINE=\$(sudo docker ps --no-trunc --format '{{.ID}}|{{.Image}}' 2>/dev/null | grep -iE $g | head -1)\n"
            ."elif command -v sudo >/dev/null 2>&1 && [ -x /usr/bin/docker ] && sudo -n /usr/bin/docker info >/dev/null 2>&1; then\n"
            ."  MODE=sudo\n"
            ."  LINE=\$(sudo /usr/bin/docker ps --no-trunc --format '{{.ID}}|{{.Image}}' 2>/dev/null | grep -iE $g | head -1)\n"
            ."else\n"
            ."  exit 1\n"
            ."fi\n"
            .'[ -n "$LINE" ] || exit 1'."\n"
            .'CID=${LINE%%|*}'."\n"
            .'echo "$MODE"'."\n"
            .'echo "$CID"';

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
     * @param  list<string>  $dockerArgv
     */
    private function applyInDockerCaddy(
        SSH2 $ssh,
        SFTP $sftp,
        string $indexPath,
        string $caddyImportPath,
        bool $include123Rar,
        string $containerId,
        array $dockerArgv
    ): void {
        $dc = $this->shellJoinEscaped($dockerArgv);
        $c = escapeshellarg($containerId);
        $tmp = '/tmp/panel-caddy-'.bin2hex(random_bytes(4));

        $ssh->exec('mkdir -p '.escapeshellarg($tmp).' 2>&1');
        if (! $sftp->put($tmp.'/index.html', $indexPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException('Не удалось загрузить index.html (Caddy /tmp)');
        }
        if (! $sftp->put($tmp.'/panel-stub-import.caddy', $caddyImportPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException('Не удалось залить panel-stub-import.caddy');
        }
        $readme = base_path('deploy/caddy/MARZBAN-DECOY.txt');
        if (is_readable($readme)) {
            $sftp->put($tmp.'/MARZBAN-DECOY.txt', $readme, SFTP::SOURCE_LOCAL_FILE);
        }

        $mout = (string) $ssh->exec($dc.' exec '.$c.' sh -c '.escapeshellarg('mkdir -p /var/www/panel-stub').' 2>&1');
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('docker exec mkdir (Caddy): '.Str::limit($mout, 500));
        }
        $c1 = (string) $ssh->exec($dc.' cp '.escapeshellarg($tmp.'/index.html')." {$c}:/var/www/panel-stub/index.html 2>&1");
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('docker cp index (Caddy): '.Str::limit($c1, 500));
        }
        if ($include123Rar) {
            $dout = (string) $ssh->exec(
                $dc.' exec '.$c.' sh -c '.escapeshellarg(
                    'dd if=/dev/zero of=/var/www/panel-stub/123.rar bs=1M count=15 status=none && chmod 644 /var/www/panel-stub/123.rar'
                ).' 2>&1'
            );
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException('dd 123.rar (Caddy): '.Str::limit($dout, 500));
            }
        } else {
            $ssh->exec($dc.' exec '.$c.' rm -f /var/www/panel-stub/123.rar 2>/dev/null; true');
        }

        $mainFind = (string) $ssh->exec(
            $dc.' exec '.$c.' sh -c '.escapeshellarg('for f in /etc/caddy/Caddyfile /config/Caddyfile; do [ -f "$f" ] && echo "$f" && exit 0; done; exit 1').' 2>&1'
        );
        if ($ssh->getExitStatus() !== 0 || $mainFind === '') {
            $this->caddyPostscript = 'Статика в /var/www/panel-stub в контейнере caddy, но Caddyfile не найден (ожидали /etc/caddy/Caddyfile или /config/Caddyfile).';
            $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');

            return;
        }
        $main = trim($mainFind);
        $dir = rtrim((string) preg_replace('#[/\\\\][^/\\\\]*$#', '', $main), '/');
        $frag = ($dir !== '' ? $dir : '/etc/caddy').'/panel-stub-import.caddy';
        $readmeInContainer = '';

        $x = (string) $ssh->exec(
            $dc.' cp '.escapeshellarg($tmp.'/panel-stub-import.caddy').' '.$c.':'.escapeshellarg($frag).' 2>&1'
        );
        if ($ssh->getExitStatus() !== 0) {
            $this->caddyPostscript = 'Статика залита; не удалось docker cp Caddy-фрагмента: '.Str::limit($x, 400);
            $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');

            return;
        }
        if (is_readable($readme)) {
            $readmeInContainer = ($dir !== '' ? $dir : '/config').'/MARZBAN-DECOY.txt';
            $ssh->exec(
                $dc.' cp '.escapeshellarg($tmp.'/MARZBAN-DECOY.txt').' '.$c.':'.escapeshellarg($readmeInContainer).' 2>&1'
            );
        }

        $appendBash = 'set -e'."\n"
            .'M='.escapeshellarg(self::DECOY_CADDY_MARKER)."\n"
            .'MAIN='.escapeshellarg($main)."\n"
            .'FRAG='.escapeshellarg($frag)."\n"
            .'grep -qF "$M" "$MAIN" 2>/dev/null || { printf "\n%s\n" "$M" >> "$MAIN" && printf "import %s\n" "$FRAG" >> "$MAIN"; }'."\n";
        $localSh = (string) tempnam(sys_get_temp_dir(), 'caddy-a-');
        file_put_contents($localSh, $appendBash, LOCK_EX);
        if (! $sftp->put($tmp.'/append-caddy.sh', $localSh, SFTP::SOURCE_LOCAL_FILE)) {
            @unlink($localSh);
            throw new RuntimeException('SFTP: не удалось залить append-caddy.sh');
        }
        @unlink($localSh);
        $ssh->exec('chmod 755 '.escapeshellarg($tmp.'/append-caddy.sh').' 2>&1');
        $aout = (string) $ssh->exec(
            $dc.' cp '.escapeshellarg($tmp.'/append-caddy.sh')." {$c}:/tmp/append-caddy.sh 2>&1"
        );
        if ($ssh->getExitStatus() !== 0) {
            $this->caddyPostscript = 'Статика залита; не удалось docker cp append-скрипта: '.Str::limit($aout, 400);
            $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');

            return;
        }
        $aout = (string) $ssh->exec($dc.' exec '.$c.' sh /tmp/append-caddy.sh 2>&1');
        $appendOk = $ssh->getExitStatus() === 0;
        $ssh->exec($dc.' exec '.$c.' rm -f /tmp/append-caddy.sh 2>/dev/null; true');
        if (! $appendOk) {
            $this->caddyPostscript = 'Статика залита, append в Caddyfile не выполнен: '.Str::limit($aout, 500);
            $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');

            return;
        }

        $val = (string) $ssh->exec(
            $dc.' exec '.$c.' caddy validate --config '.escapeshellarg($main).' 2>&1'
        );
        if ($ssh->getExitStatus() !== 0) {
            $importLine = 'import '.$frag;
            $rollBash = 'set -e'."\n"
                .'MAIN='.escapeshellarg($main)."\n"
                .'L1='.escapeshellarg(self::DECOY_CADDY_MARKER)."\n"
                .'L2='.escapeshellarg($importLine)."\n"
                .'TMP=/tmp/CF-rollback.$$'."\n"
                .'grep -Fvx "$L1" "$MAIN" | grep -Fvx "$L2" > "$TMP" && mv "$TMP" "$MAIN"'."\n";
            $localRb = (string) tempnam(sys_get_temp_dir(), 'caddy-r-');
            file_put_contents($localRb, $rollBash, LOCK_EX);
            if (! $sftp->put($tmp.'/rollback-caddy.sh', $localRb, SFTP::SOURCE_LOCAL_FILE)) {
                @unlink($localRb);
            } else {
                @unlink($localRb);
                $ssh->exec('chmod 755 '.escapeshellarg($tmp.'/rollback-caddy.sh').' 2>&1');
                $ssh->exec($dc.' cp '.escapeshellarg($tmp.'/rollback-caddy.sh')." {$c}:/tmp/rollback-caddy.sh 2>&1");
                $ssh->exec($dc.' exec '.$c.' sh /tmp/rollback-caddy.sh 2>&1');
                $ssh->exec($dc.' exec '.$c.' rm -f /tmp/rollback-caddy.sh 2>/dev/null; true');
            }
            $help = $readmeInContainer !== '' ? 'См. в контейнере: '.$readmeInContainer : 'См. deploy/caddy/MARZBAN-DECOY.txt';
            $this->caddyPostscript = 'Статика в /var/www/panel-stub. caddy validate не прошёл (часто конфликт :80/:443 с доменом Marzban) — import откатан. '.Str::limit($val, 500).' '.$help;

            $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');

            return;
        }

        $rl = (string) $ssh->exec(
            $dc.' exec '.$c.' caddy reload --config '.escapeshellarg($main).' 2>&1'
        );
        if ($ssh->getExitStatus() !== 0) {
            $this->caddyPostscript = 'Caddyfile применён (validate ok), reload сбой: '.Str::limit($rl, 400);
        }

        $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');
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
