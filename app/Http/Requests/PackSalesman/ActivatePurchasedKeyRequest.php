<?php

namespace App\Http\Requests\PackSalesman;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Активация ключа после оплаты на стороне Bott-T (веб-витрина):
 * сначала фронт получает товар по orderKey, в теле — id ключа VPN; затем этот запрос.
 */
class ActivatePurchasedKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'public_key' => 'required|string',
            'user_tg_id' => 'required',
            'user_secret_key' => 'required|string',
            /** UUID ключа в системе VPN (из product.data / «код товара» после выдачи заказа) */
            'key' => 'required|string',
            /** Служебный токен заказа Bott (для логов и поддержки) */
            'order_key' => 'sometimes|nullable|string|max:512',
            /** Номер заказа в Bott */
            'bott_order_id' => 'sometimes|nullable|string|max:64',
            'email' => 'sometimes|nullable|email|max:255',
        ];
    }
}
