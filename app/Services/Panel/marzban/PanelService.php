<?php

namespace App\Services\Panel\marzban;

use App\Dto\Panel\PanelDto;
use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\External\MarzbanAPI;
use Illuminate\Support\Facades\Storage;
use phpseclib\Net\SFTP;
use phpseclib\Net\SSH2;

class PanelService
{
    public function sslConnection()
    {
        $host = '89.110.107.231'; // после создания сервера будет хост
        $username = 'root'; // всегда root
        $password = 'M3bn27MgetHPCh4J'; // после создания сервера

        $command = 'date'; // тест команда

        $settingFile = 'cat /opt/marzban/.env'; //переделать на переход по пути

        $ssh = new SSH2($host);
        $sftp = new SFTP($host);
        if (!$ssh->login($username, $password)) {
            $output = 'Connection failed';
        } else {
//            $output = $ssh->exec($firstCommand);
//            $output = $ssh->exec($secondCommand);
//            $result = Storage::disk('sftp')->get('/opt/marzban/.env');
//            $stream = fopen('ssh2.sftp://' . intval($ssh) . '/opt/marzban/.env', 'r');
            $output = $ssh->exec($settingFile);
        }

        if (str_contains($ssh->exec('stat /opt/marzban/.env'), 'No such file'))
            $result = 'FILE NOT FOUND';
        else
            $result = 'FILE ISSET';

        dd($result);
//
//        $test = $ssh->exec('stat /opt/marzban/.eng');
//        dd($test);
//
//        if ($ssh->exec('! -f /opt/marzban/.env '))

//        $output = json_decode($output, true);

//        dd($output);

//        preg_match_all('/[^\s"]+|"[^"]*"/', $output, $matches);

//        $regex = '~"[^"]*"(*SKIP)(*F)|\s+~';
//        $subject = 'hola hola "pepsi cola" yay';
//        $replaced = preg_replace($regex,"",$output);
//
//        dd($replaced);

//        $sftp = $sftp->login($username, $password);
//        $stream = file_exists("ssh2.sftp://$sftp/opt/marzban/.env");

//        $cmd = 'if test -d "/opt/marzban/.env"; then echo 1; fi';
//        $stream = $ssh->exec($cmd);
//        dd($stream);

//        if ($ssh->exec('test -f /opt/marzban/.env'))
//            dd('EXIST');
//        else
//            dd('NNOOOOTT');

        $outputs = explode("\n", $output);
        $result = [];
        foreach ($outputs as $output) {
            $formate = str_replace(array("'", '"'), ' ', $output);
            $formate = preg_replace('/\s+/', '', $formate);

            $data = explode("=", $formate);
            if (isset($data[1]))
                $result[$data[0]] = $data[1];
        }

        dd($result['UVICORN_HOST']);

//        dd($output);

//        $ssh->disconnect();
//        $outputDisconnect = $ssh->exec($command);
//
//        dd($outputDisconnect);
    }

    public function updatePanelData()
    {
        $server = ServerFactory::fromEntity();

        $server->panel_adress = 'address';
        $server->panel_login = 'login';
        $server->panel_password = 'password';
        $server->panel_key = 'key';

        $server->panel_status = 'Обновлена';
    }

    public function createPanel()
    {
        $command = 'command'; // тест команды для поднятия панели

        $ssh = self::connectServer(); // подключаемся к серверу
        $ssh->exec($command); // выполняем команду


    }

    //Подключаемся к серверу с помощью имеющихся данных
    public function connectServer()
    {
        $server = ServerFactory::fromEntity();

        $host = $server->host;
        $username = $server->login;
        $password = $server->password;

        $server->panel_status = 'Создана'; // сделать статус только созданной панели
    }


    public function panelAPI()
    {
        $vdsinaApi = new MarzbanAPI();

        return $vdsinaApi->createUser();
    }


    //адаптер для подключения по SSH, если норм - вынести в отделыный класс для работы по SSH
    public function connectSshAdapter(ServerDto $serverDto)
    {
        $ssh_connect = new SSH2($serverDto->ip);

        //переделать
        if (!$ssh_connect->login($serverDto->login, $serverDto->password)) {
            $output = 'SSH connection failed';
        } else {
            $output = $ssh_connect;
        }

        return $output;
    }

