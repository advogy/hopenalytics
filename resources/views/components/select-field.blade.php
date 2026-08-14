{{--
    Sibling of x-form-field, same label/hint/error contract, for a <select> instead of an
    <input> — $slot holds the <option>s (a plain hardcoded list, or a @foreach loop), since
    the option set varies too much across callers for a single :options prop to cover cleanly.
--}}
@props(['name', 'label', 'required' => false, 'hint' => null, 'wrapperClass' => 'mb-5', 'id' => null])

@php $id ??= $name; @endphp

<div class="{{ $wrapperClass }}">
    <label for="{{ $id }}" class="block text-sm font-medium {{ $hint ? 'mb-0.5' : 'mb-1.5' }}">
        {{ $label }}
    </label>
    @if ($hint)
        <p class="mb-1.5 text-xs text-slate-400">{{ $hint }}</p>
    @endif
    <select
        id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800']) }}
    >
        {{ $slot }}
    </select>
    @error($name)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
