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
        $code = strtoupper(trim((string) ($this->code ?? '')));
        $raw = trim((string) ($this->emoji ?? ''));

        if ($raw !== '') {
            if (preg_match('/^:([a-z]{2}):$/i', $raw, $m)) {
                return CountryFlagHelper::emojiFromAlpha2($m[1]);
            }

            if (strpos($raw, '&') !== false && strpos($raw, ';') !== false && strpos($raw, '#') !== false) {
                $decoded = html_entity_decode($raw, ENT_HTML5 | ENT_HTML401, 'UTF-8');
                if (preg_match('/[\x{1F1E6}-\x{1F1FF}]{2}/u', $decoded, $match)) {
                    return $match[0];
                }
            }

            if (preg_match('/^[\x{1F1E6}-\x{1F1FF}]{2}$/u', $raw)) {
                return $raw;
            }
        }

        return CountryFlagHelper::emojiFromAlpha2($code);
    }

    /** Подпись для UI: «🇳🇱 NL» или «NL», если emoji не удалось восстановить. */
    public function labelWithFlag(): string
    {
        $code = strtoupper(trim((string) ($this->code ?? '')));
        if ($code === '') {
            return '';
        }

        $flag = $this->resolvedFlagEmoji();

        return $flag !== '' ? ($flag.' '.$code) : $code;
    }
}
