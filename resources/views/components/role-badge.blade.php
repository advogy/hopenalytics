@props(['role'])

@php
    $variants = [
        'superadmin' => 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-400',
        'admin' => 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
        'pimpinan' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
        'anggota' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    ];

    $tier = match (true) {
        $role === null => 'anggota',
        $role->value === 'superadmin' => 'superadmin',
        $role->isReadOnly() => 'pimpinan',
        default => 'admin',
    };

    $label = $role?->label() ?? 'Anggota';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {$variants[$tier]}"]) }}>
    {{ $label }}
</span>
