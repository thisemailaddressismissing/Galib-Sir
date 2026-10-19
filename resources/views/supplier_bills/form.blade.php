@extends('layouts.app')

@section('title', $bill->exists ? 'Edit Bill / Voucher' : 'Record Supplier Bill / Voucher')

@section('content')
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-lg text-slate-800">{{ $bill->exists ? 'Edit Bill / Voucher' : 'Record Supplier Bill / Voucher' }}</h2>
            <a href="{{ $bill->person_id ? route('suppliers.show', $bill->person_id) : route('suppliers.index') }}" class="text-xs text-slate-500 hover:underline">← Back</a>
        </div>

        <form method="POST" action="{{ $bill->exists ? route('supplier-bills.update', $bill) : route('supplier-bills.store') }}" enctype="multipart/form-data"
            class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 shadow-sm space-y-4">
            @csrf
            @if ($bill->exists) @method('PUT') @endif

            <div class="grid sm:grid-cols-2 gap-4">
                {{-- Supplier --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="person_id">Supplier / Vendor *</label>
                    <select id="person_id" name="person_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">Select Supplier</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected((string) old('person_id', $bill->person_id) === (string) $s->id)>
                                {{ $s->name }} @if ($s->phone) ({{ $s->phone }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Bill / Voucher Number --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="bill_number">Bill / Voucher #</label>
                    <input type="text" id="bill_number" name="bill_number" value="{{ old('bill_number', $bill->bill_number) }}"
                        placeholder="e.g. INV-2026-0042"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                {{-- Category --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="category_id">Expense Category</label>
                    <select id="category_id" name="category_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">Select Category (optional)</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('category_id', $bill->category_id) === (string) $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Bill Date --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="bill_date">Bill Date *</label>
                    <input type="date" id="bill_date" name="bill_date" value="{{ old('bill_date', $bill->bill_date?->toDateString() ?: now()->toDateString()) }}" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                {{-- Due Date --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" value="{{ old('due_date', $bill->due_date?->toDateString()) }}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                {{-- Total Amount --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="amount">Total Bill Amount (৳) *</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount', $bill->amount) }}" required
                        placeholder="0.00"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono text-base font-semibold focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    @if ($bill->exists && $bill->paid_amount > 0)
                        <p class="text-xs text-amber-600 mt-1">Already paid: ৳{{ number_format((float) $bill->paid_amount, 2) }}. Amount cannot be less than this.</p>
                    @endif
                </div>

                {{-- Description / Notes --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="notes">Particulars / Notes</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Item details, invoice remarks, delivery reference…"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">{{ old('notes', $bill->notes) }}</textarea>
                </div>

                {{-- File Attachment (Voucher Scan/PDF) --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="voucher">Upload Bill / Voucher Attachment</label>
                    <input type="file" id="voucher" name="voucher" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                    <p class="text-[11px] text-slate-400 mt-1">Accepts JPG, PNG, PDF up to 10MB.</p>
                    @if ($bill->exists && $bill->attachments->isNotEmpty())
                        <div class="mt-2 text-xs text-slate-600 flex items-center gap-1.5">
                            <span>Current attachment:</span>
                            @foreach ($bill->attachments as $att)
                                <a href="{{ Storage::disk('public')->url($att->file_path) }}" target="_blank" class="text-emerald-700 hover:underline font-medium">
                                    📎 {{ $att->file_name }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium shadow-sm">
                    {{ $bill->exists ? 'Update Bill' : 'Save Bill / Voucher' }}
                </button>
                <a href="{{ $bill->person_id ? route('suppliers.show', $bill->person_id) : route('suppliers.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium">Cancel</a>
            </div>
        </form>
    </div>
@endsection
