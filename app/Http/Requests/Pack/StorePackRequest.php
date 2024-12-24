<?php

namespace App\Http\Requests\Pack;

use Illuminate\Foundation\Http\FormRequest;

class StorePackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'price' => 'required|integer|min:0',
            'period' => 'required|integer|min:1',
            'traffic_limit' => 'required|integer|min:1',
            'count' => 'required|integer|min:1',
            'activate_time' => 'required|integer|min:1'
        ];
    }

    /**
     * Get the validated data from the request.
     *
     * @param string|null $key
     * @param mixed $default
     * @return array
     */
    public function validated(string $key = null, $default = null): array
    {
        $validated = parent::validated();

        // Конвертируем только если данные еще не были сконвертированы
        if (isset($validated['traffic_limit']) && $validated['traffic_limit'] < 1024*1024*1024) {
            // Конвертируем GB в байты
            $validated['traffic_limit'] = $validated['traffic_limit'] * 1024 * 1024 * 1024;
        }
        
        if (isset($validated['activate_time']) && $validated['activate_time'] < 3600) {
            // Конвертируем часы в секунды
            $validated['activate_time'] = $validated['activate_time'] * 3600;
        }
        
        // По умолчанию пакет активен
        $validated['status'] = true;

        return $validated;
    }
}
