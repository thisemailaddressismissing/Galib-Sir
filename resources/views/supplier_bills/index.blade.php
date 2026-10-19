@extends('layouts.app')

@section('title', 'Supplier Bills & Vouchers')

@section('content')
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Supplier Bills & Vouchers</h1>
            <p class="text-xs text-slate-500">All invoices, vouchers, and settlement statuses</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('suppliers.index') }}" class="px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-medium">← Suppliers Directory</a>
            <a href="{{ route('supplier-bills.create') }}" class="px-3.5 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs sm:text-sm font-semibold shadow-sm">+ Record Bill</a>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <form method="GET" action="{{ route('supplier-bills.index') }}" class="bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap items-center gap-2 text-xs sm:text-sm shadow-sm">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search bill #, supplier, notes…"
            class="flex-1 min-w-44 px-3 py-1.5 border border-slate-300 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">

        <select name="person_id" class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
            <option value="">All Suppliers</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected((string) request('person_id') === (string) $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>

        <select name="status" class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
            <option value="">All Statuses</option>
            <option value="unpaid" @selected(request('status') === 'unpaid')>Unpaid</option>
            <option value="partial" @selected(request('status') === 'partial')>Partial</option>
            <option value="paid" @selected(request('status') === 'paid')>Paid</option>
        </select>

        <input type="date" name="from" value="{{ request('from') }}" title="From Date"
            class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
        <input type="date" name="to" value="{{ request('to') }}" title="To Date"
            class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">

        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-xs sm:text-sm">Filter</button>
        @if (request()->hasAny(['search', 'person_id', 'status', 'from', 'to']))
            <a href="{{ route('supplier-bills.index') }}" class="px-2 py-1.5 text-xs text-slate-500 hover:underline">Reset</a>
        @endif
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-medium ml-auto">Export CSV</a>
    </form>

    {{-- Bills Table Grid --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead>
                <tr class="text-left text-slate-600 bg-slate-50 border-b border-slate-200 font-semibold">
                    <th class="py-2.5 px-3">Date</th>
                    <th class="py-2.5 px-3">Bill / Voucher #</th>
                    <th class="py-2.5 px-3">Supplier</th>
                    <th class="py-2.5 px-3">Category</th>
                    <th class="py-2.5 px-3 text-right">Bill Total (৳)</th>
                    <th class="py-2.5 px-3 text-right text-emerald-800">Paid (৳)</th>
                    <th class="py-2.5 px-3 text-right text-red-800">Due (৳)</th>
                    <th class="py-2.5 px-3 text-center">Status</th>
                    <th class="py-2.5 px-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($bills as $bill)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-2.5 px-3 whitespace-nowrap font-mono text-xs text-slate-600">
                            {{ $bill->bill_date->format('d M Y') }}
                        </td>
                        <td class="py-2.5 px-3 font-semibold text-slate-900 whitespace-nowrap">
                            <a href="{{ route('supplier-bills.show', $bill) }}" class="hover:text-emerald-700 hover:underline">
                                {{ $bill->bill_number ?: ('#' . $bill->id) }}
                            </a>
                        </td>
                        <td class="py-2.5 px-3 font-medium text-slate-800">
                            <a href="{{ route('suppliers.show', $bill->person_id) }}" class="hover:text-emerald-700 hover:underline">
                                {{ $bill->person->name }}
                            </a>
                        </td>
                        <td class="py-2.5 px-3 text-slate-600">
                            {{ $bill->category->name ?? '—' }}
                        </td>
                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-medium text-slate-800">
                            ৳{{ number_format((float) $bill->amount, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-semibold text-emerald-700">
                            ৳{{ number_format((float) $bill->paid_amount, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-bold {{ $bill->due_amount > 0 ? 'text-red-600' : 'text-slate-600' }}">
                            ৳{{ number_format((float) $bill->due_amount, 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                            @if ($bill->status === 'paid')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                    Paid
                                </span>
                            @elseif ($bill->status === 'partial')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                    Partial
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">
                                    Unpaid
                                </span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-right whitespace-nowrap space-x-2">
                            @if ($bill->due_amount > 0)
                                <a href="{{ route('supplier-bills.pay', $bill) }}" class="px-2.5 py-1 rounded bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-xs">
                                    Pay →
                                </a>
                            @endif
                            <a href="{{ route('supplier-bills.show', $bill) }}" class="text-slate-600 hover:underline text-xs">View</a>
                            <a href="{{ route('supplier-bills.edit', $bill) }}" class="text-emerald-700 hover:underline text-xs">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-500">
                            <p class="text-sm font-medium">No supplier bills found matching filters.</p>
                            <a href="{{ route('supplier-bills.create') }}" class="mt-2 inline-block px-3 py-1.5 rounded-lg bg-emerald-700 text-white text-xs font-medium">+ Record Bill</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $bills->links() }}
@endsection
