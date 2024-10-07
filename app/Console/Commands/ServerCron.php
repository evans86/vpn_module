<?php

namespace App\Console\Commands;

use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use App\Services\Server\vdsina\VdsinaService;
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
        $serverStrategy = new ServerStrategy(Server::VDSINA);
        $serverStrategy->checkStatus();
        return 0;
    }
}
