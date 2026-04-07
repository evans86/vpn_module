<?php

namespace App\Http\Requests\PackSalesman;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Активация уже купленного ключа (без покупки в том же запросе).
 * Поля совпадают с KeyActivateRequest — модуль, пользователь Bott и UUID ключа.
 */
class PackSalesmanActivateKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
