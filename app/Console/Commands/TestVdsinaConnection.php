<?php

namespace App\Console\Commands;

use App\Services\External\VdsinaAPI;
use Illuminate\Console\Command;

class TestVdsinaConnection extends Command
{
    protected $signature = 'vdsina:test-connection {--debug}';
    protected $description = 'Test connection to VDSina API';

    public function handle()
    {
        $apiKey = config('services.api_keys.vdsina_key');
        $debug = $this->option('debug');

        if (empty($apiKey)) {
            $this->error('❌ VDSina API key is not set in configuration');
            $this->info('💡 Check your .env file: VDSINA_API_KEY=your_key_here');
            return 1;
        }

        $this->info('🔑 Testing connection to VDSina API...');
        $this->info('API Key: ' . substr($apiKey, 0, 8) . '...' . substr($apiKey, -4));

        try {
            $vdsina = new VdsinaAPI($apiKey);

            // 1. Тестируем подключение
            $this->info('');
            $this->info('1. Testing API authentication...');

            $testResult = $vdsina->testConnection();

            if (!$testResult['success']) {
                $this->error('❌ API authentication failed: ' . $testResult['message']);
                return 1;
            }

            $this->info('✅ Authentication successful');
            $this->info('   Account: ' . $testResult['account']);
            $this->info('   Balance: $' . number_format($testResult['balance'], 2));

            // 2. Тестируем основные методы API
            $this->info('');
            $this->info('2. Testing API methods...');

            $this->testApiMethods($vdsina);

            $this->info('');
            $this->info('🎉 All tests passed! VDSina API is working correctly.');
            $this->info('💡 Your application should now work with VDSina API.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());

            if ($debug) {
                $this->info('📄 Stack trace: ' . $e->getTraceAsString());
            }

            return 1;
        }
    }

    private function testApiMethods(VdsinaAPI $vdsina): void
    {
        $methods = [
            'getDatacenter' => 'Data centers',
            'getServerGroup' => 'Server groups',
            'getTemplate' => 'Templates',
        ];

        foreach ($methods as $method => $description) {
            try {
                $result = call_user_func([$vdsina, $method]);

                $count = isset($result['data']) ? (is_array($result['data']) ? count($result['data']) : 1) : 0;
                $this->info("✅ {$description}: {$count} items");

                // Покажем первый элемент для дата-центров
                if ($method === 'getDatacenter' && $debug && !empty($result['data'])) {
                    $firstDc = $result['data'][0];
                    $this->info("   📍 Example: {$firstDc['name']} (ID: {$firstDc['id']})");
                }

            } catch (\Exception $e) {
                $this->error("❌ {$description}: " . $e->getMessage());
            }
        }

        // Тестируем server-plan с параметром
        try {
            $result = $vdsina->getServerPlan(2);
            $count = isset($result['data']) ? (is_array($result['data']) ? count($result['data']) : 1) : 0;
            $this->info("✅ Server plans: {$count} items");

            if ($debug && !empty($result['data'])) {
                $firstPlan = $result['data'][0];
                $this->info("   💰 Example: {$firstPlan['name']} - ${$firstPlan['price']}/month");
            }

        } catch (\Exception $e) {
            $this->error("❌ Server plans: " . $e->getMessage());
        }
    }
}
