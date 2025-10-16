<?php

namespace App\Console\Commands;

use App\Services\External\VdsinaAPI;
use Illuminate\Console\Command;

class TestVdsinaConnection extends Command
{
    protected $signature = 'vdsina:test-connection {--debug} {--test-auth}';
    protected $description = 'Test connection to VDSina API with detailed diagnostics';

    public function handle()
    {
        $apiKey = config('services.api_keys.vdsina_key');
        $debug = $this->option('debug');
        $testAuth = $this->option('test-auth');

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
            $vdsina = new VdsinaAPI($apiKey);

            // Если запрошен тест методов аутентификации
            if ($testAuth) {
                return $this->testAllAuthMethods($vdsina);
            }

            // 1. Тестируем API с автоматическим подбором аутентификации
            $this->info('');
            $this->info('1. Testing API authentication (auto-detection)...');

            $testResult = $vdsina->testConnection();

            if (!$testResult['success']) {
                $this->error('❌ API authentication failed: ' . $testResult['message']);

                if ($debug) {
                    $this->info('📄 Error: ' . $testResult['error']);
                }

                $this->info('');
                $this->info('🔄 Trying to detect correct authentication method...');
                $this->testAllAuthMethods($vdsina);

                return 1;
            }

            $this->info('✅ Authentication successful');
            $this->info('   Account: ' . $testResult['account']);

            // 2. Тестируем основные методы API
            $this->info('');
            $this->info('2. Testing API methods...');

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

    private function testAllAuthMethods(VdsinaAPI $vdsina): int
    {
        $this->info('');
        $this->info('🔐 Testing all authentication methods...');

        $results = $vdsina->testAuthMethods();

        $hasSuccess = false;

        foreach ($results as $method => $result) {
            if ($result['success']) {
                $this->info("✅ {$method}: SUCCESS - " . ($result['status_msg'] ?? 'Authenticated'));
                $hasSuccess = true;
            } else {
                $error = $result['error'] ?? ($result['status_msg'] ?? 'Unknown error');
                $this->error("❌ {$method}: FAILED - {$error}");
            }
        }

        if (!$hasSuccess) {
            $this->info('');
            $this->error('💥 All authentication methods failed!');
            $this->suggestAuthSolutions();
            return 1;
        }

        $this->info('');
        $this->info('✅ Found working authentication method!');
        return 0;
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

            } catch (\Exception $e) {
                $this->error("❌ {$description}: " . $e->getMessage());
            }
        }

        // Отдельно тестируем server-plan с параметром
        try {
            $result = $vdsina->getServerPlan(2);
            $count = isset($result['data']) ? (is_array($result['data']) ? count($result['data']) : 1) : 0;
            $this->info("✅ Server plans: {$count} items");
        } catch (\Exception $e) {
            $this->error("❌ Server plans: " . $e->getMessage());
        }
    }

    private function suggestAuthSolutions(): void
    {
        $this->info('');
        $this->info('🔧 Possible solutions for authentication issues:');
        $this->info('   • 🔑 Verify API key in VDSina panel is active');
        $this->info('   • 📋 Check that API key has all necessary permissions');
        $this->info('   • 🔄 Generate a new API key');
        $this->info('   • 👀 Ensure key is copied correctly (no spaces, no quotes)');
        $this->info('   • 🌐 Try accessing API through different network (VPN)');
        $this->info('   • 📞 Contact VDSina support for correct authentication format');
        $this->info('');
        $this->info('💡 Try creating a new API key with different permissions');
    }
}
