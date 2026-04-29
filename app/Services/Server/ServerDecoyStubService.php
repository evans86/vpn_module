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

    /** CGI-скрипт внутри DOCUMENT_ROOT (fcgiwrap/nginx ожидают согласованные пути). */
    private const HOST_TEST_SPEED_SCRIPT = self::STUB_ROOT.'/panel-stub-test-speed';

    private const HOST_TEST_SPEED_SNIPPET = '/etc/nginx/snippets/panel-stub-test-speed.inc';

    private const HOST_TEST_SPEED_TOKEN = self::STUB_ROOT.'/.test-speed-token';

    private const DECOY_CADDY_MARKER = '# DECOY_STUB_ADMIN_IMPORT';

    /** @var string префикс: один export PATH, без if/for (совместимость с /bin/sh, без CRLF-ловушек) */
    private const DOCKER_BASH_PROLOG = <<<'SH'
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/bin:/usr/sbin:/sbin:/snap/bin"
SH;

    private MarzbanService $marzbanService;

    /** @var string доп. текст к успеху (частичное применение Caddy) */
    private string $caddyPostscript = '';

    /** @var string заметки об авто-установке nginx в ОС */
    private string $provisionNote = '';

    public function __construct(MarzbanService $marzbanService)
    {
        $this->marzbanService = $marzbanService;
    }

    /**
     * Многострочный скрипт на удалённом Linux через base64; исполняет bash,
     * не posix sh (dash), иначе подстановки вида ${var:-0} и часть синтаксиса ломаются.
     */
    private function execRemoteBashScript(SSH2 $ssh, string $script): string
    {
        $script = str_replace(["\r\n", "\r"], "\n", $script);
        $b64 = base64_encode($script);

        return (string) $ssh->exec('printf %s '.var_export($b64, true).' | base64 -d | /bin/bash 2>&1');
    }

    /**
     * Установить nginx через apt/yum/dnf если на хосте его ещё нет (права root из SSH).
     *
     * @return string|null текст для сообщения пользователю или null если пакетного менеджера нет
     */
    private function tryInstallNginxOnHost(SSH2 $ssh): ?string
    {
        $prevTimeout = (int) ($ssh->getTimeout() ?? 300);
        $ssh->setTimeout(900);

        try {
            $script = <<<'INSTALL'
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
set -e
if [ -x /usr/sbin/nginx ] || command -v nginx >/dev/null 2>&1; then
  echo ALREADY
  exit 0
fi
if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y nginx
elif command -v dnf >/dev/null 2>&1; then
  dnf install -y nginx
elif command -v yum >/dev/null 2>&1; then
  yum install -y nginx
else
  echo NO_PM >&2
  exit 42
fi
systemctl enable nginx 2>/dev/null || true
systemctl restart nginx 2>/dev/null || service nginx restart 2>/dev/null || true
echo DONE_NGINX
INSTALL;

            $raw = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->execRemoteBashScript($ssh, $script)));
            $exit = (int) $ssh->getExitStatus();

            if ($exit === 42 || str_contains($raw, 'NO_PM')) {
                return null;
            }

            if ($exit === 0 && str_contains($raw, 'ALREADY')) {
                return 'Nginx на хосте уже был; применяю только конфиг заглушки.';
            }

            if ($exit === 0 && str_contains($raw, 'DONE_NGINX')) {
                return 'Nginx установлен в ОС из админки (apt/dnf/yum), затем развёрнуты 80/443 и заглушка.';
            }

            Log::warning('Декоя: автоустановка nginx без ожидаемого вывода', [
                'exit' => $exit,
                'raw' => Str::limit($raw, 800),
            ]);

            return null;
        } finally {
            $ssh->setTimeout($prevTimeout);
        }
    }

    /**
     * @param  bool  $installHostNginxIfMissing  при отсутствии nginx — apt/dnf/yum (root), затем конфиг заглушки на хост
     *
     * @return array{success: bool, message: string}
     */
    public function apply(Server $server, bool $include123Rar, bool $installHostNginxIfMissing = true): array
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
            $this->provisionNote = '';
            $hostNginx = $this->resolveNginxBinary($ssh);
            if ($hostNginx === null && $installHostNginxIfMissing) {
                $nginxInstallOut = $this->tryInstallNginxOnHost($ssh);
                if ($nginxInstallOut !== null) {
                    $this->provisionNote = $nginxInstallOut;
                    $hostNginx = $this->resolveNginxBinary($ssh);
                }
            }
            $nginxStubBodyDocker = $this->nginxStubBody(true);
            $testSpeedTokenForDb = null;

            if ($hostNginx !== null) {
                $fcgiArtifacts = $this->deployHostOutboundTestSpeedFcgiArtifacts($ssh, $sftp);
                $omitTestSpeedMarkers = ($fcgiArtifacts === null);
                if ($omitTestSpeedMarkers) {
                    Log::info('Заглушка: без /test-speed (нет fcgiwrap или UNIX-сокета fcgi)');
                }
                if ($fcgiArtifacts !== null) {
                    $testSpeedTokenForDb = $fcgiArtifacts['token'];
                }
                $nginxStubBodyHost = $this->nginxStubBody($omitTestSpeedMarkers);
                $this->applyOnHostNginx($ssh, $sftp, $indexPath, $nginxStubBodyHost, $include123Rar, $hostNginx);
            } else {
                $docker = $this->resolveDockerNginxContext($ssh);
                if ($docker !== null) {
                    $this->applyInDockerNginx(
                        $ssh,
                        $sftp,
                        $indexPath,
                        $nginxStubBodyDocker,
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
                                $nginxStubBodyDocker,
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
            $prefixProv = $this->provisionNote !== '' ? $this->provisionNote.' ' : '';
            $baseMsg = 'Готово: заглушка развёрнута. '.$prefixProv.$probe;
            if ($this->caddyPostscript !== '') {
                $baseMsg .= ' '.$this->caddyPostscript;
            }
            $this->caddyPostscript = '';
            $server->decoy_stub_last_message = Str::limit($baseMsg, 2000);

            $tokenForDb = $hostNginx !== null
                ? $this->hydrateOutboundTestSpeedTokenFromRemoteIfMissing($ssh, $testSpeedTokenForDb)
                : null;
            $server->decoy_stub_test_speed_token = $tokenForDb;
            $server->save();

            Log::info('Decoy stub applied', ['server_id' => $server->id, 'include_123' => $include123Rar]);

            $this->provisionNote = '';

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

    /**
     * У Debian/Ubuntu второй блок default_server задаёт шаблон в sites-enabled/default;
     * наша заглушка лежит в conf.d → nginx -t сообщает duplicate default_server.
     * Выключение симлинка/файла и при наличии — снятие default_server со stock default.conf в conf.d/http.d.
     */
    private function suppressConflictingVanillaDefaultServer(SSH2 $ssh): void
    {
        $shell = <<<'SH'
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
for p in /etc/nginx/sites-enabled/default /etc/nginx/sites-enabled/default.conf; do
  [ ! -e "$p" ] && continue
  if [ -L "$p" ]; then rm -f "$p"; continue; fi
  if [ -f "$p" ]; then mv -f "$p" "${p}.disabled-by-panel-stub"; fi
done
for f in /etc/nginx/conf.d/default.conf /etc/nginx/http.d/default.conf; do
  [ ! -f "$f" ] && continue
  grep -qE '^[[:space:]]*listen[[:space:]].*default_server' "$f" || continue
  cp -a "$f" "${f}.bak-panelstub.$$" 2>/dev/null || true
  sed -i '/^[[:space:]]*listen /s/[[:space:]]*default_server//g' "$f" 2>/dev/null \
    || sed -i.bak '/^[[:space:]]*listen /s/[[:space:]]*default_server//g' "$f"
done
exit 0
SH;
        $this->execRemoteBashScript($ssh, $shell);
    }

    /**
     * Конфиг заглушки: при omit — убрать include /test-speed (Docker или нет fcgiwrap на хосте).
     *
     * @return non-empty-string
     */
    private function nginxStubBody(bool $omitTestSpeedMarkers): string
    {
        $path = base_path('deploy/nginx/panel-stub.default-server.conf');
        if (! is_readable($path)) {
            throw new RuntimeException('В проекте нет deploy/nginx/panel-stub.default-server.conf');
        }
        $raw = file_get_contents($path);
        $raw = is_string($raw) ? $raw : '';
        if ($omitTestSpeedMarkers) {
            $raw = (string) preg_replace('/#\s*HOST_TEST_SPEED_BEGIN.*?#\s*HOST_TEST_SPEED_END\s*\r?\n?/su', '', $raw);
        }
        if ($raw === '') {
            throw new RuntimeException('Пустой шаблон nginx заглушки');
        }

        return $raw;
    }

    /**
     * stdout установщика fcgiwrap: на старых логах apt/dnf могли печататься в начало строки —
     * берём последнюю строку, похожую на один абсолютный путь без пробелов (сокет).
     *
     * @return non-empty-string|null
     */
    private function parseFcgiSocketPathFromInstallerOutput(string $raw): ?string
    {
        $raw = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        if ($raw === '') {
            return null;
        }

        $candidates = [];
        foreach (preg_split("/\n/", $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '/') && ! str_contains($line, ' ')) {
                $candidates[] = $line;
            }
        }

        if ($candidates !== []) {
            $last = end($candidates);

            return $last !== false ? $last : null;
        }

        if (str_starts_with($raw, '/') && ! str_contains($raw, ' ') && strpos($raw, "\n") === false) {
            return $raw;
        }

        return null;
    }

    /**
     * fcgiwrap + скрипт + snippet + token для ?token= (только хостовый nginx).
     *
     * @return array{socket: string, token: string}|null
     */
    private function deployHostOutboundTestSpeedFcgiArtifacts(SSH2 $ssh, SFTP $sftp): ?array
    {
        $scriptLocal = base_path('deploy/stub-assets/panel-stub-test-speed.sh');
        if (! is_readable($scriptLocal)) {
            Log::warning('deploy/stub-assets/panel-stub-test-speed.sh не найден — /test-speed пропускается');

            return null;
        }

        $installer = <<<'SH'
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
set +e
if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq </dev/null >/dev/null 2>&1
  apt-get install -y fcgiwrap </dev/null >/dev/null 2>&1
elif command -v dnf >/dev/null 2>&1; then
  dnf install -y fcgiwrap >/dev/null 2>&1
elif command -v yum >/dev/null 2>&1; then
  yum install -y fcgiwrap >/dev/null 2>&1
elif command -v apk >/dev/null 2>&1; then
  apk add --no-cache fcgiwrap >/dev/null 2>&1
fi
(systemctl daemon-reload >/dev/null 2>&1) || true
(systemctl enable --now fcgiwrap.socket >/dev/null 2>&1) \
  || systemctl restart fcgiwrap >/dev/null 2>&1 \
  || service fcgiwrap restart >/dev/null 2>&1 \
  || service fcgiwrap start >/dev/null 2>&1 \
  || true
sleep 2
SOC=""
for i in 1 2 3 4 5 6 7 8 9 10; do
for c in /run/fcgiwrap.socket /var/run/fcgiwrap.socket /run/fcgiwrap/fcgiwrap.sock /run/fcgiwrap.sock /var/run/nginx/fcgiwrap.socket /run/nginx/fcgiwrap.socket /run/nginx/fcgiwrap.sock; do
  if [ -S "$c" ]; then SOC="$c"; break 2; fi
done
  sleep 1
done
if [ "$SOC" = "" ]; then
  SOC=$(find /run /var/run /usr/local/var/run -maxdepth 8 -type s -iname '*fcgiwrap*' ! -iname '*php*' 2>/dev/null | head -1)
fi
if [ "$SOC" = "" ]; then
  SOC=$(find /run /var/run -maxdepth 6 -type s \( -name 'wrap_fcgi.sock' -o -name 'fcgiwrap.sock' \) ! -iname '*php-fpm*' ! -iname '*fpm.sock*' 2>/dev/null | head -1)
fi
printf '%s' "$SOC"
exit 0
SH;

        $installerRawOut = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->execRemoteBashScript($ssh, $installer)));
        $sockOut = $this->parseFcgiSocketPathFromInstallerOutput($installerRawOut);
        if ($sockOut === null || ! str_starts_with($sockOut, '/')) {
            Log::warning('fcgiwrap: сокет не найден после установки', [
                'out' => Str::limit($installerRawOut, 500),
                'parsed' => $sockOut,
            ]);

            return null;
        }

        $ssh->exec('mkdir -p '.escapeshellarg(self::STUB_ROOT).' /etc/nginx/snippets 2>&1');
        $tmpSh = '/tmp/panel-stub-ts-'.bin2hex(random_bytes(4)).'.sh';
        if (! $sftp->put($tmpSh, $scriptLocal, SFTP::SOURCE_LOCAL_FILE)) {
            Log::warning('Не удалось залить panel-stub-test-speed.sh');

            return null;
        }
        $mvSh = $ssh->exec(
            'mv -f '.escapeshellarg($tmpSh).' '.escapeshellarg(self::HOST_TEST_SPEED_SCRIPT)
                .' && chmod 755 '.escapeshellarg(self::HOST_TEST_SPEED_SCRIPT)
                .' && rm -f /usr/local/sbin/panel-stub-test-speed 2>/dev/null || true'
                .' 2>&1'
        );
        if ($ssh->getExitStatus() !== 0) {
            Log::warning('Не удалось установить panel-stub-test-speed', ['out' => Str::limit($mvSh, 500)]);

            return null;
        }

        $token = bin2hex(random_bytes(24));
        $tokenEsc = escapeshellarg($token);
        $tokenPath = escapeshellarg(self::HOST_TEST_SPEED_TOKEN);
        $writeTok = trim((string) $ssh->exec(
            'printf %s '.$tokenEsc.' > '.$tokenPath.' 2>/dev/null'
            .' ; if getent passwd www-data >/dev/null 2>&1 && getent group www-data >/dev/null 2>&1; then '
            .'chown root:www-data '.$tokenPath.' 2>/dev/null; chmod 0640 '.$tokenPath.'; '
            .'else chmod 0644 '.$tokenPath.' 2>/dev/null; fi'
            .' ; test -s '.$tokenPath.' && echo ok'
        ));
        if (! str_contains($writeTok, 'ok')) {
            Log::warning('Не удалось записать .test-speed-token');

            return null;
        }

        // www-data должна иметь возможность прочесть token без world-readable если возможно
        $snippet = <<<'NGINX'
location = /test-speed {
    gzip off;
    default_type text/plain;
    charset utf-8;
    include fastcgi_params;
    fastcgi_param DOCUMENT_ROOT __STUB_ROOT__;
    fastcgi_param SCRIPT_NAME /test-speed;
    fastcgi_param GATEWAY_INTERFACE CGI/1.1;
    fastcgi_param SCRIPT_FILENAME __SCRIPT__;
    fastcgi_param QUERY_STRING $query_string;
    fastcgi_param REQUEST_METHOD $request_method;
    fastcgi_intercept_errors off;
    fastcgi_connect_timeout 15s;
    fastcgi_send_timeout 180s;
    fastcgi_read_timeout 180s;
    fastcgi_pass unix:__SOCK__;
}
NGINX;
        $snippet = str_replace(
            ['__SCRIPT__', '__SOCK__', '__STUB_ROOT__'],
            [self::HOST_TEST_SPEED_SCRIPT, $sockOut, self::STUB_ROOT],
            $snippet
        );

        $tmpSn = '/tmp/panel-stub-sn-'.bin2hex(random_bytes(4)).'.inc';
        $localSn = tempnam(sys_get_temp_dir(), 'pstub');
        if ($localSn === false) {
            return null;
        }
        file_put_contents($localSn, $snippet);
        $okPut = $sftp->put($tmpSn, $localSn, SFTP::SOURCE_LOCAL_FILE);
        @unlink($localSn);
        if (! $okPut) {
            Log::warning('Не удалось залить snippet test-speed');

            return null;
        }
        $mvSn = $ssh->exec('mv -f '.escapeshellarg($tmpSn).' '.escapeshellarg(self::HOST_TEST_SPEED_SNIPPET)
            .' && chmod 644 '.escapeshellarg(self::HOST_TEST_SPEED_SNIPPET).' 2>&1');
        if ($ssh->getExitStatus() !== 0) {
            Log::warning('Не удалось установить snippet', ['out' => Str::limit($mvSn, 400)]);

            return null;
        }

        $this->provisionNote = ($this->provisionNote !== '' ? $this->provisionNote.' ' : '')
            .'Исходящие: GET http(s)://<IP>/test-speed?token='.$token.' (см. curl -k).';

        return ['socket' => $sockOut, 'token' => $token];
    }

    /**
     * Сохраняем в БД токен после применения: если массив из deploy уже содержит токен —
     * он приоритетен; иначе читаем /var/www/panel-stub/.test-speed-token с VPS (бывает при
     * расхождениях SFTP/exec или когда файл создавался при прошлых попытках).
     */
    private function hydrateOutboundTestSpeedTokenFromRemoteIfMissing(SSH2 $ssh, ?string $tokenFromDeploy): ?string
    {
        if (is_string($tokenFromDeploy) && trim($tokenFromDeploy) !== '') {
            return trim($tokenFromDeploy);
        }

        $p = escapeshellarg(self::HOST_TEST_SPEED_TOKEN);
        $out = trim((string) $ssh->exec('[ -s '.$p.' ] && LANG=C LC_ALL=C tr -dc "0123456789abcdefABCDEF" < '.$p.' 2>/dev/null | head -c 96'));

        if ($out !== '' && preg_match('/^[a-fA-F0-9]{40,96}$/', $out)) {
            Log::info('Подтянут токен /test-speed с VPS в БД (файл .test-speed-token уже на диске)');

            return strtolower($out);
        }

        return null;
    }

    private function applyOnHostNginx(
        SSH2 $ssh,
        SFTP $sftp,
        string $indexPath,
        string $nginxConfBody,
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

        $localNginx = tempnam(sys_get_temp_dir(), 'stubng');
        if ($localNginx === false) {
            throw new RuntimeException('Нет временной директории для конфига nginx');
        }
        if (false === file_put_contents($localNginx, $nginxConfBody)) {
            @unlink($localNginx);
            throw new RuntimeException('Не удалось подготовить конфиг nginx');
        }
        $rnameNginx = '/tmp/panel-stub-'.bin2hex(random_bytes(8)).'.conf';

        if (! $sftp->put($rnameNginx, $localNginx, SFTP::SOURCE_LOCAL_FILE)) {
            @unlink($localNginx);
            throw new RuntimeException('Не удалось загрузить конфиг nginx');
        }
        @unlink($localNginx);
        $mv = $ssh->exec(
            'mv -f '.escapeshellarg($rnameNginx).' '.escapeshellarg(self::NGINX_CONF)
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

        $this->suppressConflictingVanillaDefaultServer($ssh);

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
        string $nginxConfBody,
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
        $localConf = tempnam(sys_get_temp_dir(), 'dockng');
        if ($localConf === false) {
            throw new RuntimeException('Нет временной директории для конфига nginx');
        }
        if (false === file_put_contents($localConf, $nginxConfBody)) {
            @unlink($localConf);
            throw new RuntimeException('Не удалось подготовить конфиг nginx');
        }
        if (! $sftp->put($tmp.'/00-panel-stub.conf', $localConf, SFTP::SOURCE_LOCAL_FILE)) {
            @unlink($localConf);
            throw new RuntimeException('Не удалось загрузить конфиг nginx (этап /tmp)');
        }
        @unlink($localConf);

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
        $script = <<<'ENDSCRIPT'
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
sleep 1
if command -v curl >/dev/null 2>&1; then
  H80=$(curl -sS --connect-timeout 5 -m 12 -o /dev/null -w "%{http_code}" http://127.0.0.1/ 2>/dev/null) || true
  H443=$(curl -sSk --connect-timeout 5 -m 12 -o /dev/null -w "%{http_code}" https://127.0.0.1/ 2>/dev/null) || true
  case "${H80}" in
    ''|000) H80='нет-ответа' ;;
    *) ;;
  esac
  case "${H443}" in
    ''|000) H443='нет-ответа' ;;
    *) ;;
  esac
