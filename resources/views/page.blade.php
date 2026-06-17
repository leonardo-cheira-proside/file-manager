<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@lang('file-manager::file-manager.title')</title>
    <link rel="stylesheet" href="{{ asset('vendor/file-manager/file-manager.css') }}">
    @livewireStyles
</head>
<body class="h-full m-0 bg-gray-50">
    <div class="h-screen">
        <livewire:file-manager />
    </div>
    @livewireScripts
</body>
</html>
