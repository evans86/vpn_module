<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Services\Panel\PanelStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StatisticsCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistics:cron';

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
        try {
            $this->info("Statistics update start");

            /**
             * @var Panel $panel
             */
            $panel = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED)->first();
            $strategy = new PanelStrategy($panel->panel);
            $strategy->getServerStats();

            $this->info('Statistics update completed');
        } catch (\Exception $e) {
            Log::error('Statistics command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'cron'
            ]);

            $this->error("Command failed: {$e->getMessage()}");
            return 1;
        } catch (GuzzleException $e) {
            Log::error('Statistics command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'cron'
            ]);
        }
        return 0;
    }
}
