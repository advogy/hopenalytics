{{--
    A repeatable "platform + URL" row list — lets a Union (or the global Settings row) hold
    several coordinator chat groups at once (e.g. WhatsApp AND Messenger), see CoordinatorGroup.
    Reused as-is by Settings' Koordinator Global tab, the per-Union grid on that same page, and
    the Union create/edit form — every call site just supplies its own array $name (the POST
    field prefix) and $groups (existing rows to prefill).

    Props:
    - name:   POST field prefix, submitted as {name}[i][platform] / {name}[i][url]
    - groups: existing rows to prefill (Collection|array of ['platform' => ..., 'url' => ...])
--}}
@props(['name' => 'groups', 'groups' => []])

@php
    $rows = collect($groups)->values();
@endphp

<div data-group-list class="space-y-2">
    <div data-group-list-rows class="space-y-2">
        @foreach ($rows as $i => $group)
            @php
                $platformValue = $group['platform'] instanceof \App\Enums\GroupPlatform ? $group['platform']->value : $group['platform'];
            @endphp
            <div data-group-list-row class="flex items-center gap-2">
                <select name="{{ $name }}[{{ $i }}][platform]" class="shrink-0 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800">
                    @foreach (\App\Enums\GroupPlatform::cases() as $platform)
                        <option value="{{ $platform->value }}" @selected($platformValue === $platform->value)>{{ $platform->label() }}</option>
                    @endforeach
                </select>
                <input
                    type="url" name="{{ $name }}[{{ $i }}][url]" value="{{ $group['url'] }}"
                    placeholder="{{ __('settings.group_link_placeholder') }}"
                    class="min-w-0 flex-1 rounded-lg border border-black/10 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
                >
                <button type="button" data-group-list-remove aria-label="{{ __('common.delete') }}" class="shrink-0 cursor-pointer rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950">
                    <x-icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
        @endforeach
    </div>

    <template data-group-list-template>
        <div data-group-list-row class="flex items-center gap-2">
            <select name="{{ $name }}[__INDEX__][platform]" class="shrink-0 rounded-lg border border-black/10 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800">
                @foreach (\App\Enums\GroupPlatform::cases() as $platform)
                    <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                @endforeach
            </select>
            <input
                type="url" name="{{ $name }}[__INDEX__][url]"
                placeholder="{{ __('settings.group_link_placeholder') }}"
                class="min-w-0 flex-1 rounded-lg border border-black/10 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none dark:border-white/10 dark:bg-slate-800"
            >
            <button type="button" data-group-list-remove aria-label="{{ __('common.delete') }}" class="shrink-0 cursor-pointer rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950">
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>
    </template>

    <button type="button" data-group-list-add class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-blue-600 transition hover:text-blue-700 dark:text-blue-400">
        <x-icon name="plus" class="h-3.5 w-3.5" />
        {{ __('settings.add_group') }}
    </button>
</div>

@once
    <script>
        (function () {
            if (window.__groupListBound) return;
            window.__groupListBound = true;

            document.addEventListener('click', function (e) {
                var addBtn = e.target.closest('[data-group-list-add]');
                if (addBtn) {
                    var container = addBtn.closest('[data-group-list]');
                    var rows = container.querySelector('[data-group-list-rows]');
                    var template = container.querySelector('[data-group-list-template]');
                    var index = rows.children.length;
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = template.innerHTML.split('__INDEX__').join(String(index)).trim();
                    rows.appendChild(wrapper.firstElementChild);
                    return;
                }

                var removeBtn = e.target.closest('[data-group-list-remove]');
                if (removeBtn) {
                    var row = removeBtn.closest('[data-group-list-row]');
                    if (row) row.remove();
                }
            });
        })();
    </script>
@endonce
