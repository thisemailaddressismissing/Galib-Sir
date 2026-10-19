@extends('layouts.app')

@section('title', 'Advance #' . $advance->id)

@section('content')
    @php
        $spent = (float) $advance->expenses->sum('amount');
        $returned = (float) $advance->returns->sum('amount');
        $balance = (float) $advance->amount - $spent - $returned;
    @endphp

    <div class="flex items-center justify-between">
        <a href="{{ route('advances.index') }}" class="text-sm text-slate-600 hover:underline">← All advances</a>
        <div class="flex gap-2">
            <a href="{{ route('advances.settle', $advance) }}" class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">Settle</a>
            <a href="{{ route('advances.edit', $advance) }}" class="px-3 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Edit</a>
            <form method="POST" action="{{ route('advances.destroy', $advance) }}" class="inline" onsubmit="return confirm('Delete this advance?')">
                @csrf @method('DELETE')
                <button type="submit" class="px-3 py-2 rounded-lg bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100">Delete</button>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Advance to</p>
            <p class="text-lg font-bold">{{ $advance->person->name ?? '—' }}</p>
            <p class="text-xs text-slate-500">Due {{ $advance->due_date?->format('d M Y') ?? '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Issued</p>
            <p class="text-lg font-bold">৳{{ number_format($advance->amount, 2) }}</p>
            <p class="text-xs text-slate-500">{{ $advance->transaction->account->name ?? '' }} {{ $advance->transaction ? '· ' . $advance->transaction->date->format('d M Y') : '' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Spent / Returned</p>
            <p class="text-lg font-bold">৳{{ number_format($spent, 2) }} <span class="text-slate-400 font-normal">/ ৳{{ number_format($returned, 2) }}</span></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Balance</p>
            <p class="text-lg font-bold {{ $balance > 0 ? 'text-amber-600' : 'text-emerald-600' }}">৳{{ number_format($balance, 2) }}</p>
            <p class="text-xs text-slate-500">{{ ucfirst($advance->status) }}</p>
        </div>
    </div>

    @if ($advance->notes)
        <div class="bg-white rounded-xl border border-slate-200 p-4 text-sm">
            <span class="text-slate-500">Notes:</span> {{ $advance->notes }}
        </div>
    @endif

    {{-- Expenses against this advance --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold">Expenses ({{ $advance->expenses->count() }})</h2>
            <a href="{{ route('expenses.create', ['advance_id' => $advance->id]) }}" class="text-sm text-emerald-700 hover:underline">+ Add expense</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-200">
                        <th class="py-2 pr-3 font-medium">Date</th>
                        <th class="py-2 pr-3 font-medium">Description</th>
                        <th class="py-2 pr-3 font-medium">Category</th>
                        <th class="py-2 font-medium text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($advance->expenses as $expense)
                        <tr>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $expense->expense_date->format('d M Y') }}</td>
                            <td class="py-2 pr-3">{{ $expense->description ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ $expense->category->name ?? '—' }}</td>
                            <td class="py-2 text-right font-semibold whitespace-nowrap">৳{{ number_format($expense->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-slate-500">No expenses yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Returns against this advance --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold">Returns ({{ $advance->returns->count() }})</h2>
            <a href="{{ route('returns.create', ['advance_id' => $advance->id]) }}" class="text-sm text-emerald-700 hover:underline">+ Record return</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-200">
                        <th class="py-2 pr-3 font-medium">Returned at</th>
                        <th class="py-2 pr-3 font-medium">Notes</th>
                        <th class="py-2 font-medium text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($advance->returns as $return)
                        <tr>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $return->returned_at->format('d M Y, h:i A') }}</td>
                            <td class="py-2 pr-3">{{ $return->notes ?? '—' }}</td>
                            <td class="py-2 text-right font-semibold whitespace-nowrap">৳{{ number_format($return->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-slate-500">No returns yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
