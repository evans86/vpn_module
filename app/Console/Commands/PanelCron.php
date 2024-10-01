<?php

namespace App\Console\Commands;

use App\Services\Panel\marzban\PanelService;
use Illuminate\Console\Command;

class PanelCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:cron';

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
        $panelService = new PanelService();
        $panelService->cronStatus();
        return 0;
    }
}
