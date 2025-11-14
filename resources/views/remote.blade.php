@extends('base')

@section('title', 'Vantanova - Remote Controller')

@push('scripts')
    @vite(['resources/assets/js/remote/app.ts'])
@endpush