else
  H80=nocurl
  H443=nocurl
fi
LN80=$(ss -tln 2>/dev/null | grep ':80 ' | wc -l | tr -cd '0-9')
LN443=$(ss -tln 2>/dev/null | grep ':443 ' | wc -l | tr -cd '0-9')
[ -z "$LN80" ] && LN80=0
[ -z "$LN443" ] && LN443=0
EXTRA=""
if [ "$LN80" = "0" ] && [ "$LN443" = "0" ]; then
  EXTRA=" Узел не слушает :80/:443 на этом хосте — publish Docker / ufw / SG провайдера или Marzban-only."
fi
if [ -f /etc/nginx/snippets/panel-stub-test-speed.inc ] && [ -s /var/www/panel-stub/.test-speed-token ]; then
  EXTRA="${EXTRA} Исходящие/check: GET /test-speed?token=<секрет с сервера> (см. текст панели)."
fi
echo "Проверка VPS: localhost HTTP=${H80} HTTPS=${H443}; ss LISTEN :80 строк=${LN80} :443 строк=${LN443}.${EXTRA}"

ENDSCRIPT;

        $out = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->execRemoteBashScript($ssh, $script)));
        if ($out === '') {
            return '(проверка localhost недоступна)';
        }

        return Str::limit($out, 1150);
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
