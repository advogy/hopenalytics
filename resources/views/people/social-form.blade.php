@php
    // A member managing their own linked Person belongs back on Profil Saya's Media Sosial
    // tab — that's where they add/edit/delete their own accounts now — while an admin
    // managing someone else's lands on the shared people.socials.index list instead. Same
    // rule as PersonSocialController::manageLocation().
    $backRoute = $person->user_id === auth()->id()
        ? route('profile.edit', ['tab' => 'sosial'])
        : route('people.socials.index', $person);
@endphp

@extends('layouts.app')

@section('title', ($social->exists ? __('entity.title_edit_social') : __('entity.title_add_social')) . ' — ' . $person->name)

@section('content')
    <x-social-form
        :social="$social"
        :owner="$person"
        :action="$social->exists ? route('socials.update', $social) : route('people.socials.store', $person)"
        :back-url="$backRoute"
        hint-suffix="person"
        handle-example="johndoe"
    />
@endsection
