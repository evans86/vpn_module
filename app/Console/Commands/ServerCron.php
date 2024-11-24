<?php

namespace App\Console\Commands;

use App\Services\Telegram\ModuleBot\FatherBotController;
use Illuminate\Console\Command;

class ServerCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
//        $serverStrategy = new ServerStrategy(Server::VDSINA);
//        $serverStrategy->checkStatus();

        $serverTG = new FatherBotController();
        $serverTG->notifyTelegram('test');
        return 0;
    }
}
