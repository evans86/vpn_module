<?php

namespace App\Console\Commands;

use App\Services\External\VdsinaAPI;
use Illuminate\Console\Command;

class TestVdsinaConnection extends Command
{
    protected $signature = 'vdsina:test-connection';
    protected $description = 'Test connection to VDSina API';

    public function handle()
    {
        $apiKey = config('services.api_keys.vdsina_key');

        if (empty($apiKey)) {
            $this->error('❌ VDSina API key is not set in configuration');
            $this->info('Please check your .env file: VDSINA_API_KEY=your_key_here');
            return 1;
        }

        $this->info('🔑 Testing connection to VDSina API...');
        $this->info('API Key: ' . substr($apiKey, 0, 8) . '...' . substr($apiKey, -4));

        try {
            $vdsina = new VdsinaAPI($apiKey);

            // Тест базового подключения
            if (!$vdsina->testConnection()) {
                $this->error('❌ Basic connection test failed');
                return 1;
            }

            $this->info('✅ Basic connection successful!');

            // Тест получения данных
            $this->info('📊 Testing API methods...');

            // Аккаунт
            $account = $vdsina->getAccount();
            $this->info('✅ Account info: ' . ($account['data']['email'] ?? 'N/A'));

            // Дата-центры
            $datacenters = $vdsina->getDatacenter();
            $this->info('✅ Data centers: ' . count($datacenters['data'] ?? []));

            // Шаблоны
            $templates = $vdsina->getTemplate();
            $this->info('✅ Templates: ' . count($templates['data'] ?? []));

            // Группы серверов
            $serverGroups = $vdsina->getServerGroup();
            $this->info('✅ Server groups: ' . count($serverGroups['data'] ?? []));

            $this->info('🎉 All tests passed! VDSina API is working correctly.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Connection failed: ' . $e->getMessage());
            $this->info('💡 Possible solutions:');
            $this->info('   - Check your API key in .env file');
            $this->info('   - Verify the key has correct permissions');
            $this->info('   - Check your internet connection');
            $this->info('   - Contact VDSina support if problem persists');
            return 1;
        }
    }
}
