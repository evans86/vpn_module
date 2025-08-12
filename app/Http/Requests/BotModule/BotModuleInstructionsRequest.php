<?php

namespace App\Http\Requests\BotModule;

use App\Helpers\ApiHelpers;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class BotModuleInstructionsRequest extends FormRequest
{
    /**
     * @return string[]
     */
    public function rules()
    {
        return [
            'public_key' => 'required|string'
        ];
    }

    /**
     * @inheritDoc
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()
            ->make(ApiHelpers::error($validator->errors()->first()), 422);

        throw (new ValidationException($validator, $response))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }
}
