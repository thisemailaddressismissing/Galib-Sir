@extends('layouts.app')

@section('title', $advance->exists ? 'Edit advance' : 'Issue advance')

@section('content')
    <form method="POST" action="{{ $advance->exists ? route('advances.update', $advance) : route('advances.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf
        @if ($advance->exists) @method('PUT') @endif

        @if ($advance->exists)
            {{-- Edit: only status / due date / notes can change --}}
            <div class="text-sm text-slate-600 bg-slate-50 rounded-lg p-3">
                Advance #{{ $advance->id }} · ৳{{ number_format($advance->amount, 2) }}
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" for="status">Status *</label>
                    <select id="status" name="status" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        @foreach (['pending' => 'Pending', 'partial' => 'Partial', 'settled' => 'Settled', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $advance->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="due_date">Due date</label>
                    <input type="date" id="due_date" name="due_date" value="{{ old('due_date', $advance->due_date?->format('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('notes', $advance->notes) }}</textarea>
                </div>
            </div>
        @else
            {{-- Create: issue a new advance (creates an expense transaction) --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" for="person_id">Person *</label>
                    <select id="person_id" name="person_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">— Select —</option>
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected((string) old('person_id', $advance->person_id) === (string) $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="account_id">Paid from account *</label>
                    <select id="account_id" name="account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">— Select —</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }} (৳{{ number_format($account->current_balance, 2) }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="amount">Amount (৳) *</label>
                    <input type="number" id="amount" name="amount" value="{{ old('amount', $advance->amount) }}" required min="0.01" step="0.01"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="due_date">Due date</label>
                    <input type="date" id="due_date" name="due_date" value="{{ old('due_date', $advance->due_date?->format('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('notes', $advance->notes) }}</textarea>
                </div>
            </div>
        @endif

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">
                {{ $advance->exists ? 'Update' : 'Issue advance' }}
            </button>
            <a href="{{ $advance->exists ? route('advances.show', $advance) : route('advances.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
