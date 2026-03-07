<?php

namespace App\Http\Requests\PackSalesman;

use Illuminate\Foundation\Http\FormRequest;

class PackSalesmanBuyKeyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'public_key' => 'required|string',
            'user_tg_id' => 'required', // тг id покупателя
            'user_secret_key' => 'required|string', // секретный ключ из бота для проверки пользователя
            'product_id' => 'required|integer',
        ];
    }
}
