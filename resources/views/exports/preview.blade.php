@extends('layouts.app')

@section('title', 'Preview Export — '.$dataset['title'])

@section('content')
    <a href="{{ url()->previous() }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
        &larr; {{ __('common.back') }}
    </a>

    @include('exports._content')
@endsection
