<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-vh-100 d-flex flex-column align-items-center justify-content-center px-3 py-5">
        <a href="/" class="text-decoration-none text-brand mb-4">
            <x-application-logo class="fs-3" />
        </a>

        <div class="card shadow-sm border-0 auth-card">
            <div class="card-body p-4">
                {{ $slot }}
            </div>
        </div>

        <p class="text-muted small mt-4 mb-0">Integrated Multi-Branch Business Management System</p>
    </div>
</body>
</html>
