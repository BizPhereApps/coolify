@extends('layouts.base')
@section('body')
    @livewireScripts
    <main class="min-h-full bg-gray-50 dark:bg-base">
        {{ $slot }}
        <x-agpl-footer />
    </main>
    @parent
@endsection
