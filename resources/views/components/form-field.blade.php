@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'placeholder' => null, 'hint' => null, 'wrapperClass' => 'mb-5', 'id' => null])

@php $id ??= $name; @endphp

<div class="{{ $wrapperClass }}">
    <label for="{{ $id }}" class="block text-sm font-medium {{ $hint ? 'mb-0.5' : 'mb-1.5' }}">
        {{ $label }}
    </label>
    @if ($hint)
        <p class="mb-1.5 text-xs text-slate-400">{{ $hint }}</p>
    @endif
    @if ($type === 'password')
        <div class="relative" data-password-field>
            <input
                type="password" id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
                value="{{ old($name, $value) }}"
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                {{ $attributes->merge(['class' => 'w-full rounded-lg border border-black/10 bg-white px-3 py-2 pr-10 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800']) }}
            >
            <button
                type="button"
                data-password-toggle
                tabindex="-1"
                aria-label="{{ __('common.toggle_password_visibility') }}"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
            >
                <x-icon name="eye" class="h-4 w-4" data-password-toggle-icon-show />
                <x-icon name="eye-slash" class="hidden h-4 w-4" data-password-toggle-icon-hide />
            </button>
        </div>
    @else
        <input
            type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
            value="{{ old($name, $value) }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->merge(['class' => 'w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800']) }}
        >
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
