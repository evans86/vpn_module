<?php

namespace App\Http\Requests\KeyActivate;

use Illuminate\Foundation\Http\FormRequest;

class ActivateWithEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => 'required|string|max:64',
            'email' => 'required|email|max:255',
        ];
    }
}