    //создание панели marzban
    public function create(int $server_id)
    {
        //по ID получить сервер на котором будем поднимать панель
        $server = Server::query()->where('id', $server_id)->first();

        //команды для установки панели на сервер
        $firstCommand = 'wget https://raw.githubusercontent.com/mozaroc/bash-hooks/main/install_marzban.sh';
//        dd($firstCommand);
        $secondCommand = 'chmod +x install_marzban.sh';
        // TODO -- слишком долго, что делать с командой?
//        $thirdCommand = './install_marzban.sh ' . $server->host;
//        dd($thirdCommand);

        //коннект по SSH
//        $ssh_connect = new SSH2($server->ip);

        $ssh_connect = self::connectSshAdapter(ServerFactory::fromEntity($server));
//dd($server->ip);
//        $ssh_connect = new SSH2($server->ip);

//        if (!$ssh_connect->login($server->login, $server->password)) {
//            $output = 'SSH connection failed';
//        } else {
//            $output = $ssh_connect->exec('date');
//        }
//        dd($output);
//        dd($ssh_connect->exec('date'));
        $ssh_connect->exec($firstCommand);
        $ssh_connect->exec($secondCommand);
        // TODO -- слишком долго, что делать с командой?
//        $ssh_connect->exec($thirdCommand);

        $panel = new PanelDto();

        $panel->server_id = $server->id;
        $panel->panel = Panel::MARZBAN;
        $panel->panel_status = Panel::PANEL_CREATED;

        Panel::create($panel->createArray());

        return $panel;
    }

    public function cronStatus()
    {
        $panels = Panel::query()->where('panel_status', Panel::PANEL_CREATED)->get();
        echo 'START check status for ' . count($panels) . ' panels' . PHP_EOL;

        foreach ($panels as $panel) {
            $status = self::panelStatus($panel->server_id);
            if ($status) {
                self::update($panel->id);
                echo 'Panel id=' . $panel->id . ' data update' . PHP_EOL;
            } else {
                echo 'Panel id=' . $panel->id . ' status new' . PHP_EOL;
            }
        }

        echo 'FINISH check status ' . count($panels) . ' panels' . PHP_EOL;
    }

    //метод для проверки установки панели, для продолжения обновления данных
    public function panelStatus(int $server_id)
    {
        $server = Server::query()->where('id', $server_id)->first();
        $ssh_connect = self::connectSshAdapter(ServerFactory::fromEntity($server));
        //сомнительный флаг установки
        return (str_contains($ssh_connect->exec('stat /opt/marzban/.env'), 'No such file')) ? false : true;

//        if (str_contains($ssh->exec('stat /opt/marzban/.env'), 'No such file'))
//            $result = false;
//        else
//            $result = true;
        //проверить наличие файла /opt/marzban/.env
    }

    //обновление JWT токена для работы API Marzban
    public function updateMarzbanToken(string $username, string $password, string $host)
    {
        $vdsinaApi = new MarzbanAPI($host);
        $auth_token = $vdsinaApi->getToken($username, $password);

        return $auth_token;
    }

    //обновление оставшихся данных для работы с панелью
    public function update(int $panel_id)
    {
        //перейти до файла конфигов и обновить данные в БД, поменять статусы
        $panel = Panel::query()->where('id', $panel_id)->first();
        $server = Server::query()->where('id', $panel->server_id)->first();
        $ssh_connect = self::connectSshAdapter(ServerFactory::fromEntity($server));

        $settingFile = 'cat /opt/marzban/.env';
        $output = $ssh_connect->exec($settingFile);

        //достаем данные из файла .env и записываем массив данных
        $outputs = explode("\n", $output);
        $data = [];
        foreach ($outputs as $output) {
            $formate = str_replace(array("'", '"'), ' ', $output);
            $formate = preg_replace('/\s+/', '', $formate);

            $result = explode("=", $formate);
            if (isset($result[1]))
                $data[$result[0]] = $result[1];
        }

        $auth_token = self::updateMarzbanToken($data['SUDO_USERNAME'], $data['SUDO_PASSWORD'], $data['XRAY_SUBSCRIPTION_URL_PREFIX']);

        $panel->panel_adress = $data['XRAY_SUBSCRIPTION_URL_PREFIX'];
        $panel->panel_login = $data['SUDO_USERNAME'];
        $panel->panel_password = $data['SUDO_PASSWORD'];
        $panel->panel_key = 'panel_key';
        $panel->auth_token = $auth_token;
        $panel->token_died_time = time() - 85400; //время жизни токена, что бы не расшифровывать jwt?

        $panel->save();

        return $panel;

//        if ((is_null($panel->auth_token)) || $panel->token_died_time > time()) {
//
//        } else {
//
//        }

    }
}
