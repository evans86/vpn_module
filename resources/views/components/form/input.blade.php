@props(['type' => 'text', 'name', 'label', 'value' => null, 'required' => false])

<div class="form-group">
    <label for="{{ $name }}">{{ $label }}</label>
    <input type="{{ $type }}" 
           class="form-control" 
           id="{{ $name }}" 
           name="{{ $name }}" 
           value="{{ old($name, $value) }}"
           @if($required) required @endif>
    @error($name)
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
    @enderror
</div>
