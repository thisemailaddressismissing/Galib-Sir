@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- Excel Sheet Ledger View --}}
    <div class="bg-white rounded-xl border border-slate-300 shadow-sm overflow-hidden">
        {{-- Excel Green Header Banner --}}
        <div class="bg-emerald-800 text-white px-4 py-3 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-emerald-700 text-white font-bold text-sm shadow-inner">⊞</span>
                <div>
                    <h2 class="font-bold text-base leading-tight">General Cash Ledger — Excel Sheet View</h2>
                    <p class="text-xs text-emerald-200">Main account tracks real outstanding balance · Petty Cash tracks sub-account spend</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs font-mono">
                <span class="px-2.5 py-1 rounded bg-emerald-900/80 border border-emerald-700/60 text-emerald-200">
                    Rows: <strong class="text-white font-bold">{{ $sheetTransactions->count() }}</strong>
                </span>
                <span class="px-2.5 py-1 rounded bg-emerald-900/80 border border-emerald-700/60 text-emerald-200">
                    Total Amount: <strong class="text-white font-bold">৳{{ number_format($sheetTotalAmount, 2) }}</strong>
                </span>
                <span class="px-2.5 py-1 rounded bg-emerald-900/80 border border-emerald-700/60 text-emerald-200">
                    Company Outflow: <strong class="text-red-300 font-bold">৳{{ number_format($sheetExternalOut, 2) }}</strong>
                </span>
                <span class="px-2.5 py-1 rounded bg-emerald-950 border border-emerald-700 text-white">
                    Main Outstanding: <strong class="text-emerald-300 font-bold">৳{{ number_format($stats['main_balance'], 2) }}</strong>
                </span>
            </div>
        </div>

        {{-- Excel Filter Toolbar --}}
        <form method="GET" action="{{ route('dashboard') }}" class="bg-slate-50 border-b border-slate-300 p-3 flex flex-wrap items-center gap-2 text-xs sm:text-sm">
            <div class="flex-1 min-w-44">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search purpose, ref, notes, person…"
                    class="w-full px-3 py-1.5 border border-slate-300 rounded text-xs sm:text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
            </div>
            <div>
                <select name="account_id" class="px-2.5 py-1.5 border border-slate-300 rounded text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All Accounts</option>
                    @foreach ($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected((string) request('account_id') === (string) $acc->id)>
                            {{ $acc->name }} ({{ $acc->is_main ? 'Main' : 'Sub' }} · ৳{{ number_format($acc->current_balance, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="type" class="px-2.5 py-1.5 border border-slate-300 rounded text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All Types</option>
                    <option value="income" @selected(request('type') === 'income')>Transfer in</option>
                    <option value="expense" @selected(request('type') === 'expense')>Transfer out</option>
                </select>
            </div>
            <div class="flex items-center gap-1">
                <input type="date" name="from" value="{{ request('from') }}" title="From Date"
                    class="px-2 py-1.5 border border-slate-300 rounded text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <span class="text-slate-400">to</span>
                <input type="date" name="to" value="{{ request('to') }}" title="To Date"
                    class="px-2 py-1.5 border border-slate-300 rounded text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <select name="sort" class="px-2 py-1.5 border border-slate-300 rounded text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="asc" @selected($sort === 'asc')>Oldest first (Ledger)</option>
                    <option value="desc" @selected($sort === 'desc')>Newest first</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-3 py-1.5 rounded bg-emerald-700 hover:bg-emerald-800 text-white text-xs sm:text-sm font-medium">Apply</button>
                @if (request()->hasAny(['search', 'account_id', 'type', 'from', 'to', 'sort']))
                    <a href="{{ route('dashboard') }}" class="px-2.5 py-1.5 rounded border border-slate-300 hover:bg-slate-200 text-slate-600 text-xs sm:text-sm">Reset</a>
                @endif
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-900 text-white text-xs sm:text-sm font-medium ml-auto">Export CSV</a>
            </div>
        </form>

        {{-- Excel Table Grid --}}
        <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
            <table class="w-full text-xs sm:text-sm border-collapse">
                <thead class="sticky top-0 z-10 bg-slate-100 text-slate-700 shadow-sm">
                    {{-- Excel Column Letter Reference Bar --}}
                    <tr class="bg-slate-200/70 text-[10px] text-slate-500 font-mono text-center select-none border-b border-slate-300">
                        <th class="py-0.5 border-r border-slate-300 w-12">A</th>
                        <th class="py-0.5 border-r border-slate-300">B</th>
                        <th class="py-0.5 border-r border-slate-300">C</th>
                        <th class="py-0.5 border-r border-slate-300">D</th>
                        <th class="py-0.5 border-r border-slate-300">E</th>
                        <th class="py-0.5 border-r border-slate-300">F</th>
                        <th class="py-0.5 border-r border-slate-300">G</th>
                        <th class="py-0.5 border-r border-slate-300">H</th>
                        <th class="py-0.5 border-r border-slate-300 bg-amber-50">I</th>
                        <th class="py-0.5 border-r border-slate-300 bg-amber-50">J</th>
                        <th class="py-0.5 bg-emerald-50">K</th>
                    </tr>
                    {{-- Header Labels --}}
                    <tr class="border-b border-slate-300 font-semibold text-left">
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center w-12 bg-slate-200/50">#</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">Date</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 min-w-44">Particulars / Purpose</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 min-w-32">Person</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 min-w-24">Account</th>
                        <th class="py-2.5 px-3 border-r border-slate-300">Category</th>
                        <th class="py-2.5 px-3 border-r border-slate-300">Ref</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-right whitespace-nowrap text-slate-800 min-w-28">Amount (৳)</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-right whitespace-nowrap bg-amber-50/70 text-amber-900 font-semibold min-w-28" title="Amount spent or returned through Petty Cash">Petty Cash (৳)</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-right whitespace-nowrap bg-amber-50/70 text-amber-950 font-semibold min-w-28" title="Petty cash balance after this row">Petty Balance</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap bg-emerald-50 text-emerald-950 font-bold min-w-36" title="Main account outstanding balance after this row">Outstanding Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    {{-- Opening Balance Row --}}
                    @if (!request('from') || $sort === 'asc')
                        <tr class="bg-slate-100/70 font-medium">
                            <td class="py-2 px-3 border-r border-slate-300 text-center text-slate-400 bg-slate-200/60 font-mono text-xs">0</td>
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-400 font-mono text-xs">—</td>
                            <td class="py-2 px-3 border-r border-slate-200 font-semibold text-slate-800">Opening Balance</td>
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-400">—</td>
                            <td class="py-2 px-3 border-r border-slate-200 font-medium text-slate-600">{{ $main ? $main->name : 'Main' }}</td>
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-400">—</td>
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-400 font-mono text-xs">—</td>
                            <td class="py-2 px-3 border-r border-slate-200 text-right font-mono text-slate-400">—</td>
                            <td class="py-2 px-3 border-r border-slate-200 text-right font-mono text-slate-400 bg-amber-50/40">—</td>
                            <td class="py-2 px-3 border-r border-slate-200 text-right font-mono text-amber-900 bg-amber-50/40 font-semibold">
                                ৳{{ number_format($petty ? (float) $petty->opening_balance : 0, 2) }}
                            </td>
                            <td class="py-2 px-3 text-right font-mono font-bold text-slate-900 bg-emerald-50/60">
                                ৳{{ number_format($main ? (float) $main->opening_balance : 0, 2) }}
                            </td>
                        </tr>
                    @endif

                    @forelse ($sheetTransactions as $index => $t)
                        <tr class="hover:bg-blue-50/50 transition-colors {{ $loop->odd ? 'bg-white' : 'bg-slate-50/40' }}">
                            {{-- Row Number --}}
                            <td class="py-2 px-3 border-r border-slate-300 text-center text-slate-400 bg-slate-100/60 font-mono text-xs select-none">
                                {{ $index + 1 }}
                            </td>
                            {{-- Date --}}
                            <td class="py-2 px-3 border-r border-slate-200 whitespace-nowrap font-mono text-xs text-slate-600">
                                {{ $t->date->format('d M Y') }}
                            </td>
                            {{-- Particulars / Purpose --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-800">
                                <span class="font-medium">{{ $t->purpose ?? '—' }}</span>
                                @if ($t->isTransfer())
                                    <span class="inline-block ml-1 px-1.5 py-0.2 rounded text-[10px] bg-slate-200 text-slate-700">Internal move</span>
                                @endif
                                @if ($t->notes)
                                    <span class="block text-[11px] text-slate-400 truncate max-w-xs">{{ $t->notes }}</span>
                                @endif
                            </td>
                            {{-- Person --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-700">
                                @if ($t->person)
                                    <a href="{{ route('people.report', $t->person) }}" class="text-slate-800 hover:text-emerald-700 hover:underline">
                                        {{ $t->person->name }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            {{-- Account --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-700 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $t->account?->is_main ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' }}">
                                    {{ $t->account->name ?? '—' }}
                                </span>
                            </td>
                            {{-- Category --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-600 whitespace-nowrap">
                                {{ $t->category->name ?? '—' }}
                            </td>
                            {{-- Reference --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-slate-500 font-mono text-xs whitespace-nowrap">
                                {{ $t->reference ?? '—' }}
                            </td>
                            {{-- Amount (Combined Single Column) --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-right whitespace-nowrap font-mono">
                                @if ($t->isTransfer())
                                    <span class="font-medium text-slate-600" title="Internal markdown/transfer">৳{{ number_format($t->amount, 2) }}</span>
                                @elseif ($t->type === 'income')
                                    <span class="font-semibold text-emerald-700">+৳{{ number_format($t->amount, 2) }}</span>
                                @else
                                    <span class="font-semibold text-red-600">-৳{{ number_format($t->amount, 2) }}</span>
                                @endif
                            </td>
                            {{-- Petty Cash Movement --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-right whitespace-nowrap font-mono bg-amber-50/30">
                                @if ($t->petty_amount !== null)
                                    @if ($t->isTransfer())
                                        <span class="text-slate-600 font-medium">{{ $t->petty_amount > 0 ? '+৳' : '-৳' }}{{ number_format(abs($t->petty_amount), 2) }}</span>
                                    @elseif ($t->petty_amount > 0)
                                        <span class="text-emerald-700 font-medium">+৳{{ number_format($t->petty_amount, 2) }}</span>
                                    @else
                                        <span class="text-amber-800 font-medium font-mono">-৳{{ number_format(abs($t->petty_amount), 2) }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            {{-- Petty Cash Running Balance --}}
                            <td class="py-2 px-3 border-r border-slate-200 text-right whitespace-nowrap font-mono bg-amber-50/40 font-semibold text-amber-950">
                                ৳{{ number_format($t->petty_balance, 2) }}
                            </td>
                            {{-- Main Outstanding Balance after this row --}}
                            <td class="py-2 px-3 text-right whitespace-nowrap font-mono font-bold bg-emerald-50/50 {{ $t->outstanding_amount < 0 ? 'text-red-600' : 'text-slate-900' }}">
                                ৳{{ number_format($t->outstanding_amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-slate-500">
                                <p class="text-base font-medium">No transactions found matching the selected filters.</p>
                                <p class="text-xs text-slate-400 mt-1">Try clearing your filters or recording a new transaction.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Accounts & Charts Overview --}}
    <div class="grid lg:grid-cols-3 gap-4">
        {{-- Account balances --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">Accounts</h2>
                <a href="{{ route('accounts.index') }}" class="text-sm text-emerald-700 hover:underline">All →</a>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($accounts as $account)
                    <li class="py-2.5 flex justify-between gap-2 items-center">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="font-medium text-slate-800">{{ $account->name }}</span>
                                @if ($account->is_main)
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-emerald-600 text-white">Main</span>
                                @else
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-medium bg-amber-100 text-amber-800">Sub</span>
                                @endif
                            </div>
                            <span class="block text-xs text-slate-400 mt-0.5">Opening: ৳{{ number_format($account->opening_balance, 2) }}</span>
                        </div>
                        <a href="{{ route('dashboard', ['account_id' => $account->id]) }}" class="font-semibold whitespace-nowrap font-mono text-right hover:text-emerald-700" title="Filter ledger by this account">
                            ৳{{ number_format($account->current_balance, 2) }}
                        </a>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">No accounts yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- 6-month chart (plain CSS bars) --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 lg:col-span-2">
            <h2 class="font-bold mb-1">Last 6 months</h2>
            <p class="text-xs text-slate-500 mb-4"><span class="text-emerald-600 font-bold">■</span> Transfer in <span class="text-red-500 font-bold ml-2">■</span> Transfer out</p>
            @php $max = max(1, collect($months)->max(fn ($m) => max($m['income'], $m['expense']))); @endphp
            <div class="flex items-end gap-4 h-40">
                @foreach ($months as $m)
                    <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end">
                        <div class="flex items-end gap-1 h-32">
                            <div class="w-4 bg-emerald-500 rounded-t" style="height: {{ max(2, $m['income'] / $max * 100) }}%" title="Transfer in ৳{{ number_format($m['income'], 2) }}"></div>
                            <div class="w-4 bg-red-400 rounded-t" style="height: {{ max(2, $m['expense'] / $max * 100) }}%" title="Transfer out ৳{{ number_format($m['expense'], 2) }}"></div>
                        </div>
                        <span class="text-xs text-slate-500">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Advances & Expenses --}}
    <div class="grid lg:grid-cols-2 gap-4">
        {{-- Recent advances --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">Recent advances</h2>
                <a href="{{ route('advances.index') }}" class="text-sm text-emerald-700 hover:underline">All →</a>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentAdvances as $a)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <a href="{{ route('advances.show', $a) }}" class="truncate hover:underline">{{ $a->person->name ?? '—' }}</a>
                        <span class="whitespace-nowrap font-mono"><span class="font-semibold">৳{{ number_format($a->amount, 2) }}</span> <span class="text-slate-400 font-sans">· {{ ucfirst($a->status) }}</span></span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">No advances yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Recent expenses + by category --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">Recent expenses</h2>
                <a href="{{ route('expenses.index') }}" class="text-sm text-emerald-700 hover:underline">All →</a>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentExpenses as $e)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <span class="truncate">{{ $e->description ?: ($e->category->name ?? 'Expense') }}</span>
                        <span class="font-semibold whitespace-nowrap font-mono">৳{{ number_format($e->amount, 2) }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">No expenses yet.</li>
                @endforelse
            </ul>
            @if ($expensesByCategory->isNotEmpty())
                <h3 class="font-bold mt-4 mb-2 text-sm">By category</h3>
                <ul class="text-sm space-y-1">
                    @foreach ($expensesByCategory as $row)
                        <li class="flex justify-between gap-2">
                            <span class="truncate text-slate-600">{{ $row->category->name ?? 'Uncategorized' }}</span>
                            <span class="font-semibold font-mono">৳{{ number_format($row->total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
