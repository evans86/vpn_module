<?php

namespace App\Console\Commands;

use App\Services\Server\ServerStrategyFactory;
use App\Services\Panel\PanelStrategyFactory;
use App\Models\Server\Server;
use App\Models\Panel\Panel;
use Illuminate\Console\Command;

class TestRefactoringCommand extends Command
{
    protected $signature = 'test:refactoring';
    protected $description = 'Тестирование рефакторинга архитектуры';

    public function handle(): int
    {
        $this->info('🧪 Тестирование рефакторинга архитектуры');
        $this->line(str_repeat('=', 60));
        $this->newLine();

        $errors = [];
        $success = [];

        // Тест 1: ServerStrategyFactory
        $this->info('1️⃣ Тест ServerStrategyFactory');
        try {
            $factory = new ServerStrategyFactory();
            
            if ($factory->isProviderSupported(Server::VDSINA)) {
                $success[] = 'ServerStrategyFactory поддерживает VDSINA';
                $this->line('   ✓ VDSINA поддерживается');
            } else {
                $errors[] = 'ServerStrategyFactory не поддерживает VDSINA';
                $this->error('   ✗ VDSINA не поддерживается');
            }
            
            try {
                $strategy = $factory->create(Server::VDSINA);
                $success[] = 'ServerStrategyFactory успешно создает стратегию';
                $this->line('   ✓ Стратегия для VDSINA создана успешно');
            } catch (\Exception $e) {
                $errors[] = 'Ошибка создания стратегии: ' . $e->getMessage();
                $this->error('   ✗ Ошибка: ' . $e->getMessage());
            }
            
            try {
                $factory->create('unknown_provider');
                $errors[] = 'Не выброшено исключение для неизвестного провайдера';
                $this->error('   ✗ Не выброшено исключение');
            } catch (\DomainException $e) {
                $success[] = 'Корректно обрабатывает неизвестный провайдер';
                $this->line('   ✓ Корректно обработано исключение');
            }
        } catch (\Exception $e) {
            $errors[] = 'Критическая ошибка: ' . $e->getMessage();
            $this->error('   ✗ Критическая ошибка: ' . $e->getMessage());
        }

        $this->newLine();

        // Тест 2: PanelStrategyFactory
        $this->info('2️⃣ Тест PanelStrategyFactory');
        try {
            $factory = new PanelStrategyFactory();
            
            if ($factory->isPanelTypeSupported(Panel::MARZBAN)) {
                $success[] = 'PanelStrategyFactory поддерживает MARZBAN';
                $this->line('   ✓ MARZBAN поддерживается');
            } else {
                $errors[] = 'PanelStrategyFactory не поддерживает MARZBAN';
                $this->error('   ✗ MARZBAN не поддерживается');
            }
            
            try {
                $strategy = $factory->create(Panel::MARZBAN);
                $success[] = 'PanelStrategyFactory успешно создает стратегию';
                $this->line('   ✓ Стратегия для MARZBAN создана успешно');
                
                if ($strategy instanceof \App\Services\Panel\PanelInterface) {
                    $success[] = 'Стратегия реализует PanelInterface';
                    $this->line('   ✓ Стратегия реализует PanelInterface');
                } else {
                    $errors[] = 'Стратегия не реализует PanelInterface';
                    $this->error('   ✗ Стратегия не реализует PanelInterface');
                }
                
                if (method_exists($strategy, 'updateToken')) {
                    $success[] = 'Метод updateToken присутствует';
                    $this->line('   ✓ Метод updateToken присутствует');
                } else {
                    $errors[] = 'Метод updateToken отсутствует';
                    $this->error('   ✗ Метод updateToken отсутствует');
                }
            } catch (\Exception $e) {
                $errors[] = 'Ошибка создания стратегии: ' . $e->getMessage();
                $this->error('   ✗ Ошибка: ' . $e->getMessage());
            }
            
            try {
                $factory->create('unknown_panel');
                $errors[] = 'Не выброшено исключение для неизвестного типа';
                $this->error('   ✗ Не выброшено исключение');
            } catch (\DomainException $e) {
                $success[] = 'Корректно обрабатывает неизвестный тип';
                $this->line('   ✓ Корректно обработано исключение');
            }
        } catch (\Exception $e) {
            $errors[] = 'Критическая ошибка: ' . $e->getMessage();
            $this->error('   ✗ Критическая ошибка: ' . $e->getMessage());
        }

        $this->newLine();

        // Тест 3: Обратная совместимость ServerStrategy
        $this->info('3️⃣ Тест обратной совместимости ServerStrategy');
        try {
            $strategy = new \App\Services\Server\ServerStrategy(Server::VDSINA);
            
            if (isset($strategy->strategy) && $strategy->strategy instanceof \App\Services\Server\ServerInterface) {
                $success[] = 'ServerStrategy работает через фабрику';
                $this->line('   ✓ ServerStrategy работает корректно');
            } else {
                $errors[] = 'ServerStrategy не создал стратегию';
                $this->error('   ✗ ServerStrategy не создал стратегию');
            }
        } catch (\Exception $e) {
            $errors[] = 'Ошибка в ServerStrategy: ' . $e->getMessage();
            $this->error('   ✗ Ошибка: ' . $e->getMessage());
        }

        $this->newLine();

        // Тест 4: Обратная совместимость PanelStrategy
        $this->info('4️⃣ Тест обратной совместимости PanelStrategy');
        try {
            $strategy = new \App\Services\Panel\PanelStrategy(Panel::MARZBAN);
            
            if (isset($strategy->strategy) && $strategy->strategy instanceof \App\Services\Panel\PanelInterface) {
                $success[] = 'PanelStrategy работает через фабрику';
                $this->line('   ✓ PanelStrategy работает корректно');
                
                if (method_exists($strategy, 'updateToken')) {
                    $success[] = 'PanelStrategy имеет метод updateToken';
                    $this->line('   ✓ Метод updateToken доступен');
                } else {
                    $errors[] = 'PanelStrategy не имеет метод updateToken';
                    $this->error('   ✗ Метод updateToken отсутствует');
                }
            } else {
                $errors[] = 'PanelStrategy не создал стратегию';
                $this->error('   ✗ PanelStrategy не создал стратегию');
            }
        } catch (\Exception $e) {
            $errors[] = 'Ошибка в PanelStrategy: ' . $e->getMessage();
            $this->error('   ✗ Ошибка: ' . $e->getMessage());
        }

        $this->newLine();

        // Итоги
        $this->line(str_repeat('=', 60));
        $this->info('📊 Итоги тестирования:');
        $this->newLine();

        if (count($success) > 0) {
            $this->info('✅ Успешные тесты (' . count($success) . '):');
            foreach ($success as $msg) {
                $this->line("   ✓ $msg");
            }
            $this->newLine();
        }

        if (count($errors) > 0) {
            $this->error('❌ Ошибки (' . count($errors) . '):');
            foreach ($errors as $msg) {
                $this->line("   ✗ $msg");
            }
            $this->newLine();
            return 1;
        } else {
            $this->info('🎉 Все тесты пройдены успешно!');
            return 0;
        }
    }
}
