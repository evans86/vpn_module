<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpiredKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expired:check-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private KeyActivateService $keyActivateService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        KeyActivateService $keyActivateService
    )
    {
        parent::__construct();
        $this->keyActivateService = $keyActivateService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $keys = KeyActivate::where('status', KeyActivate::PAID)
                ->where('deleted_at', '<', now())
                ->get();

            $this->info("Found {$keys->count()} keys to check");

            foreach ($keys as $key) {
                try {
                    $this->info("Checking key {$key->id} ");

                    $key = $this->keyActivateService->checkAndUpdateStatus($key);

                    $this->info("Key {$key->id} status: {$key->getStatusText()}");
                } catch (\Exception $e) {
                    Log::error('Error checking Key status', [
                        'server_id' => $key->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $this->error("Error checking server {$key->id}: {$e->getMessage()}");
                }
            }

        } catch (\Exception $e) {
            Log::error('Keys expired check command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("Command failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
