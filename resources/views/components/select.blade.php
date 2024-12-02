@props([
    'name',
    'label' => '',
    'options' => [],
    'selected' => '',
    'required' => false,
    'placeholder' => '-- Выберите --',
    'error' => $errors->first($name)
])

<div class="form-group">
    @if($label)
        <label for="{{ $name }}">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <select name="{{ $name }}"
            id="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'form-control ' . ($error ? 'is-invalid' : '')]) }}>

        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $value => $label)
            <option value="{{ $value }}" {{ $value == old($name, $selected) ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>

    @if($error)
        <div class="invalid-feedback">
            {{ $error }}
        </div>
    @endif
</div>
