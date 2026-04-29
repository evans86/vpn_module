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

    /** @var string префикс: один export PATH, без if/for (совместимость с /bin/sh, без CRLF-ловушек) */
    private const DOCKER_BASH_PROLOG = <<<'SH'
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/bin:/usr/sbin:/sbin:/snap/bin"
SH;

    private MarzbanService $marzbanService;

    /** @var string доп. текст к успеху (частичное применение Caddy) */
    private string $caddyPostscript = '';

    public function __construct(MarzbanService $marzbanService)
    {
        $this->marzbanService = $marzbanService;
    }

    /**
     * Многострочный bash на удалённом Linux. На Windows при `bash -lc`.escapeshellarg() портятся
     * кавычки — сервер тогда пишет: syntax error: unexpected end of file.
     */
    private function execRemoteBashScript(SSH2 $ssh, string $script): string
    {
        $script = str_replace(["\r\n", "\r"], "\n", $script);
        $b64 = base64_encode($script);

        return (string) $ssh->exec('printf %s '.var_export($b64, true).' | base64 -d | /bin/sh 2>&1');
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
                    if ($caddy !== null) {
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
                    } else {
                        $goz = $this->resolveDockerGozargahMarzbanContext($ssh);
                        if ($goz === null) {
                            throw $this->notFoundException($ssh);
                        }
                        $proxy = $this->probeContainerHasCaddyOrNginx($ssh, $goz['container_id'], $goz['docker_argv']);
                        if ($proxy === 'CADDY') {
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
                                $goz['container_id'],
                                $goz['docker_argv']
                            );
                        } elseif ($proxy === 'NGINX') {
                            $this->applyInDockerNginx(
                                $ssh,
                                $sftp,
                                $indexPath,
                                $nginxPath,
                                $include123Rar,
                                $goz['container_id'],
                                $goz['docker_argv']
                            );
                        } else {
                            $this->applyInMarzbanContainerStaticOnly(
                                $ssh,
                                $sftp,
                                $indexPath,
                                $include123Rar,
                                $goz
                            );
                        }
                    }
                }
            }

            $probe = $this->verifyStubPortsFromRemote($ssh);

            $server->decoy_stub_last_applied_at = now();
            $baseMsg = 'Готово: заглушка развёрнута. '.$probe;
            if ($this->caddyPostscript !== '') {
                $baseMsg .= ' '.$this->caddyPostscript;
            }
            $this->caddyPostscript = '';
            $server->decoy_stub_last_message = Str::limit($baseMsg, 2000);
            $server->save();

            Log::info('Decoy stub applied', ['server_id' => $server->id, 'include_123' => $include123Rar]);

            return ['success' => true, 'message' => (string) $server->decoy_stub_last_message];
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
            'Автозаглушка не сработала: нет nginx в ОС, нет контейнера с образом nginx/openresty/caddy и нет gozargah/marzban '
            .'(либо `docker` недоступен этому SSH-пользователю). '
            .$this->buildNoStubHint($ssh)
        );
    }

    private function buildNoStubHint(SSH2 $ssh): string
    {
        $probeBash = self::DOCKER_BASH_PROLOG.<<<'BASH'
D=""
[ -x /usr/bin/docker ] && D="/usr/bin/docker"
[ -z "$D" ] && [ -x /snap/bin/docker ] && D="/snap/bin/docker"
[ -z "$D" ] && command -v docker >/dev/null 2>&1 && D=$(command -v docker)
if [ -n "$D" ]; then
  echo "docker_path=$D"
  "$D" info 2>&1 | head -2
  echo "--- running ---"
  "$D" ps --no-trunc --format "status={{.Status}}  name={{.Names}}  image={{.Image}}" 2>&1 | head -15
  echo "--- all images (first 12) ---"
  "$D" ps -a --no-trunc --format "{{.Image}}" 2>&1 | head -12
else
  echo "no_docker_cli"
  id; groups
fi
BASH;
        $raw = trim((string) $this->execRemoteBashScript($ssh, $probeBash));
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

        $out = trim((string) $this->execRemoteBashScript($ssh, $script));
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
     * Только ghcr.io/gozargah/marzban — без caddy в имени образа, но с одним контейнером панели.
     *
     * @return array{container_id: string, docker_argv: list<string>}|null
     */
    private function resolveDockerGozargahMarzbanContext(SSH2 $ssh): ?array
    {
        return $this->resolveFirstRunningContainer($ssh, 'gozargah/marzban');
    }

    /**
     * @param  list<string>  $dockerArgv
     * @return 'CADDY'|'NGINX'|'NONE'
     */
    private function probeContainerHasCaddyOrNginx(SSH2 $ssh, string $containerId, array $dockerArgv): string
    {
        $dc = $this->shellJoinEscaped($dockerArgv);
        $c = escapeshellarg($containerId);
        $out = trim((string) $ssh->exec(
            $dc.' exec '.$c.' sh -c '.escapeshellarg(
                'if command -v caddy >/dev/null 2>&1; then echo CADDY; '
                .'elif command -v nginx >/dev/null 2>&1; then echo NGINX; else echo NONE; fi'
            )
        ));
        if ($out === 'CADDY' || $out === 'NGINX' || $out === 'NONE') {
            return $out;
        }
        if (str_starts_with($out, 'CADDY')) {
            return 'CADDY';
        }
        if (str_starts_with($out, 'NGINX')) {
            return 'NGINX';
        }

        return 'NONE';
    }

    /**
     * Официальный образ gozargah/marzban — часто без caddy/nginx в $PATH, только Uvicorn; отдельный caddy в compose.
     *
     * @param  array{container_id: string, docker_argv: list<string>}  $ctx
     */
    private function applyInMarzbanContainerStaticOnly(
        SSH2 $ssh,
        SFTP $sftp,
        string $indexPath,
        bool $include123Rar,
        array $ctx
    ): void {
        $dc = $this->shellJoinEscaped($ctx['docker_argv']);
        $c = escapeshellarg($ctx['container_id']);
        $tmp = '/tmp/panel-mz-'.bin2hex(random_bytes(4));
        $ssh->exec('mkdir -p '.escapeshellarg($tmp).' 2>&1');
        if (! $sftp->put($tmp.'/index.html', $indexPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException('Не удалось залить index.html (Marzban)');
        }
        $mout = (string) $ssh->exec(
            $dc.' exec '.$c.' sh -c '.escapeshellarg('mkdir -p /var/www/panel-stub').' 2>&1'
        );
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('Marzban: mkdir: '.Str::limit($mout, 400));
        }
        $c1 = (string) $ssh->exec(
            $dc.' cp '.escapeshellarg($tmp.'/index.html')." {$c}:/var/www/panel-stub/index.html 2>&1"
        );
        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException('Marzban: docker cp index: '.Str::limit($c1, 400));
        }
        if ($include123Rar) {
            $dout = (string) $ssh->exec(
                $dc.' exec '.$c.' sh -c '.escapeshellarg(
                    'dd if=/dev/zero of=/var/www/panel-stub/123.rar bs=1M count=15 status=none && chmod 644 /var/www/panel-stub/123.rar'
                ).' 2>&1'
            );
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException('dd 123.rar: '.Str::limit($dout, 500));
            }
        } else {
            $ssh->exec($dc.' exec '.$c.' rm -f /var/www/panel-stub/123.rar 2>/dev/null; true');
        }
        $this->caddyPostscript = '[Частично] Контейнер gozargah/marzban без caddy/nginx: статика записана в /var/www/panel-stub внутри контейнера; с хоста :80/:443 могут быть пусты. '
            .'Откройте веб-доступ: `apt install nginx` на хосте (как даёт заглушка при наличии nginx в ОС), либо отдельный контейнер caddy/nginx с `ports: 80:80` и монтированием, либо docker-compose из доков Marzban с reverse-proxy.';

        $ssh->exec('rm -rf '.escapeshellarg($tmp).' 2>/dev/null; true');
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
     * С самой VPS: curl по localhost (не ping) и ss — видно ли LISTEN на 80/443.
     * Снаружи могут блокировать при рабущем icmp; здесь проверяем именно «веб» на ноде.
     */
    private function verifyStubPortsFromRemote(SSH2 $ssh): string
    {
        $script = self::DOCKER_BASH_PROLOG.<<<'BASH'
sleep 1
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
H80=""
H443=""
if command -v curl >/dev/null 2>&1; then
  H80=$(curl -sS --connect-timeout 5 -m 12 -o /dev/null -w "%{http_code}" http://127.0.0.1/ 2>/dev/null || printf 'fail')
  H443=$(curl -sSk --connect-timeout 5 -m 12 -o /dev/null -w "%{http_code}" https://127.0.0.1/ 2>/dev/null || printf 'fail')
else
  H80="nocurl"
  H443="nocurl"
fi
LN80=$(ss -tln 2>/dev/null | grep ':80 ' | wc -l | tr -d ' ')
LN443=$(ss -tln 2>/dev/null | grep ':443 ' | wc -l | tr -d ' ')
EXTRA=""
if [ "${LN80:-0}" = "0" ] && [ "${LN443:-0}" = "0" ]; then
  EXTRA=" Узел сам не принимает :80/:443 — проверьте publish Docker, ufw, Security Group провайдера; при Marzban-only см. текст [Частично] выше."
fi
printf 'Проверка на узле (не ping): HTTP/80 код=%s, HTTPS/443 код=%s; строки LISTEN ss :80=%s :443=%s.%s\n' "${H80}" "${H443}" "${LN80}" "${LN443}" "${EXTRA}"
BASH;

        $out = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->execRemoteBashScript($ssh, $script)));
        if ($out === '') {
            return '(проверка localhost недоступна)';
        }

        return Str::limit($out, 900);
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
