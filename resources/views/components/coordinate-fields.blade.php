@props(['latitude' => null, 'longitude' => null])

<div class="mb-6">
    <label class="mb-0.5 block text-sm font-medium">
        {{ __('common.coordinates') }}
    </label>
    <p class="mb-1.5 text-xs text-slate-400">{{ __('common.coordinates_hint') }}</p>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <input
                type="number" step="any" id="latitude" name="latitude"
                value="{{ old('latitude', $latitude) }}"
                placeholder="Latitude"
                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
            @error('latitude')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <input
                type="number" step="any" id="longitude" name="longitude"
                value="{{ old('longitude', $longitude) }}"
                placeholder="Longitude"
                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
            @error('longitude')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
