<?php

namespace App\Http\Requests\BotModule;

use App\Dto\Bot\BotModuleDto;
use App\Helpers\ApiHelpers;
use Illuminate\Foundation\Http\FormRequest;
use \Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;


class BotUpdateRequest extends FormRequest
{
    /**
     * @return string[]
     */
    public function rules()
    {
        return [
            'id' => 'required|integer',
            'public_key' => 'required|string',
            'private_key' => 'required|string',
            'version' => 'required|string|min:1|max:1',
            'free_show' => 'required|string|min:0|max:1',
            'category_id' => 'required|integer',
            'secret_user_key' => 'required|string',
            'tariff_cost' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^(\d+-\d+)(,\d+-\d+)*$/', $value)) {
                        $fail('The ' . $attribute . ' format is invalid. Correct format: 1-150,3-400,6-600,12-1100');
                        return;
                    }

                    // Проверяем, что первые числа (периоды) не изменились
                    $periods = explode(',', $value);
                    $existingPeriods = [];
                    foreach ($periods as $period) {
                        [$num, $price] = explode('-', $period);
                        $existingPeriods[] = $num;
                    }

                    $expectedPeriods = [1, 3, 6, 12]; // Ожидаемые периоды
                    if (count($existingPeriods) !== count($expectedPeriods) ||
                        array_diff($existingPeriods, $expectedPeriods)) {
                        $fail('The periods (first numbers) in ' . $attribute . ' cannot be changed. Only prices can be updated.');
                    }
                },
            ],
            'bot_user_id' => 'required|integer',
        ];
    }

    /**
     * @return BotModuleDto
     */
    public function getDto(): BotModuleDto
    {
        $dto = new BotModuleDto();
        $dto->id = intval($this->id);
        $dto->public_key = $this->public_key;
        $dto->private_key = $this->private_key;
        $dto->bot_id = intval($this->bot_id);
        $dto->version = intval($this->version);
        $dto->category_id = intval($this->category_id);
        $dto->is_paid = intval($this->is_paid);
        $dto->free_show = intval($this->free_show);
        $dto->secret_user_key = $this->secret_user_key;
        $dto->tariff_cost = $this->tariff_cost;
        $dto->bot_user_id = intval($this->bot_user_id);

        return $dto;
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
