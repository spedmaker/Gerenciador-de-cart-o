<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SmartBill – @yield('title', 'Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="d-flex">
    {{-- Sidebar --}}
    <nav class="sidebar d-flex flex-column p-3" style="width:220px;min-width:220px;">
        <a href="{{ route('dashboard') }}" class="brand-title text-decoration-none mb-4 d-block">
            💳 SmartBill
        </a>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a href="{{ route('dashboard') }}"
                   class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('dashboard.mensal') }}"
                   class="nav-link {{ request()->routeIs('dashboard.mensal') ? 'active' : '' }}">
                    Evolução mensal
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('statements.index') }}"
                   class="nav-link {{ request()->routeIs('statements.*') ? 'active' : '' }}">
                    Faturas
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('transactions.index') }}"
                   class="nav-link {{ request()->routeIs('transactions.index') ? 'active' : '' }}">
                    Lançamentos
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('transactions.uncategorized') }}"
                   class="nav-link {{ request()->routeIs('transactions.uncategorized') ? 'active' : '' }}">
                    Pendentes
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('super-dashboard') }}"
                   class="nav-link {{ request()->routeIs('super-dashboard*') ? 'active' : '' }}">
                    Super Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('category-rules.index') }}"
                   class="nav-link {{ request()->routeIs('category-rules.*') ? 'active' : '' }}">
                    Regras
                </a>
            </li>
        </ul>
    </nav>

    {{-- Main content --}}
    <main class="flex-grow-1 p-4" style="min-height:100vh">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
