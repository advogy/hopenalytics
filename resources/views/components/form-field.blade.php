@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'placeholder' => null, 'hint' => null, 'wrapperClass' => 'mb-5'])

<div class="{{ $wrapperClass }}">
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">
        {{ $label }}
        @if ($hint)
            <span class="text-slate-400">{{ $hint }}</span>
        @endif
    </label>
    <input
        type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" @if ($required) required @endif
        value="{{ old($name, $value) }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800']) }}
    >
    @error($name)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
