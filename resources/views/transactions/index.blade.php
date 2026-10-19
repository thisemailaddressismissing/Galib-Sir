@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $transactions->total() }} transactions</p>
        <a href="{{ route('transactions.create') }}" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">+ Add transaction</a>
    </div>

    <form method="GET" action="{{ route('transactions.index') }}" class="bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search purpose, reference, notes…"
            class="flex-1 min-w-48 px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <select name="type" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All types</option>
            <option value="income" @selected(request('type') === 'income')>Transfer in</option>
            <option value="expense" @selected(request('type') === 'expense')>Transfer out</option>
        </select>
        <select name="account_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All accounts</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) request('account_id') === (string) $account->id)>{{ $account->name }}</option>
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
                    <th class="py-2 px-3 font-medium">Type</th>
                    <th class="py-2 px-3 font-medium">Purpose</th>
                    <th class="py-2 px-3 font-medium">Person</th>
                    <th class="py-2 px-3 font-medium">Account</th>
                    <th class="py-2 px-3 font-medium">Category</th>
                    <th class="py-2 px-3 font-medium text-right">Amount</th>
                    <th class="py-2 px-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($transactions as $transaction)
                    <tr>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $transaction->date->format('d M Y') }}</td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $transaction->type === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ $transaction->typeLabel() }}</span>
                        </td>
                        <td class="py-2 px-3">{{ $transaction->purpose ?? '—' }}
                            @if ($transaction->reference)<span class="block text-xs text-slate-500">{{ $transaction->reference }}</span>@endif
                        </td>
                        <td class="py-2 px-3">{{ $transaction->person->name ?? '—' }}</td>
                        <td class="py-2 px-3">{{ $transaction->account->name ?? '—' }}</td>
                        <td class="py-2 px-3">{{ $transaction->category->name ?? '—' }}</td>
                        <td class="py-2 px-3 text-right font-semibold whitespace-nowrap">৳{{ number_format($transaction->amount, 2) }}</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <a href="{{ route('transactions.edit', $transaction) }}" class="text-emerald-700 hover:underline mr-3">Edit</a>
                            <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" class="inline" onsubmit="return confirm('Delete this transaction?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-4 px-3 text-slate-500">No transactions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transactions->links() }}
@endsection
