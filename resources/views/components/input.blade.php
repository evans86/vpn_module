@props([
    'type' => 'text',
    'name',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'help' => '',
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

    <input type="{{ $type }}"
           name="{{ $name }}"
           id="{{ $name }}"
           value="{{ old($name, $value) }}"
           placeholder="{{ $placeholder }}"
           {{ $required ? 'required' : '' }}
           {{ $attributes->merge(['class' => 'form-control ' . ($error ? 'is-invalid' : '')]) }}>

    @if($help)
        <small class="form-text text-muted">{{ $help }}</small>
    @endif

    @if($error)
        <div class="invalid-feedback">
            {{ $error }}
        </div>
    @endif
</div>
