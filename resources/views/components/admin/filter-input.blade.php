@props(['name', 'label', 'value' => '', 'placeholder' => '', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }}
    </label>
    <input type="{{ $type }}" 
           id="{{ $name }}" 
           name="{{ $name }}" 
           value="{{ old($name, $value) }}" 
           placeholder="{{ $placeholder }}"
           class="form-control">
</div>

