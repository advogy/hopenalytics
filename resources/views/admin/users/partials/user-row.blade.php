{{--
    One row of the Kelola Pengguna Admin/Pemimpin tables — shared by the flat listing and the
    Divisi > Uni > Daerah grouped listing (see partials/grouped-user-rows.blade.php).

    Expected: $user, $index (number shown in the # column), $canBootstrapAnyLevel, $tab
    ('admin'|'pemimpin', passed straight through to partials.row-actions), $depth (optional,
    0-3), $ancestors (optional, space-separated group ids this row is nested under).
--}}
@php
    $namePaddingClass = match ($depth ?? 0) {
        1 => 'pl-4',
        2 => 'pl-8',
        3 => 'pl-12',
        default => '',
    };
@endphp
<tr @if ($ancestors ?? null) data-group-ancestors="{{ $ancestors }}" @endif>
    <td class="py-2 pr-2 text-slate-400 dark:text-slate-500 {{ $namePaddingClass }}">{{ $index }}</td>
    <td class="py-2 pr-2">
        @include('admin.users.partials.name-email', ['user' => $user])
    </td>
    @if ($canBootstrapAnyLevel)
        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $user->role->label() }}</td>
        <td class="py-2 pr-2 text-slate-500 dark:text-slate-400">{{ $scopeDisplayFor($user) }}</td>
    @endif
    <td class="py-2 pr-2">
        @include('admin.users.partials.status-badges', ['user' => $user])
    </td>
    <td class="py-2 pr-2">
        <div class="flex items-center justify-end gap-3">
            <form
                method="POST"
                action="{{ route('admin.users.revoke', $user) }}"
                data-confirm="{{ __('users.revoke_confirm', ['name' => $user->name]) }}"
            >
                @csrf
                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                    {{ __('users.revoke') }}
                </button>
            </form>
            @include('admin.users.partials.row-actions', ['user' => $user, 'tab' => $tab])
        </div>
    </td>
</tr>
