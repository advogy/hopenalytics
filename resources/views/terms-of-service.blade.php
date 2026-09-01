@php($wide = true)
@extends('layouts.guest')

@section('title', __('legal.terms_title') . ' — ' . config('app.name'))

@section('content')
    @include('partials.terms-content')
@endsection
