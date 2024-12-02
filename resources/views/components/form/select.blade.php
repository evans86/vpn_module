@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'id' => null])

<div class="form-group">
    <label for="{{ $id ?? $name }}">{{ $label }}</label>
    <select class="form-control selectpicker"
            id="{{ $id ?? $name }}"
            name="{{ $name }}"
            @if($required) required @endif>
        <option value="">Выберите...</option>
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" {{ old($name, $value) == $optionValue ? 'selected' : '' }}>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
    @error($name)
    <div class="invalid-feedback d-block">
        {{ $message }}
    </div>
    @enderror
</div>
