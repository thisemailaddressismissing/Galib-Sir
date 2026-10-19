<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cash Management') — Office Cash</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transition-transform lg:static lg:translate-x-0 bg-slate-900 text-slate-200 flex flex-col">
        <div class="px-6 py-5 border-b border-slate-700/60">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-emerald-500 flex items-center justify-center font-bold text-slate-900">৳</div>
                <div>
                    <p class="font-bold text-white leading-tight">Office Cash</p>
                    <p class="text-xs text-slate-400">Cash Management</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 text-sm">
            @php
                $links = [
                    ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => '◧', 'match' => 'dashboard'],
                    ['route' => 'advances.index', 'label' => 'Advances', 'icon' => '▣', 'match' => 'advances*'],
                    ['route' => 'expenses.index', 'label' => 'Expenses', 'icon' => '🧾', 'match' => 'expenses*'],
                    ['route' => 'returns.index', 'label' => 'Returns', 'icon' => '↩', 'match' => 'returns*'],
                    ['route' => 'people.index', 'label' => 'People', 'icon' => '👥', 'match' => 'people*'],
                    ['route' => 'suppliers.index', 'label' => 'Suppliers', 'icon' => '🚚', 'match' => 'supplier*'],
                    ['route' => 'accounts.index', 'label' => 'Accounts', 'icon' => '🏦', 'match' => 'accounts*'],
                    ['route' => 'categories.index', 'label' => 'Categories', 'icon' => '🏷', 'match' => 'categories*'],
                    ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => '⇄', 'match' => 'transactions*'],
                    ['route' => 'backups.index', 'label' => 'Cloud Backup', 'icon' => '☁️', 'match' => 'backups*'],
                ];
            @endphp
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['match']); @endphp
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ $active ? 'bg-emerald-500/15 text-emerald-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <span class="w-6 text-center">{{ $link['icon'] }}</span>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="p-4 border-t border-slate-700/60 text-xs text-slate-400">
            <p>Office Cash System</p>
            <p>{{ now()->format('d M Y') }}</p>
        </div>
    </aside>
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-30 lg:hidden"></div>

    <!-- Main -->
    <div class="flex-1 min-w-0 flex flex-col">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 flex items-center gap-3 sticky top-0 z-20">
            <button id="sidebarToggle" class="lg:hidden p-2 rounded-lg hover:bg-slate-100" aria-label="Toggle menu">☰</button>
            <h1 class="font-bold text-lg truncate">@yield('title', 'Dashboard')</h1>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('transactions.create') }}" class="hidden sm:inline-flex px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">+ Transaction</a>
                <a href="{{ route('advances.create') }}" class="inline-flex px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">+ Advance</a>
            </div>
        </header>

        <main class="p-4 sm:p-6 space-y-6">
            @if (session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    document.getElementById('sidebarToggle').addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    });
    overlay.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    });
</script>
@stack('scripts')
</body>
</html>
