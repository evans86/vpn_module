<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DiagnoseArchitectureCommand extends Command
{
    protected $signature = 'architecture:diagnose
                            {--fix : Показать рекомендации по исправлению}
                            {--detailed : Подробный отчет}';

    protected $description = 'Диагностика архитектуры проекта для подготовки к новым типам серверов';

    private array $issues = [];
    private array $warnings = [];
    private array $info = [];

    public function handle(): int
    {
        $this->info('🔍 Диагностика архитектуры проекта...');
        $this->newLine();

        // Проверки
        $this->checkDirectDependencies();
        $this->checkHardcodedTypes();
        $this->checkSwitchCases();
        $this->checkInterfaceCompleteness();
        $this->checkRepositoryHardcoding();

        // Вывод результатов
        $this->displayResults();

        return 0;
    }

    /**
     * Проверка прямых зависимостей от конкретных реализаций
     */
    private function checkDirectDependencies(): void
    {
        $this->line('📋 Проверка прямых зависимостей...');

        $patterns = [
            'MarzbanService' => [
                'pattern' => '/app\([^)]*MarzbanService[^)]*\)/i',
                'message' => 'Прямой вызов MarzbanService через app()',
                'severity' => 'error',
                'exclude' => [
                    'PanelMarzbanStrategy.php', // Стратегия использует сервис через DI - это нормально
                    'DiagnoseArchitectureCommand.php', // Команда диагностики
                    'TestRefactoringCommand.php' // Команда тестирования
                ]
            ],
            'VdsinaService' => [
                'pattern' => '/app\([^)]*VdsinaService[^)]*\)/i',
                'message' => 'Прямой вызов VdsinaService через app()',
                'severity' => 'error',
                'exclude' => [
                    'ServerVdsinaStrategy.php', // Стратегия использует сервис через DI - это нормально
                    'DiagnoseArchitectureCommand.php', // Команда диагностики
                    'TestRefactoringCommand.php' // Команда тестирования
                ]
            ],
            'new MarzbanService' => [
                'pattern' => '/new\s+[^;\(]*MarzbanService\s*\(/i',
                'message' => 'Создание экземпляра MarzbanService через new',
                'severity' => 'error',
                'exclude' => [
                    'DiagnoseArchitectureCommand.php', // Команда диагностики
                    'TestRefactoringCommand.php' // Команда тестирования
                ]
            ],
            'new VdsinaService' => [
                'pattern' => '/new\s+[^;\(]*VdsinaService\s*\(/i',
                'message' => 'Создание экземпляра VdsinaService через new',
                'severity' => 'error',
                'exclude' => [
                    'DiagnoseArchitectureCommand.php', // Команда диагностики
                    'TestRefactoringCommand.php' // Команда тестирования
                ]
            ]
        ];

        $files = $this->getPhpFiles();

        // Исключаем команды диагностики и тестирования из проверки
        $excludedFiles = [
            'DiagnoseArchitectureCommand.php',
            'TestRefactoringCommand.php'
        ];

        foreach ($files as $file) {
            if (!File::exists($file)) {
                continue;
            }

            // Пропускаем исключенные файлы
            $fileName = basename($file);
            if (in_array($fileName, $excludedFiles)) {
                continue;
            }

            try {
                $content = File::get($file);
                foreach ($patterns as $type => $config) {
                    // Проверяем исключения
                    if (isset($config['exclude']) && in_array($fileName, $config['exclude'])) {
                        continue;
                    }

                    if (preg_match($config['pattern'], $content)) {
                        $this->addIssue($file, $config['message'], $config['severity']);
                    }
                }
            } catch (\Exception $e) {
                // Пропускаем файлы, которые не удалось прочитать
                continue;
            }
        }
    }

    /**
     * Проверка хардкода типов провайдеров и панелей
     */
    private function checkHardcodedTypes(): void
    {
        $this->line('📋 Проверка хардкода типов...');

        $patterns = [
            'Panel::MARZBAN' => [
                'pattern' => '/Panel::MARZBAN|Panel\s*::\s*MARZBAN/',
                'message' => 'Хардкод Panel::MARZBAN',
                'severity' => 'warning',
                'exclude' => [
                    'PanelStrategy.php',
                    'PanelSeeder.php',
                    'Panel.php',
                    'PanelStrategyFactory.php', // Фабрика - нормальное место для регистрации
                    'MarzbanService.php', // Сервис провайдера - нормальное место
                    'PanelMarzbanStrategy.php', // Стратегия - нормальное место
                    'DiagnoseArchitectureCommand.php', // Команда диагностики
                    'TestRefactoringCommand.php' // Команда тестирования
                ],
                'excludePatterns' => [
                    '/\\?\\?\s*Panel::MARZBAN/', // Fallback значения (?? Panel::MARZBAN)
                    '/->panel\s*\\?\\?\s*Panel::MARZBAN/', // Fallback в доступе к свойству ($panel->panel ?? Panel::MARZBAN)
                    '/=\s*Panel::MARZBAN\s*;.*\/\/.*по умолчанию/i', // Значения по умолчанию с комментарием
                    '/\\?\s*string\s+\$[^=]*=\s*null.*Panel::MARZBAN/', // Параметры с null по умолчанию
                    '/panelType\s*=\s*\$panelType\s*\\?\\?\s*Panel::MARZBAN/', // Присваивание с fallback
                ]
            ],
            'Server::VDSINA' => [
                'pattern' => '/Server::VDSINA|Server\s*::\s*VDSINA/',
                'message' => 'Хардкод Server::VDSINA',
                'severity' => 'warning',
                'exclude' => [
                    'ServerStrategy.php',
                    'ServerSeeder.php',
                    'ServerFactory.php',
                    'Server.php',
                    'ServerStrategyFactory.php', // Фабрика - нормальное место для регистрации
                    'VdsinaService.php', // Сервис провайдера - нормальное место
                    'ServerVdsinaStrategy.php', // Стратегия - нормальное место
                    'DiagnoseArchitectureCommand.php', // Команда диагностики
                    'TestRefactoringCommand.php' // Команда тестирования
                ],
                'excludePatterns' => [
                    '/\\?\\?\s*Server::VDSINA/', // Fallback значения (?? Server::VDSINA)
                    '/->provider\s*\\?\\?\s*Server::VDSINA/', // Fallback в доступе к свойству
                    '/=\s*Server::VDSINA\s*;.*\/\/.*по умолчанию/i', // Значения по умолчанию с комментарием
                    '/\\?\s*string\s+\$[^=]*=\s*null.*Server::VDSINA/', // Параметры с null по умолчанию
                    '/provider\s*=\s*\$provider\s*\\?\\?\s*Server::VDSINA/', // Присваивание с fallback
                ]
            ]
        ];

        $files = $this->getPhpFiles();

        // Исключаем команды диагностики и тестирования из проверки
        $excludedFiles = [
            'DiagnoseArchitectureCommand.php',
            'TestRefactoringCommand.php'
        ];

        foreach ($files as $file) {
            if (!File::exists($file)) {
                continue;
            }

            $fileName = basename($file);

            // Пропускаем исключенные файлы
            if (in_array($fileName, $excludedFiles)) {
                continue;
            }

            try {
                $content = File::get($file);

                foreach ($patterns as $type => $config) {
                    if (in_array($fileName, $config['exclude'])) {
                        continue;
                    }

                    // Проверяем исключающие паттерны (fallback значения и т.д.)
                    if (isset($config['excludePatterns'])) {
                        $shouldExclude = false;
                        foreach ($config['excludePatterns'] as $excludePattern) {
                            if (preg_match($excludePattern, $content)) {
                                $shouldExclude = true;
                                break;
                            }
                        }
                        if ($shouldExclude) {
                            continue;
                        }
                    }

                    if (preg_match($config['pattern'], $content)) {
                        $this->addIssue($file, $config['message'], $config['severity']);
                    }
                }
            } catch (\Exception $e) {
                // Пропускаем файлы, которые не удалось прочитать
                continue;
            }
        }
    }

    /**
     * Проверка switch-case в стратегиях
     */
    private function checkSwitchCases(): void
    {
        $this->line('📋 Проверка switch-case в стратегиях...');

        $strategyFiles = [
            app_path('Services/Server/ServerStrategy.php'),
            app_path('Services/Panel/PanelStrategy.php')
        ];

        foreach ($strategyFiles as $file) {
            if (!File::exists($file)) {
                continue;
            }

            try {
                $content = File::get($file);
                if (preg_match('/switch\s*\([^)]+\)\s*\{[^}]*case\s+/i', $content)) {
                    $this->addInfo($file, 'Используется switch-case для выбора стратегии. Рекомендуется использовать фабрику.');
                }
            } catch (\Exception $e) {
                // Пропускаем файлы, которые не удалось прочитать
                continue;
            }
        }
    }

    /**
     * Проверка полноты интерфейсов
     */
    private function checkInterfaceCompleteness(): void
    {
        $this->line('📋 Проверка полноты интерфейсов...');

        // Проверяем PanelInterface
        $panelInterface = app_path('Services/Panel/PanelInterface.php');
        $marzbanService = app_path('Services/Panel/marzban/MarzbanService.php');

        if (File::exists($panelInterface) && File::exists($marzbanService)) {
            try {
                $interfaceContent = File::get($panelInterface);
                $serviceContent = File::get($marzbanService);

                // Ищем публичные методы в MarzbanService
                preg_match_all('/public\s+function\s+(\w+)\s*\(/', $serviceContent, $serviceMethods);
                preg_match_all('/public\s+function\s+(\w+)\s*\(/', $interfaceContent, $interfaceMethods);

                $serviceMethodsList = $serviceMethods[1] ?? [];
                $interfaceMethodsList = $interfaceMethods[1] ?? [];

                // Методы, которые есть в сервисе, но могут отсутствовать в интерфейсе
                $missingMethods = array_diff($serviceMethodsList, $interfaceMethodsList);

                // Фильтруем служебные методы
                $missingMethods = array_filter($missingMethods, function($method) {
                    return !in_array($method, ['__construct', 'getArray', 'toArray']);
                });

            if (!empty($missingMethods)) {
                // Исключаем специфичные методы, которые не должны быть в интерфейсе
                $excludedMethods = [
                    'connectSshAdapter', // Специфичный метод для SSH подключения к серверу (не относится к панели)
                    '__construct', // Конструктор
                    'getArray', // Вспомогательные методы
                    'toArray', // Вспомогательные методы
                ];

                foreach ($missingMethods as $method) {
                    // Пропускаем исключенные методы
                    if (in_array($method, $excludedMethods)) {
                        continue;
                    }

                    // Проверяем, используется ли метод где-то напрямую
                    if ($this->isMethodUsedDirectly('MarzbanService', $method)) {
                        $this->addWarning(
                            $marzbanService,
                            "Метод {$method} используется напрямую, но отсутствует в PanelInterface"
                        );
                    }
                }
            }
            } catch (\Exception $e) {
                // Пропускаем, если не удалось прочитать файлы
                $this->warn("Не удалось проверить интерфейсы: " . $e->getMessage());
            }
        }
    }

    /**
     * Проверка хардкода в репозиториях
     */
    private function checkRepositoryHardcoding(): void
    {
        $this->line('📋 Проверка репозиториев...');

        $repositoryFiles = File::glob(app_path('Repositories/**/*Repository.php'));

        if (is_array($repositoryFiles)) {
            foreach ($repositoryFiles as $file) {
                if (!File::exists($file)) {
                    continue;
                }

                try {
                    $content = File::get($file);

                    // Проверяем только реальный хардкод в запросах, исключая:
                    // 1. Значения по умолчанию в параметрах методов (?? Panel::MARZBAN)
                    // 2. Fallback значения ($panel->panel ?? Panel::MARZBAN)
                    // 3. Значения по умолчанию в объявлениях параметров (?string $panelType = null)

                    // Паттерн ищет хардкод в запросах к БД или сравнениях
                    $hasHardcode = preg_match('/(?:->where\([\'"]panel[\'"]\s*,\s*Panel::MARZBAN|->where\([\'"]provider[\'"]\s*,\s*Server::VDSINA)/i', $content);

                    if ($hasHardcode) {
                        // Проверяем, не является ли это значением по умолчанию
                        $isDefaultValue = preg_match('/(?:\?\s*string\s+\$[^=]*=\s*(?:Panel::MARZBAN|Server::VDSINA)|\\?\\?\s*(?:Panel::MARZBAN|Server::VDSINA)|->panel\s*\\?\\?\s*(?:Panel::MARZBAN|Server::VDSINA))/', $content);

                        if (!$isDefaultValue) {
                            $this->addWarning($file, 'Репозиторий содержит хардкод типов провайдеров/панелей');
                        }
                    }
                } catch (\Exception $e) {
                    // Пропускаем файлы, которые не удалось прочитать
                    continue;
                }
            }
        }
    }

    /**
     * Проверка, используется ли метод напрямую
     */
    private function isMethodUsedDirectly(string $serviceClass, string $method): bool
    {
        $files = $this->getPhpFiles();
        $pattern = '/->\s*' . preg_quote($method, '/') . '\s*\(/';

        foreach ($files as $file) {
            if (!File::exists($file)) {
                continue;
            }

            try {
                $content = File::get($file);
                // Исключаем сам сервис и стратегию
                if (strpos($file, $serviceClass) !== false || strpos($file, 'Strategy') !== false) {
                    continue;
                }

                if (preg_match($pattern, $content)) {
                    return true;
                }
            } catch (\Exception $e) {
                // Пропускаем файлы, которые не удалось прочитать
                continue;
            }
        }

        return false;
    }

    /**
     * Получить все PHP файлы в app
     */
    private function getPhpFiles(): array
    {
        $files = File::allFiles(app_path());
        $phpFiles = [];

        foreach ($files as $file) {
            // File::allFiles возвращает SplFileInfo объекты
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }

        return $phpFiles;
    }

    /**
     * Добавить проблему
     */
    private function addIssue(string $file, string $message, string $severity = 'error'): void
    {
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);

        if ($severity === 'error') {
            $this->issues[] = [
                'file' => $relativePath,
                'message' => $message
            ];
        } else {
            $this->warnings[] = [
                'file' => $relativePath,
                'message' => $message
            ];
        }
    }

    /**
     * Добавить предупреждение
     */
    private function addWarning(string $file, string $message): void
    {
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
        $this->warnings[] = [
            'file' => $relativePath,
            'message' => $message
        ];
    }

    /**
     * Добавить информацию
     */
    private function addInfo(string $file, string $message): void
    {
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
        $this->info[] = [
            'file' => $relativePath,
            'message' => $message
        ];
    }

    /**
     * Вывод результатов
     */
    private function displayResults(): void
    {
        $this->newLine();
        $this->info('📊 Результаты диагностики:');
        $this->newLine();

        // Критические проблемы
        if (!empty($this->issues)) {
            $this->error('❌ Критические проблемы (' . count($this->issues) . '):');
            foreach ($this->issues as $issue) {
                $this->line("   • {$issue['file']}");
                $this->line("     {$issue['message']}");
            }
            $this->newLine();
        } else {
            $this->info('✅ Критических проблем не найдено');
            $this->newLine();
        }

        // Предупреждения
        if (!empty($this->warnings)) {
            $this->warn('⚠️  Предупреждения (' . count($this->warnings) . '):');
            foreach ($this->warnings as $warning) {
                $this->line("   • {$warning['file']}");
                $this->line("     {$warning['message']}");
            }
            $this->newLine();
        }

        // Информация
        if (!empty($this->info)) {
            $this->comment('ℹ️  Рекомендации (' . count($this->info) . '):');
            foreach ($this->info as $info) {
                $this->line("   • {$info['file']}");
                $this->line("     {$info['message']}");
            }
            $this->newLine();
        }

        // Итоговая статистика
        $this->displaySummary();
    }

    /**
     * Итоговая статистика
     */
    private function displaySummary(): void
    {
        $totalIssues = count($this->issues) + count($this->warnings);

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📈 Итоговая статистика:');
        $this->line("   Критических проблем: " . count($this->issues));
        $this->line("   Предупреждений: " . count($this->warnings));
        $this->line("   Рекомендаций: " . count($this->info));
        $this->line("   Всего: {$totalIssues}");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        if ($totalIssues === 0) {
            $this->info('✅ Архитектура в хорошем состоянии!');
        } elseif (count($this->issues) === 0) {
            $this->comment('⚠️  Есть предупреждения, но критических проблем нет');
        } else {
            $this->error('❌ Обнаружены критические проблемы, требующие исправления');
        }

        if ($this->option('fix')) {
            $this->newLine();
            $this->displayFixRecommendations();
        }
    }

    /**
     * Рекомендации по исправлению
     */
    private function displayFixRecommendations(): void
    {
        $this->info('💡 Рекомендации по исправлению:');
        $this->newLine();

        if (!empty($this->issues)) {
            $this->line('1. Замените прямые вызовы сервисов на стратегии:');
            $this->line('   ❌ app(MarzbanService::class)');
            $this->line('   ✅ new PanelStrategy($panel->panel)');
            $this->newLine();
        }

        if (!empty($this->warnings)) {
            $this->line('2. Уберите хардкод типов:');
            $this->line('   ❌ if ($panel->panel === Panel::MARZBAN)');
            $this->line('   ✅ Используйте стратегию для всех операций');
            $this->newLine();
        }

        $this->line('3. Создайте фабрики стратегий для упрощения добавления новых провайдеров');
        $this->line('4. Расширьте интерфейсы методами, которые используются напрямую');
        $this->newLine();
        $this->comment('Подробный план рефакторинга см. в ARCHITECTURE_DIAGNOSIS.md');
    }
}
