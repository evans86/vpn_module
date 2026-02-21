<?php

namespace App\Models\Bot;

use App\Models\Salesman\Salesman;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_key
 * @property string $private_key
 * @property int $bot_id
 * @property int|null $version
 * @property int $category_id
 * @property int $percent
 * @property int $is_paid
 * @property int $free_show
 * @property int $secret_user_key
 * @property string|null $tariff_cost
 * @property string|null $vpn_instructions
 * @property int|null $bot_user_id
 */
class BotModule extends Model

{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'bot_module';
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
//        'vpn_instructions' => 'array'
    ];

    /**
     * Get the private key attribute (with backward compatibility: decrypt if was stored encrypted)
     */
    public function getPrivateKeyAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return decrypt($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return $value;
        }
    }

    /**
     * Set the private key attribute. Храним в открытом виде — по нему ищем в get/update/delete.
     */
    public function setPrivateKeyAttribute($value)
    {
        $this->attributes['private_key'] = $value ?? null;
    }

    /**
     * Get the public key attribute (with backward compatibility: decrypt if was stored encrypted)
     */
    public function getPublicKeyAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return decrypt($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return $value;
        }
    }

    /**
     * Set the public key attribute. Храним в открытом виде — по нему ищем в get/update/delete.
     */
    public function setPublicKeyAttribute($value)
    {
        $this->attributes['public_key'] = $value ?? null;
    }

    /**
     * Get the secret user key attribute (with backward compatibility)
     */
    public function getSecretUserKeyAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        try {
            return decrypt($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return $value;
        }
    }

    /**
     * Set the secret user key attribute (always encrypt)
     */
    public function setSecretUserKeyAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['secret_user_key'] = encrypt($value);
        } else {
            $this->attributes['secret_user_key'] = null;
        }
    }

    public function salesman()
    {
        return $this->hasOne(Salesman::class, 'module_bot_id');
    }

    /**
     * Поиск модуля по ключам. Учитывает старые записи с зашифрованными ключами в БД.
     */
    public static function findByKeys(string $publicKey, string $privateKey): ?self
    {
        /** @var static|null $module */
        $module = static::query()
            ->where('public_key', $publicKey)
            ->where('private_key', $privateKey)
            ->first();
        if ($module !== null) {
            return $module;
        }
        // Старые записи могли быть сохранены в зашифрованном виде — ищем по расшифрованным (геттер)
        $found = static::query()->get()->first(function ($m) use ($publicKey, $privateKey) {
            return $m->public_key === $publicKey && $m->private_key === $privateKey;
        });

        return $found instanceof static ? $found : null;
    }

    /**
     * Поиск модуля по public_key (для getSettings). Учитывает старые зашифрованные записи.
     */
    public static function findByPublicKey(string $publicKey): ?self
    {
        /** @var static|null $module */
        $module = static::query()->where('public_key', $publicKey)->first();
        if ($module !== null) {
            return $module;
        }
        $found = static::query()->get()->first(function ($m) use ($publicKey) {
            return $m->public_key === $publicKey;
        });

        return $found instanceof static ? $found : null;
    }
}
