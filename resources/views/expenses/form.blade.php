@extends('layouts.app')

@section('title', $expense->exists ? 'Edit expense' : 'Add expense')

@section('content')
    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf
        @if ($expense->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="advance_id">Advance</label>
                <select id="advance_id" name="advance_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— None (direct expense) —</option>
                    @foreach ($advances as $advance)
                        <option value="{{ $advance->id }}" @selected((string) old('advance_id', $expense->advance_id) === (string) $advance->id)>#{{ $advance->id }} — {{ $advance->person->name ?? '—' }} (৳{{ number_format($advance->amount, 2) }}, {{ $advance->status }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— None —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $expense->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="amount">Amount (৳) *</label>
                <input type="number" id="amount" name="amount" value="{{ old('amount', $expense->amount) }}" required min="0.01" step="0.01"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="expense_date">Date *</label>
                <input type="date" id="expense_date" name="expense_date" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="reference">Reference</label>
                <input type="text" id="reference" name="reference" value="{{ old('reference', $expense->reference) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="description">Description</label>
                <textarea id="description" name="description" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('description', $expense->description) }}</textarea>
            </div>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">
                {{ $expense->exists ? 'Update' : 'Save' }}
            </button>
            <a href="{{ route('expenses.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
