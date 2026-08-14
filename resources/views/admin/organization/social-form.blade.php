@php
    $indexRouteName = match (true) {
        $owner instanceof \App\Models\Union => 'admin.unions.socials.index',
        $owner instanceof \App\Models\Conference => 'admin.conferences.socials.index',
        $owner instanceof \App\Models\Institution => 'admin.institutions.socials.index',
    };
    $storeRouteName = match (true) {
        $owner instanceof \App\Models\Union => 'admin.unions.socials.store',
        $owner instanceof \App\Models\Conference => 'admin.conferences.socials.store',
        $owner instanceof \App\Models\Institution => 'admin.institutions.socials.store',
    };
@endphp

@extends('layouts.app')

@section('title', ($social->exists ? __('entity.title_edit_social') : __('entity.title_add_social')) . ' — ' . $owner->name)

@section('content')
    <x-social-form
        :social="$social"
        :owner="$owner"
        :action="$social->exists ? route('socials.update', $social) : route($storeRouteName, $owner)"
        :back-url="route($indexRouteName, $owner)"
    />
@endsection
