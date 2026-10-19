@extends('layouts.app')

@section('title', $account->exists ? 'Edit account' : 'Add account')

@section('content')
    <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf
        @if ($account->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="name">Name *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $account->name) }}" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="type">Type *</label>
                <select id="type" name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @foreach (['cash' => 'Cash', 'bank' => 'Bank', 'mobile_banking' => 'Mobile banking', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $account->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="opening_balance">Opening balance (৳) *</label>
                <input type="number" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', $account->opening_balance ?? 0) }}"
                    required min="0" step="0.01" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="account_number">Account number</label>
                <input type="text" id="account_number" name="account_number" value="{{ old('account_number', $account->account_number) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="bank_name">Bank name</label>
                <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $account->bank_name) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="description">Description</label>
                <textarea id="description" name="description" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('description', $account->description) }}</textarea>
            </div>
            <label class="sm:col-span-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->exists ? $account->is_active : true)) class="w-4 h-4">
                Active
            </label>
            <label class="sm:col-span-2 flex items-start gap-2 text-sm">
                <input type="checkbox" name="is_main" value="1" @checked(old('is_main', $account->is_main)) class="w-4 h-4 mt-0.5">
                <span>
                    Main account
                    <span class="block text-xs text-slate-500">Holds the real balance and is the only account that can receive outside deposits. Other accounts (e.g. Petty Cash) are sub accounts — their spending also reduces the main balance.</span>
                </span>
            </label>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">
                {{ $account->exists ? 'Update' : 'Save' }}
            </button>
            <a href="{{ route('accounts.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
