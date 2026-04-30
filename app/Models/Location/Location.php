<?php

namespace App\Models\Location;

use App\Helpers\CountryFlagHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $code
 * @property string|null $emoji
 */
class Location extends Model
{
    use HasFactory;

    const NL = 1;
    const RU = 2;
    const FI = 3;
    const TR = 4;
    const CZ = 5;

    protected $guarded = false;
    protected $table = 'location';

    /**
     * Реальный UTF-8 флаг (🇳🇱) из location.code или из поля emoji
     * (Discord-шорткод «:nl:», числовые HTML-сущности, уже сохранённые emoji-символы).
     */
    public function resolvedFlagEmoji(): string
    {
        return CountryFlagHelper::resolvedEmojiFromStored($this->code, $this->emoji);
    }

    /** Подпись для UI: «🇳🇱 NL» или «NL», если emoji не удалось восстановить. */
    public function labelWithFlag(): string
    {
        return CountryFlagHelper::countryLabelWithFlag($this->code, $this->emoji);
    }
}
