<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{-- Sidebar (fixed on desktop, slide-in menu on tablet/mobile) --}}
    @include('layouts.navigation')

    <div class="app-main d-flex flex-column min-vh-100">
        {{-- Top navigation bar --}}
        <nav class="navbar bg-white border-bottom px-3 sticky-top">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                        aria-controls="appSidebar" aria-label="Open menu">
                    <i class="bi bi-list"></i>
                </button>
                @isset($header)
                    <h1 class="h5 mb-0">{{ $header }}</h1>
                @endisset
            </div>

            <div class="dropdown">
                <button class="btn btn-link text-decoration-none text-body dropdown-toggle d-flex align-items-center gap-2"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">{{ Auth::user()->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person me-2"></i>{{ __('Profile') }}
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>{{ __('Log Out') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        {{-- Page content --}}
        <main class="flex-grow-1 p-3 p-md-4">
            {{-- Flash messages set by controllers: ->with('success', '...') or ->with('error', '...') --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="text-center text-muted small py-3 border-top bg-white">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </footer>
    </div>
</body>
</html>
