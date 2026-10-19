@extends('layouts.app')

@section('title', 'Supplier: ' . $supplier->name)

@section('content')
    @php
        $billed = (float) ($supplier->total_billed ?? 0);
        $paid = (float) ($supplier->total_paid ?? 0);
        $due = (float) ($supplier->total_due ?? 0);
    @endphp

    {{-- Supplier Header Card --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 uppercase tracking-wider">Supplier</span>
                    <h1 class="text-xl font-bold text-slate-900">{{ $supplier->name }}</h1>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $supplier->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
                        {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                    @if ($supplier->designation)
                        <span><strong>Contact:</strong> {{ $supplier->designation }}</span>
                    @endif
                    @if ($supplier->phone)
                        <span><strong>Phone:</strong> <a href="tel:{{ $supplier->phone }}" class="text-emerald-700 hover:underline">{{ $supplier->phone }}</a></span>
                    @endif
                    @if ($supplier->email)
                        <span><strong>Email:</strong> <a href="mailto:{{ $supplier->email }}" class="text-emerald-700 hover:underline">{{ $supplier->email }}</a></span>
                    @endif
                    @if ($supplier->address)
                        <span><strong>Address:</strong> {{ $supplier->address }}</span>
                    @endif
                    @if ($supplier->nid_no)
                        <span><strong>BIN/Tax ID:</strong> {{ $supplier->nid_no }}</span>
                    @endif
                </div>
                @if ($supplier->notes)
                    <p class="mt-2 text-xs text-slate-500 bg-slate-50 p-2 rounded border border-slate-100">{{ $supplier->notes }}</p>
                @endif
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                @if ($due > 0)
                    <a href="{{ route('bill-payments.create', ['supplier_id' => $supplier->id]) }}" class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-semibold shadow-sm flex items-center gap-1.5">
                        <span>💳</span> Pay Bill
                    </a>
                @endif
                <a href="{{ route('supplier-bills.create', ['person_id' => $supplier->id]) }}" class="px-3.5 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs sm:text-sm font-medium shadow-sm flex items-center gap-1.5">
                    <span>+</span> Record Bill / Voucher
                </a>
                <a href="{{ route('suppliers.edit', $supplier) }}" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-medium">Edit</a>
                <a href="{{ route('suppliers.index') }}" class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs sm:text-sm">← Suppliers List</a>
            </div>
        </div>
    </div>

    {{-- Financial Overview Balance Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Invoiced / Billed</p>
            <p class="text-2xl font-bold font-mono text-slate-900 mt-1">৳{{ number_format($billed, 2) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ $bills->count() }} bills / vouchers received</p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Amount Paid</p>
            <p class="text-2xl font-bold font-mono text-emerald-700 mt-1">৳{{ number_format($paid, 2) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ $payments->count() }} payments settled</p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm {{ $due > 0 ? 'bg-red-50/30 border-red-200' : 'bg-emerald-50/20' }}">
            <p class="text-xs font-semibold uppercase tracking-wider {{ $due > 0 ? 'text-red-700' : 'text-slate-500' }}">Current Outstanding Due</p>
            <p class="text-2xl font-bold font-mono mt-1 {{ $due > 0 ? 'text-red-600' : 'text-emerald-700' }}">৳{{ number_format($due, 2) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ $due > 0 ? 'Payable balance to supplier' : 'All bills settled' }}</p>
        </div>
    </div>

    {{-- Section: Bills & Vouchers --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="font-bold text-slate-800 text-sm sm:text-base">Supplier's Bills & Vouchers</h2>
                <p class="text-xs text-slate-500">Track invoices, vouchers, payments, and outstanding balances per bill</p>
            </div>
            <a href="{{ route('supplier-bills.create', ['person_id' => $supplier->id]) }}" class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-medium shadow-sm">
                + Add Bill / Voucher
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead>
                    <tr class="text-left text-slate-600 bg-slate-100/70 border-b border-slate-200 font-semibold">
                        <th class="py-2.5 px-3 whitespace-nowrap">Date</th>
                        <th class="py-2.5 px-3">Bill / Voucher #</th>
                        <th class="py-2.5 px-3">Category</th>
                        <th class="py-2.5 px-3 min-w-40">Particulars / Notes</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">Total Bill (৳)</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap text-emerald-800">Paid (৳)</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap text-red-800">Due (৳)</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-center">Voucher</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($bills as $bill)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            {{-- Bill Date --}}
                            <td class="py-2.5 px-3 whitespace-nowrap font-mono text-xs text-slate-600">
                                {{ $bill->bill_date->format('d M Y') }}
                                @if ($bill->due_date)
                                    <span class="block text-[10px] text-slate-400">Due: {{ $bill->due_date->format('d M Y') }}</span>
                                @endif
                            </td>

                            {{-- Bill # --}}
                            <td class="py-2.5 px-3 font-semibold text-slate-900 whitespace-nowrap">
                                <a href="{{ route('supplier-bills.show', $bill) }}" class="hover:text-emerald-700 hover:underline">
                                    {{ $bill->bill_number ?: ('#' . $bill->id) }}
                                </a>
                            </td>

                            {{-- Category --}}
                            <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                                {{ $bill->category->name ?? '—' }}
                            </td>

                            {{-- Notes --}}
                            <td class="py-2.5 px-3 text-slate-700">
                                <span class="line-clamp-1">{{ $bill->notes ?? '—' }}</span>
                            </td>

                            {{-- Amount --}}
                            <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-medium text-slate-800">
                                ৳{{ number_format((float) $bill->amount, 2) }}
                            </td>

                            {{-- Paid --}}
                            <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-semibold text-emerald-700">
                                ৳{{ number_format((float) $bill->paid_amount, 2) }}
                            </td>

                            {{-- Due --}}
                            <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-bold {{ $bill->due_amount > 0 ? 'text-red-600' : 'text-slate-600' }}">
                                ৳{{ number_format((float) $bill->due_amount, 2) }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                @if ($bill->status === 'paid')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        Paid
                                    </span>
                                @elseif ($bill->status === 'partial')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                        Partial
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">
                                        Unpaid
                                    </span>
                                @endif
                            </td>

                            {{-- Attached Voucher --}}
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                @if ($bill->attachments->isNotEmpty())
                                    @php $att = $bill->attachments->first(); @endphp
                                    <a href="{{ Storage::disk('public')->url($att->file_path) }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 hover:underline" title="{{ $att->file_name }}">
                                        <span>📎</span> View
                                    </a>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="py-2.5 px-3 text-right whitespace-nowrap space-x-2">
                                @if ($bill->due_amount > 0)
                                    <a href="{{ route('supplier-bills.pay', $bill) }}" class="px-2.5 py-1 rounded bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-xs">
                                        Pay Bill →
                                    </a>
                                @endif
                                <a href="{{ route('supplier-bills.show', $bill) }}" class="text-slate-600 hover:underline text-xs">View</a>
                                <a href="{{ route('supplier-bills.edit', $bill) }}" class="text-emerald-700 hover:underline text-xs">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-slate-500">
                                <p class="text-sm font-medium">No bills recorded for this supplier yet.</p>
                                <a href="{{ route('supplier-bills.create', ['person_id' => $supplier->id]) }}" class="mt-2 inline-block px-3 py-1.5 rounded-lg bg-emerald-700 text-white text-xs font-medium">+ Record First Bill</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Section: Payment History --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
            <h2 class="font-bold text-slate-800 text-sm sm:text-base">Payment History (All Settlements)</h2>
            <p class="text-xs text-slate-500">Shows all payments made against bills and the account deducted (Main or Petty Cash)</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead>
                    <tr class="text-left text-slate-600 bg-slate-100/70 border-b border-slate-200 font-semibold">
                        <th class="py-2.5 px-3 whitespace-nowrap">Payment Date</th>
                        <th class="py-2.5 px-3">Bill / Voucher</th>
                        <th class="py-2.5 px-3">Deducted From Account</th>
                        <th class="py-2.5 px-3">Reference</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">Amount Paid (৳)</th>
                        <th class="py-2.5 px-3">Notes</th>
                        <th class="py-2.5 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-2.5 px-3 whitespace-nowrap font-mono text-xs text-slate-600">
                                {{ $payment->payment_date->format('d M Y') }}
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap font-medium text-slate-900">
                                @if ($payment->bill)
                                    <a href="{{ route('supplier-bills.show', $payment->bill) }}" class="hover:text-emerald-700 hover:underline">
                                        {{ $payment->bill->bill_number ?: ('Bill #' . $payment->bill->id) }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $payment->account?->is_main ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' }}">
                                    {{ $payment->account->name ?? '—' }} ({{ $payment->account?->is_main ? 'Main' : 'Petty Cash' }})
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-xs text-slate-600 whitespace-nowrap">
                                {{ $payment->reference ?: '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-bold text-emerald-700">
                                -৳{{ number_format((float) $payment->amount, 2) }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-600 text-xs">
                                {{ $payment->notes ?: '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('bill-payments.destroy', $payment) }}" class="inline" onsubmit="return confirm('Void/delete this payment? This will reverse the account deduction and reopen the bill balance.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-xs">Void</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-400">
                                No payments recorded yet for this supplier.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
