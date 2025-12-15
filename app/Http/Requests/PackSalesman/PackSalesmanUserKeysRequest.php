<?php

namespace App\Http\Requests\PackSalesman;

use Illuminate\Foundation\Http\FormRequest;

class PackSalesmanUserKeysRequest extends FormRequest
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
//            'private_key' => 'required|string',
            'user_tg_id' => 'required',
            'user_secret_key' => 'required|string',
//            'offset' => 'required',
//            'limit' => 'required'
        ];
    }
}
