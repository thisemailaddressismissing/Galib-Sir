@extends('layouts.app')

@section('title', 'Pay Supplier Bill')

@section('content')
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-4">
            <div>
                <a href="{{ $selectedSupplierId ? route('suppliers.show', $selectedSupplierId) : route('suppliers.index') }}" class="text-xs text-slate-500 hover:underline">
                    ← Back to {{ $selectedBill ? $selectedBill->person->name : 'Suppliers' }}
                </a>
                <h2 class="font-bold text-lg text-slate-800 mt-0.5">Pay Supplier Bill / Voucher</h2>
            </div>
            <a href="{{ route('suppliers.index') }}" class="text-xs text-slate-500 hover:underline">Suppliers Directory</a>
        </div>

        {{-- Dynamic Bill Context Summary Card --}}
        <div id="billSummaryCard" class="bg-slate-50 rounded-xl border border-slate-200 p-4 mb-4 {{ $selectedBill ? '' : 'hidden' }}">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 flex-wrap gap-2">
                <div>
                    <span class="text-xs text-slate-500">Supplier:</span>
                    <p id="summarySupplierName" class="font-bold text-slate-900 text-base">
                        {{ $selectedBill ? $selectedBill->person->name : '—' }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-500">Bill / Voucher:</span>
                    <p id="summaryBillNumber" class="font-bold text-slate-800">
                        {{ $selectedBill ? ($selectedBill->bill_number ?: ('#' . $selectedBill->id)) : '—' }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 pt-3 text-center">
                <div class="bg-white p-2.5 rounded-lg border border-slate-200">
                    <span class="text-[11px] text-slate-400 block uppercase font-medium">Total Bill</span>
                    <span id="summaryTotalAmount" class="font-bold font-mono text-slate-800 text-sm">
                        ৳{{ number_format($selectedBill ? (float) $selectedBill->amount : 0, 2) }}
                    </span>
                </div>
                <div class="bg-white p-2.5 rounded-lg border border-slate-200">
                    <span class="text-[11px] text-slate-400 block uppercase font-medium">Paid So Far</span>
                    <span id="summaryPaidAmount" class="font-bold font-mono text-emerald-700 text-sm">
                        ৳{{ number_format($selectedBill ? (float) $selectedBill->paid_amount : 0, 2) }}
                    </span>
                </div>
                <div class="bg-white p-2.5 rounded-lg border border-red-200 bg-red-50/40">
                    <span class="text-[11px] text-red-600 block uppercase font-bold">Outstanding Due</span>
                    <span id="summaryDueAmount" class="font-bold font-mono text-red-600 text-sm">
                        ৳{{ number_format($selectedBill ? (float) $selectedBill->due_amount : 0, 2) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Payment Form --}}
        <form method="POST" action="{{ $selectedBill ? route('supplier-bills.pay.store', $selectedBill) : route('bill-payments.store') }}"
            class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 shadow-sm space-y-4">
            @csrf

            <div class="space-y-4">
                {{-- 1. Select Supplier --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="supplier_id">
                        1. Select Supplier / Vendor *
                    </label>
                    <select id="supplier_id" name="supplier_id" required onchange="onSupplierChange()"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">-- Choose Supplier --</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected((string) old('supplier_id', $selectedSupplierId) === (string) $s->id)>
                                {{ $s->name }} @if ($s->phone) ({{ $s->phone }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 2. Select Bill / Voucher No --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="supplier_bill_id">
                        2. Select Bill / Voucher No *
                    </label>
                    <select id="supplier_bill_id" name="supplier_bill_id" required onchange="onBillChange()"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">-- Choose Bill / Voucher --</option>
                        @foreach ($dueBills as $dbill)
                            <option value="{{ $dbill->id }}"
                                data-supplier="{{ $dbill->person_id }}"
                                data-supplier-name="{{ addslashes($dbill->person->name) }}"
                                data-bill-number="{{ addslashes($dbill->bill_number ?: ('#' . $dbill->id)) }}"
                                data-total="{{ (float) $dbill->amount }}"
                                data-paid="{{ (float) $dbill->paid_amount }}"
                                data-due="{{ (float) $dbill->due_amount }}"
                                @selected((string) old('supplier_bill_id', $selectedBill?->id) === (string) $dbill->id)>
                                {{ $dbill->person->name }} — {{ $dbill->bill_number ? 'Bill #' . $dbill->bill_number : 'Bill #' . $dbill->id }} ({{ $dbill->bill_date->format('d M Y') }}) — Due: ৳{{ number_format((float) $dbill->due_amount, 2) }}
                            </option>
                        @endforeach
                    </select>
                    <p id="noBillsMsg" class="text-xs text-amber-600 mt-1 hidden">
                        No outstanding bills found for this supplier. All bills are settled or no bills exist.
                    </p>
                </div>

                {{-- 3. Deduct Payment From Account --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="account_id">
                        3. Deduct Payment From Account *
                    </label>
                    <select id="account_id" name="account_id" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" @selected(old('account_id') == $acc->id || (!$errors->any() && $acc->is_main))>
                                {{ $acc->name }} ({{ $acc->is_main ? 'Main Account' : 'Petty Cash / Sub' }} · Available: ৳{{ number_format((float) $acc->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Select <strong>Main Account</strong> for bank/main transfers, or <strong>Petty Cash</strong> for cash voucher settlements.
                    </p>
                </div>

                {{-- 4. Payment Amount (Full or Partial) --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider" for="amount">
                            4. Payment Amount (৳) *
                        </label>
                        <button type="button" id="btnSetFullDue" onclick="setFullDue()" class="text-xs text-emerald-700 hover:underline font-medium">
                            Set Full Due
                        </button>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">৳</span>
                        <input type="number" step="0.01" min="0.01"
                            id="amount" name="amount" value="{{ old('amount', $defaultAmount ?: '') }}" required
                            placeholder="0.00"
                            class="w-full pl-8 pr-3 py-2 border border-slate-300 rounded-lg text-base font-bold font-mono focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <p id="dueAmountHint" class="text-[11px] text-slate-500 mt-1">
                        Enter any partial payment, or leave the full amount to settle the bill completely.
                    </p>
                </div>

                {{-- 5. Payment Date --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="payment_date">5. Payment Date *</label>
                    <input type="date" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                {{-- 6. Reference --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="reference">Payment Reference / Cheque #</label>
                    <input type="text" id="reference" name="reference" value="{{ old('reference') }}"
                        placeholder="e.g. Cheque #10294, TrxID 98310, Cash Voucher"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                {{-- 7. Notes --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="notes">Payment Notes</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Optional notes for this payment installment…"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold shadow-sm">
                    Confirm & Record Payment
                </button>
                <a href="{{ $selectedSupplierId ? route('suppliers.show', $selectedSupplierId) : route('suppliers.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        let currentDueAmount = {{ $selectedBill ? (float) $selectedBill->due_amount : 0 }};

        function onSupplierChange() {
            const supplierSelect = document.getElementById('supplier_id');
            const billSelect = document.getElementById('supplier_bill_id');
            const selectedSupplierId = supplierSelect.value;
            const noBillsMsg = document.getElementById('noBillsMsg');

            let visibleCount = 0;
            let firstVisibleBillId = null;

            Array.from(billSelect.options).forEach(opt => {
                if (!opt.value) return; // skip placeholder
                const billSupplier = opt.getAttribute('data-supplier');
                if (!selectedSupplierId || billSupplier === selectedSupplierId) {
                    opt.hidden = false;
                    opt.disabled = false;
                    visibleCount++;
                    if (!firstVisibleBillId) firstVisibleBillId = opt.value;
                } else {
                    opt.hidden = true;
                    opt.disabled = true;
                }
            });

            if (selectedSupplierId && visibleCount === 0) {
                noBillsMsg.classList.remove('hidden');
                billSelect.value = '';
            } else {
                noBillsMsg.classList.add('hidden');
                // If only 1 bill exists for this supplier, auto-select it
                if (visibleCount === 1) {
                    billSelect.value = firstVisibleBillId;
                } else {
                    const currentSelectedOpt = billSelect.selectedOptions[0];
                    if (currentSelectedOpt && currentSelectedOpt.hidden) {
                        billSelect.value = '';
                    }
                }
            }

            onBillChange();
        }

        function onBillChange() {
            const billSelect = document.getElementById('supplier_bill_id');
            const selectedOpt = billSelect.selectedOptions[0];
            const summaryCard = document.getElementById('billSummaryCard');
            const amountInput = document.getElementById('amount');
            const btnSetFullDue = document.getElementById('btnSetFullDue');
            const dueHint = document.getElementById('dueAmountHint');

            if (!selectedOpt || !selectedOpt.value) {
                summaryCard.classList.add('hidden');
                currentDueAmount = 0;
                amountInput.max = '';
                return;
            }

            const supplierName = selectedOpt.getAttribute('data-supplier-name');
            const billNumber = selectedOpt.getAttribute('data-bill-number');
            const total = parseFloat(selectedOpt.getAttribute('data-total') || '0');
            const paid = parseFloat(selectedOpt.getAttribute('data-paid') || '0');
            const due = parseFloat(selectedOpt.getAttribute('data-due') || '0');

            currentDueAmount = due;

            // Update Supplier dropdown if not already set
            const supplierSelect = document.getElementById('supplier_id');
            const optSupplier = selectedOpt.getAttribute('data-supplier');
            if (supplierSelect.value !== optSupplier) {
                supplierSelect.value = optSupplier;
            }

            // Update summary card
            document.getElementById('summarySupplierName').innerText = supplierName;
            document.getElementById('summaryBillNumber').innerText = billNumber;
            document.getElementById('summaryTotalAmount').innerText = '৳' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('summaryPaidAmount').innerText = '৳' + paid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('summaryDueAmount').innerText = '৳' + due.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            summaryCard.classList.remove('hidden');

            // Update amount input
            amountInput.max = due.toFixed(2);
            if (!amountInput.value || parseFloat(amountInput.value) > due || parseFloat(amountInput.value) <= 0) {
                amountInput.value = due.toFixed(2);
            }
            btnSetFullDue.innerText = 'Set Full Due (৳' + due.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ')';
            dueHint.innerText = 'Max allowed: ৳' + due.toFixed(2) + '. Enter partial amount or keep full due to settle.';
        }

        function setFullDue() {
            if (currentDueAmount > 0) {
                document.getElementById('amount').value = currentDueAmount.toFixed(2);
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('supplier_id').value) {
                onSupplierChange();
            } else if (document.getElementById('supplier_bill_id').value) {
                onBillChange();
            }
        });
    </script>
@endsection
