@extends('layouts.app')

@section('title', 'Record return')

@section('content')
    <form method="POST" action="{{ route('returns.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="advance_id">Advance *</label>
                <select id="advance_id" name="advance_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— Select —</option>
                    @foreach ($advances as $advance)
                        <option value="{{ $advance->id }}" @selected((string) old('advance_id', $advanceReturn->advance_id) === (string) $advance->id)>#{{ $advance->id }} — {{ $advance->person->name ?? '—' }} (৳{{ number_format($advance->amount, 2) }}, {{ $advance->status }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="account_id">Returned to account *</label>
                <select id="account_id" name="account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">— Select —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="amount">Amount (৳) *</label>
                <input type="number" id="amount" name="amount" value="{{ old('amount', $advanceReturn->amount) }}" required min="0.01" step="0.01"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="returned_at">Returned at *</label>
                <input type="datetime-local" id="returned_at" name="returned_at" value="{{ old('returned_at', $advanceReturn->returned_at?->format('Y-m-d\TH:i')) }}" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('notes', $advanceReturn->notes) }}</textarea>
            </div>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">Save</button>
            <a href="{{ route('returns.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
