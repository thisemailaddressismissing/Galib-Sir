@extends('layouts.app')

@section('title', 'Transfer money')

@section('content')
    <form method="POST" action="{{ route('transfers.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="from_account_id">From account *</label>
                <select id="from_account_id" name="from_account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— Select —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('from_account_id') === (string) $account->id)>{{ $account->name }} (৳{{ number_format($account->current_balance, 2) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="to_account_id">To account *</label>
                <select id="to_account_id" name="to_account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— Select —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('to_account_id') === (string) $account->id)>{{ $account->name }} (৳{{ number_format($account->current_balance, 2) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="amount">Amount (৳) *</label>
                <input type="number" id="amount" name="amount" value="{{ old('amount') }}" required min="0.01" step="0.01"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="date">Date *</label>
                <input type="date" id="date" name="date" value="{{ old('date', $transfer['date']) }}" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">Transfer</button>
            <a href="{{ route('accounts.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
