@extends('layouts.app')

@section('title', 'Settle advance #' . $advance->id)

@section('content')
    @php
        $spent = (float) $advance->expenses->sum('amount');
        $returned = (float) $advance->returns->sum('amount');
        $balance = (float) $advance->amount - $spent - $returned;
        $rows = old('vouchers', [['amount' => '', 'expense_date' => now()->toDateString(), 'category_id' => '', 'description' => '', 'reference' => '']]);
    @endphp

    <div class="flex items-center justify-between">
        <a href="{{ route('advances.show', $advance) }}" class="text-sm text-slate-600 hover:underline">← Back to advance</a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-4 text-sm flex flex-wrap gap-x-6 gap-y-1">
        <span><span class="text-slate-500">Person:</span> <strong>{{ $advance->person->name ?? '—' }}</strong></span>
        <span><span class="text-slate-500">Issued:</span> <strong>৳{{ number_format($advance->amount, 2) }}</strong></span>
        <span><span class="text-slate-500">Spent:</span> ৳{{ number_format($spent, 2) }}</span>
        <span><span class="text-slate-500">Returned:</span> ৳{{ number_format($returned, 2) }}</span>
        <span><span class="text-slate-500">Unsettled:</span> <strong class="{{ $balance > 0 ? 'text-amber-600' : 'text-emerald-600' }}">৳{{ number_format($balance, 2) }}</strong></span>
    </div>

    <form method="POST" action="{{ route('advances.settle.store', $advance) }}" class="space-y-6">
        @csrf

        {{-- Vouchers --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <h2 class="font-bold mb-1">Vouchers</h2>
            <p class="text-sm text-slate-500 mb-3">One row per bill submitted. Leave the section empty if only cash is returned.</p>
            <div id="voucherRows" class="space-y-3">
                @foreach ($rows as $i => $row)
                    <div class="voucher-row grid sm:grid-cols-12 gap-2 items-end border-b border-slate-100 pb-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium mb-1">Amount (৳)</label>
                            <input type="number" name="vouchers[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" min="0.01" step="0.01"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium mb-1">Date</label>
                            <input type="date" name="vouchers[{{ $i }}][expense_date]" value="{{ $row['expense_date'] ?? '' }}"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium mb-1">Category</label>
                            <select name="vouchers[{{ $i }}][category_id]" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                <option value="">—</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) ($row['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-medium mb-1">Description</label>
                            <input type="text" name="vouchers[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium mb-1">Reference</label>
                            <input type="text" name="vouchers[{{ $i }}][reference]" value="{{ $row['reference'] ?? '' }}"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        </div>
                        <div class="sm:col-span-1">
                            <button type="button" class="remove-row px-3 py-2 text-sm text-red-600 hover:underline">✕</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" id="addRow" class="mt-3 text-sm text-emerald-700 font-medium hover:underline">+ Add another voucher</button>
        </div>

        {{-- Cash return --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <h2 class="font-bold mb-1">Cash returned</h2>
            <p class="text-sm text-slate-500 mb-3">Remaining amount handed back. Leave the amount empty if nothing is returned.</p>
            <div class="grid sm:grid-cols-2 gap-4 max-w-2xl">
                <div>
                    <label class="block text-sm font-medium mb-1" for="return_amount">Amount (৳)</label>
                    <input type="number" id="return_amount" name="return_amount" value="{{ old('return_amount') }}" min="0.01" step="0.01"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="account_id">Returned to account</label>
                    <select id="account_id" name="account_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">— Select —</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="returned_at">Returned at</label>
                    <input type="datetime-local" id="returned_at" name="returned_at" value="{{ old('returned_at', now()->format('Y-m-d\TH:i')) }}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="return_notes">Notes</label>
                    <input type="text" id="return_notes" name="return_notes" value="{{ old('return_notes') }}"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">Record settlement</button>
            <a href="{{ route('advances.show', $advance) }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>

    <template id="voucherTemplate">
        <div class="voucher-row grid sm:grid-cols-12 gap-2 items-end border-b border-slate-100 pb-3">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium mb-1">Amount (৳)</label>
                <input type="number" name="vouchers[__INDEX__][amount]" min="0.01" step="0.01"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium mb-1">Date</label>
                <input type="date" name="vouchers[__INDEX__][expense_date]" value="{{ now()->toDateString() }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium mb-1">Category</label>
                <select name="vouchers[__INDEX__][category_id]" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">—</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-medium mb-1">Description</label>
                <input type="text" name="vouchers[__INDEX__][description]"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium mb-1">Reference</label>
                <input type="text" name="vouchers[__INDEX__][reference]"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-1">
                <button type="button" class="remove-row px-3 py-2 text-sm text-red-600 hover:underline">✕</button>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
<script>
    let voucherIndex = {{ count($rows) }};
    const rows = document.getElementById('voucherRows');
    const template = document.getElementById('voucherTemplate').innerHTML;
    document.getElementById('addRow').addEventListener('click', () => {
        rows.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', voucherIndex++));
    });
    rows.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-row') && rows.children.length > 1) {
            e.target.closest('.voucher-row').remove();
        }
    });
</script>
@endpush
