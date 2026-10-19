@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $expenses->total() }} expenses</p>
        <a href="{{ route('expenses.create') }}" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">+ Add expense</a>
    </div>

    <form method="GET" action="{{ route('expenses.index') }}" class="bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap gap-2">
        <select name="advance_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm max-w-64">
            <option value="">All advances</option>
            @foreach ($advances as $advance)
                <option value="{{ $advance->id }}" @selected((string) request('advance_id') === (string) $advance->id)>#{{ $advance->id }} — {{ $advance->person->name ?? '—' }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Filter</button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">Export CSV</a>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2 px-3 font-medium">Date</th>
                    <th class="py-2 px-3 font-medium">Description</th>
                    <th class="py-2 px-3 font-medium">Category</th>
                    <th class="py-2 px-3 font-medium">Advance</th>
                    <th class="py-2 px-3 font-medium text-right">Amount</th>
                    <th class="py-2 px-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $expense->expense_date->format('d M Y') }}</td>
                        <td class="py-2 px-3">{{ $expense->description ?? '—' }}
                            @if ($expense->reference)<span class="block text-xs text-slate-500">{{ $expense->reference }}</span>@endif
                        </td>
                        <td class="py-2 px-3">{{ $expense->category->name ?? '—' }}</td>
                        <td class="py-2 px-3">
                            @if ($expense->advance)
                                <a href="{{ route('advances.show', $expense->advance) }}" class="hover:underline">#{{ $expense->advance->id }} — {{ $expense->advance->person->name ?? '—' }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="py-2 px-3 text-right font-semibold whitespace-nowrap">৳{{ number_format($expense->amount, 2) }}</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <a href="{{ route('expenses.edit', $expense) }}" class="text-emerald-700 hover:underline mr-3">Edit</a>
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline" onsubmit="return confirm('Delete this expense?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 px-3 text-slate-500">No expenses found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $expenses->links() }}
@endsection
