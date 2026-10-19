@extends('layouts.app')

@section('title', $transaction->exists ? 'Edit transaction' : 'Add transaction')

@section('content')
    <form method="POST" action="{{ $transaction->exists ? route('transactions.update', $transaction) : route('transactions.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf
        @if ($transaction->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="type">Type *</label>
                <select id="type" name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="expense" @selected(old('type', $transaction->type) === 'expense')>Transfer out</option>
                    <option value="income" @selected(old('type', $transaction->type) === 'income')>Transfer in</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="date">Date *</label>
                <input type="date" id="date" name="date" value="{{ old('date', $transaction->date?->format('Y-m-d')) }}" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="amount">Amount (৳) *</label>
                <input type="number" id="amount" name="amount" value="{{ old('amount', $transaction->amount) }}" required min="0.01" step="0.01"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="account_id">Account *</label>
                <select id="account_id" name="account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— Select —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id', $transaction->account_id) === (string) $account->id)>{{ $account->name }}{{ $account->is_main ? ' (Main)' : ' (Sub account)' }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1">“Transfer in” (outside deposit) is only allowed into the Main account.</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="person_id">Person/Supplier</label>
                <select id="person_id" name="person_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— None —</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" @selected((string) old('person_id', $transaction->person_id) === (string) $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— None —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $transaction->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="purpose">Purpose</label>
                <input type="text" id="purpose" name="purpose" value="{{ old('purpose', $transaction->purpose) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="reference">Reference</label>
                <input type="text" id="reference" name="reference" value="{{ old('reference', $transaction->reference) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('notes', $transaction->notes) }}</textarea>
            </div>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">
                {{ $transaction->exists ? 'Update' : 'Save' }}
            </button>
            <a href="{{ route('transactions.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
