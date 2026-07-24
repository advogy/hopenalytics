@php($wide = true)
@extends('layouts.guest')

@section('title', __('nav.about') . ' — ' . config('app.name'))

@section('content')
    @include('partials.about-content')
@endsection
