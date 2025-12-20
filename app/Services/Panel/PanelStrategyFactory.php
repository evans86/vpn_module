<?php

namespace App\Services\Panel;

use App\Models\Panel\Panel;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Фабрика для создания стратегий работы с панелями
 * 
 * Позволяет легко добавлять новые типы панелей без изменения существующего кода
 * 
 * @example
 * $factory = new PanelStrategyFactory();
 * $strategy = $factory->create(Panel::MARZBAN);
 * $strategy->create($serverId);
 */
class PanelStrategyFactory
{
    /**
     * Маппинг типов панелей на классы стратегий
     * 
     * @var array<string, string>
     */
    private static array $strategyMap = [
        Panel::MARZBAN => \App\Services\Panel\strategy\PanelMarzbanStrategy::class,
        // Здесь можно добавить новые типы панелей:
        // Panel::NEW_PANEL => \App\Services\Panel\strategy\PanelNewPanelStrategy::class,
    ];

    /**
     * Создать стратегию для указанного типа панели
     *
     * @param string $panelType Тип панели (например, Panel::MARZBAN)
     * @return PanelInterface Стратегия для работы с панелью
     * @throws DomainException Если тип панели не найден
     */
    public function create(string $panelType): PanelInterface
    {
        if (!isset(self::$strategyMap[$panelType])) {
            Log::error('Panel strategy not found', [
                'panel_type' => $panelType,
                'available_panel_types' => array_keys(self::$strategyMap)
            ]);
            
            throw new DomainException(
                "Panel strategy not found for type: {$panelType}. " .
                "Available panel types: " . implode(', ', array_keys(self::$strategyMap))
            );
        }

        $strategyClass = self::$strategyMap[$panelType];
        
        // Используем DI контейнер для создания стратегии с зависимостями
        $strategy = app($strategyClass);
        
        if (!$strategy instanceof PanelInterface) {
            throw new DomainException(
                "Strategy class {$strategyClass} must implement PanelInterface"
            );
        }

        return $strategy;
    }

    /**
     * Получить список доступных типов панелей
     *
     * @return array<string> Массив доступных типов панелей
     */
    public function getAvailablePanelTypes(): array
    {
        return array_keys(self::$strategyMap);
    }

    /**
     * Проверить, поддерживается ли тип панели
     *
     * @param string $panelType Тип панели
     * @return bool true если тип панели поддерживается
     */
    public function isPanelTypeSupported(string $panelType): bool
    {
        return isset(self::$strategyMap[$panelType]);
    }

    /**
     * Зарегистрировать новую стратегию (для расширяемости)
     * 
     * ВНИМАНИЕ: Используйте только в Service Provider или конфигурации!
     *
     * @param string $panelType Тип панели
     * @param string $strategyClass Класс стратегии
     * @return void
     * @throws DomainException Если класс стратегии не существует или не реализует интерфейс
     */
    public static function registerStrategy(string $panelType, string $strategyClass): void
    {
        if (!class_exists($strategyClass)) {
            throw new DomainException("Strategy class {$strategyClass} does not exist");
        }

        if (!is_subclass_of($strategyClass, PanelInterface::class)) {
            throw new DomainException(
                "Strategy class {$strategyClass} must implement PanelInterface"
            );
        }

        self::$strategyMap[$panelType] = $strategyClass;
        
        Log::info('Panel strategy registered', [
            'panel_type' => $panelType,
            'strategy_class' => $strategyClass
        ]);
    }
}
