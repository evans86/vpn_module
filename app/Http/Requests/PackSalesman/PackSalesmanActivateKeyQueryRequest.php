<?php

namespace App\Http\Requests\PackSalesman;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET-активация ключа: те же поля, что у PackSalesmanActivateKeyRequest, плюс нормализация query-string.
 *
 * - key_id — синоним key (удобно в URL).
 * - public_key / user_secret_key: в query «+» часто превращается в пробел — восстанавливаем для base64.
 * - UUID ключа: приводим к нижнему регистру (a-f), если похож на UUID.
 */
class PackSalesmanActivateKeyQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        $keyRaw = $this->input('key', $this->input('key_id'));
        if ($keyRaw !== null && $keyRaw !== '') {
            $keyStr = (string) $keyRaw;
            if (preg_match('/^[0-9a-fA-F-]{36}$/', $keyStr)) {
                $merge['key'] = strtolower($keyStr);
            } else {
                $merge['key'] = $keyStr;
            }
        }

        foreach (['public_key', 'user_secret_key'] as $field) {
            $v = $this->input($field);
            if (! is_string($v) || $v === '') {
                continue;
            }
            if (str_contains($v, ' ') && ! str_contains($v, '+')) {
                $merge[$field] = str_replace(' ', '+', $v);
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'key' => 'required|string',
            'public_key' => 'required|string',
            'user_tg_id' => 'required',
            'user_secret_key' => 'required|string',
        ];
    }
}
