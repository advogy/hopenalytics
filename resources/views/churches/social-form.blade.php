@extends('layouts.app')

@section('title', ($social->exists ? __('entity.title_edit_social') : __('entity.title_add_social')) . ' — ' . $church->name)

@section('content')
    <x-social-form
        :social="$social"
        :owner="$church"
        :action="$social->exists ? route('socials.update', $social) : route('socials.store', $church)"
        :back-url="route('churches.socials.index', $church)"
        show-category
    />
@endsection
