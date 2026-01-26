<?php

namespace App\Models\OrderSetting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
class OrderSetting extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'order_settings';

    /**
     * Получить значение настройки по ключу
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Установить значение настройки
     */
    public static function setValue(string $key, $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Проверить, включена ли система заказов
     */
    public static function isSystemEnabled(): bool
    {
        return (bool) self::getValue('system_enabled', '0');
    }

    /**
     * Включить/выключить систему заказов
     */
    public static function setSystemEnabled(bool $enabled): void
    {
        self::setValue('system_enabled', $enabled ? '1' : '0');
    }
}

