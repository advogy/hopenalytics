@php($wide = true)
@extends('layouts.guest')

@section('title', __('legal.privacy_title') . ' — ' . config('app.name'))

@section('content')
    @include('partials.privacy-content')
@endsection
