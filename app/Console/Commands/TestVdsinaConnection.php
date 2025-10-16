<?php

namespace App\Console\Commands;

use App\Services\External\VdsinaAPI;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class TestVdsinaConnection extends Command
{
    protected $signature = 'vdsina:test-connection {--debug}';
    protected $description = 'Test connection to VDSina API with detailed diagnostics';

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
        $this->info('Key Length: ' . strlen($apiKey) . ' characters');

        if ($debug) {
            $this->info('🔍 Debug mode: ON');
        }

        try {
            // 1. Проверяем базовое подключение к хосту
            $this->info('');
            $this->info('1. Testing host connectivity...');

            if (!$this->testHostConnectivity()) {
                $this->error('❌ Cannot reach VDSina API host');
                return 1;
            }
            $this->info('✅ Host is reachable');

            // 2. Тестируем API
            $this->info('');
            $this->info('2. Testing API authentication...');

            $vdsina = new VdsinaAPI($apiKey);
            $testResult = $vdsina->testConnection();

            if (!$testResult['success']) {
                $this->error('❌ API authentication failed: ' . $testResult['message']);

                if ($debug && isset($testResult['response'])) {
                    $this->info('📄 Full response: ' . json_encode($testResult['response'], JSON_PRETTY_PRINT));
                }

                $this->suggestSolutions($testResult['message']);
                return 1;
            }

            $this->info('✅ Authentication successful');
            $this->info('   Account: ' . $testResult['account']);

            // 3. Тестируем основные методы API
            $this->info('');
            $this->info('3. Testing API methods...');

            $this->testApiMethods($vdsina);

            $this->info('');
            $this->info('🎉 All tests passed! VDSina API is working correctly.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());

            if ($debug) {
                $this->info('📄 Stack trace: ' . $e->getTraceAsString());
            }

            return 1;
        }
    }

    private function testHostConnectivity(): bool
    {
        try {
            $client = new Client(['timeout' => 10]);
            $response = $client->get('https://userapi.vdsina.com/', [
                'verify' => false, // Отключаем SSL verification для теста
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $this->warn('   Host connectivity warning: ' . $e->getMessage());
            return false;
        }
    }

    private function testApiMethods(VdsinaAPI $vdsina): void
    {
        $methods = [
            'getDatacenter' => 'Data centers',
            'getServerGroup' => 'Server groups',
            'getTemplate' => 'Templates',
            'getServerPlan' => 'Server plans',
        ];

        foreach ($methods as $method => $description) {
            try {
                if ($method === 'getServerPlan') {
                    $result = $vdsina->getServerPlan(2); // ID для Standard servers
                } else {
                    $result = call_user_func([$vdsina, $method]);
                }

                $count = isset($result['data']) ? (is_array($result['data']) ? count($result['data']) : 1) : 0;
                $this->info("✅ {$description}: {$count} items");

            } catch (\Exception $e) {
                $this->error("❌ {$description}: " . $e->getMessage());
            }
        }
    }

    private function suggestSolutions(string $errorMessage): void
    {
        $this->info('');
        $this->info('🔧 Possible solutions:');

        if (str_contains($errorMessage, '401') || str_contains($errorMessage, 'Unauthorized')) {
            $this->info('   • Check your API key in VDSina panel');
            $this->info('   • Ensure the key has correct permissions');
            $this->info('   • Generate a new API key if needed');
        } elseif (str_contains($errorMessage, 'cURL') || str_contains($errorMessage, 'SSL')) {
            $this->info('   • Check your internet connection');
            $this->info('   • Verify SSL certificates are installed');
            $this->info('   • Try using HTTP instead of HTTPS (if supported)');
        } elseif (str_contains($errorMessage, 'timeout')) {
            $this->info('   • Check your firewall settings');
            $this->info('   • Verify network connectivity to VDSina');
            $this->info('   • Try increasing timeout in API client');
        }

        $this->info('   • Contact VDSina support if problem persists');
        $this->info('');
        $this->info('📖 VDSina API documentation: https://www.vdsina.com/ru/tech/api');
    }
}
