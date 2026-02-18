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
     * Get the private key attribute (with backward compatibility)
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
     * Set the private key attribute (always encrypt)
     */
    public function setPrivateKeyAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['private_key'] = encrypt($value);
        } else {
            $this->attributes['private_key'] = null;
        }
    }

    /**
     * Get the public key attribute (with backward compatibility)
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
     * Set the public key attribute (always encrypt)
     */
    public function setPublicKeyAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['public_key'] = encrypt($value);
        } else {
            $this->attributes['public_key'] = null;
        }
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
}
