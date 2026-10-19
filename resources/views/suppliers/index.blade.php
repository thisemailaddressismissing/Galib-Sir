@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
    {{-- Supplier Overview Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500">Total Suppliers</p>
            <p class="text-2xl font-bold text-slate-900">{{ $suppliers->total() }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Active vendors</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500">Total Invoiced / Billed</p>
            <p class="text-2xl font-bold text-slate-800">৳{{ number_format($totalBilled, 2) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">All bills & vouchers</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500">Total Paid</p>
            <p class="text-2xl font-bold text-emerald-600">৳{{ number_format($totalPaid, 2) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Deducted from cash pools</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500">Total Outstanding Due</p>
            <p class="text-2xl font-bold {{ $totalDue > 0 ? 'text-red-600' : 'text-slate-900' }}">৳{{ number_format($totalDue, 2) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Payable to suppliers</p>
        </div>
    </div>

    {{-- Filter & Action Bar --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <form method="GET" action="{{ route('suppliers.index') }}" class="flex-1 bg-white rounded-xl border border-slate-200 p-2.5 flex flex-wrap items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search supplier name, phone, email…"
                class="flex-1 min-w-48 px-3 py-1.5 border border-slate-300 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
            <select name="status" class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <option value="">All statuses</option>
                <option value="due" @selected(request('status') === 'due')>With Due Balance</option>
                <option value="cleared" @selected(request('status') === 'cleared')>Fully Cleared</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-medium">Filter</button>
            @if (request()->hasAny(['search', 'status']))
                <a href="{{ route('suppliers.index') }}" class="px-2.5 py-1.5 text-xs sm:text-sm text-slate-500 hover:underline">Reset</a>
            @endif
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-medium">CSV</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-medium">PDF</a>
        </form>
        <div class="flex items-center gap-2">
            <a href="{{ route('bill-payments.create') }}" class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium shadow-sm whitespace-nowrap">💳 Pay Bill</a>
            <a href="{{ route('supplier-bills.create') }}" class="px-3.5 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium shadow-sm whitespace-nowrap">+ Record Bill</a>
            <a href="{{ route('suppliers.create') }}" class="px-3.5 py-2 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-800 text-sm font-medium shadow-sm whitespace-nowrap">+ Add Supplier</a>
        </div>
    </div>

    {{-- Supplier Directory Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead>
                <tr class="text-left text-slate-600 bg-slate-50 border-b border-slate-200 font-semibold">
                    <th class="py-3 px-4">Supplier Name</th>
                    <th class="py-3 px-3">Contact</th>
                    <th class="py-3 px-3 text-right">Total Billed</th>
                    <th class="py-3 px-3 text-right">Total Paid</th>
                    <th class="py-3 px-3 text-right">Current Due</th>
                    <th class="py-3 px-3 text-center">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $s)
                    @php
                        $billed = (float) ($s->total_billed ?? 0);
                        $paid = (float) ($s->total_paid ?? 0);
                        $due = (float) ($s->total_due ?? 0);
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3 px-4 font-medium text-slate-900">
                            <a href="{{ route('suppliers.show', $s) }}" class="font-bold text-slate-800 hover:text-emerald-700 hover:underline">
                                {{ $s->name }}
                            </a>
                            @if ($s->designation)
                                <span class="block text-[11px] text-slate-400 font-normal">{{ $s->designation }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-slate-600 whitespace-nowrap">
                            @if ($s->phone)
                                <span class="block font-mono text-xs">{{ $s->phone }}</span>
                            @endif
                            @if ($s->email)
                                <span class="block text-[11px] text-slate-400">{{ $s->email }}</span>
                            @endif
                            @if (!$s->phone && !$s->email)
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap font-mono text-slate-800">
                            ৳{{ number_format($billed, 2) }}
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap font-mono font-semibold text-emerald-700">
                            ৳{{ number_format($paid, 2) }}
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap font-mono font-bold {{ $due > 0 ? 'text-red-600' : 'text-slate-700' }}">
                            ৳{{ number_format($due, 2) }}
                        </td>
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            @if ($due > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-50 text-red-700 border border-red-200">
                                    Due: ৳{{ number_format($due, 2) }}
                                </span>
                            @elseif ($billed > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Cleared
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                                    No bills
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right whitespace-nowrap space-x-2">
                            <a href="{{ route('supplier-bills.create', ['person_id' => $s->id]) }}" class="px-2 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-medium border border-emerald-200">+ Bill</a>
                            <a href="{{ route('suppliers.show', $s) }}" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-medium">View Ledger →</a>
                            <a href="{{ route('suppliers.edit', $s) }}" class="text-slate-400 hover:text-slate-600 text-xs">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-slate-500">
                            <p class="text-base font-medium">No suppliers found.</p>
                            <p class="text-xs text-slate-400 mt-1">Add your first supplier or adjust your filter.</p>
                            <div class="mt-3">
                                <a href="{{ route('suppliers.create') }}" class="px-3 py-1.5 rounded-lg bg-emerald-700 text-white text-xs font-medium">+ Add Supplier</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $suppliers->links() }}
@endsection
