@extends('layouts.app')

@section('title', 'Bill #' . ($bill->bill_number ?: $bill->id))

@section('content')
    <div class="max-w-4xl space-y-5">
        {{-- Top Navigation & Actions --}}
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <a href="{{ route('suppliers.show', $bill->person_id) }}" class="text-xs text-slate-500 hover:text-emerald-700 hover:underline">← Back to {{ $bill->person->name }}</a>
                <h1 class="text-xl font-bold text-slate-900 mt-1">
                    Bill / Voucher {{ $bill->bill_number ? '#' . $bill->bill_number : '#' . $bill->id }}
                </h1>
            </div>
            <div class="flex items-center gap-2">
                @if ($bill->due_amount > 0)
                    <a href="{{ route('supplier-bills.pay', $bill) }}" class="px-3.5 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs sm:text-sm font-semibold shadow-sm">
                        Pay Bill Now (৳{{ number_format((float) $bill->due_amount, 2) }}) →
                    </a>
                @endif
                <a href="{{ route('supplier-bills.edit', $bill) }}" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-medium">Edit Bill</a>
            </div>
        </div>

        {{-- Bill Details Summary Card --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-4 border-b border-slate-100">
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Supplier</span>
                    <a href="{{ route('suppliers.show', $bill->person_id) }}" class="font-bold text-slate-800 hover:text-emerald-700 hover:underline">
                        {{ $bill->person->name }}
                    </a>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Bill Date</span>
                    <span class="font-mono text-sm text-slate-700">{{ $bill->bill_date->format('d M Y') }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Due Date</span>
                    <span class="font-mono text-sm text-slate-700">{{ $bill->due_date ? $bill->due_date->format('d M Y') : '—' }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Category</span>
                    <span class="text-sm text-slate-700">{{ $bill->category->name ?? '—' }}</span>
                </div>
            </div>

            {{-- Financial Balances --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="p-3.5 rounded-lg bg-slate-50 border border-slate-100">
                    <span class="block text-xs text-slate-500 font-medium">Total Bill</span>
                    <span class="block text-xl font-bold font-mono text-slate-900 mt-0.5">৳{{ number_format((float) $bill->amount, 2) }}</span>
                </div>
                <div class="p-3.5 rounded-lg bg-emerald-50/50 border border-emerald-100">
                    <span class="block text-xs text-emerald-800 font-medium">Paid So Far</span>
                    <span class="block text-xl font-bold font-mono text-emerald-700 mt-0.5">৳{{ number_format((float) $bill->paid_amount, 2) }}</span>
                </div>
                <div class="p-3.5 rounded-lg {{ $bill->due_amount > 0 ? 'bg-red-50/50 border-red-100' : 'bg-slate-50 border-slate-100' }}">
                    <span class="block text-xs {{ $bill->due_amount > 0 ? 'text-red-800' : 'text-slate-500' }} font-medium">Remaining Due</span>
                    <span class="block text-xl font-bold font-mono {{ $bill->due_amount > 0 ? 'text-red-600' : 'text-slate-700' }} mt-0.5">৳{{ number_format((float) $bill->due_amount, 2) }}</span>
                </div>
            </div>

            @if ($bill->notes)
                <div class="pt-2">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Particulars / Notes</span>
                    <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100">{{ $bill->notes }}</p>
                </div>
            @endif

            {{-- Attached Voucher Preview --}}
            @if ($bill->attachments->isNotEmpty())
                <div class="pt-3 border-t border-slate-100">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Attached Voucher / Invoice</span>
                    <div class="flex items-center gap-3 flex-wrap">
                        @foreach ($bill->attachments as $att)
                            <div class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 bg-slate-50 text-xs">
                                <span class="text-base">📄</span>
                                <div>
                                    <p class="font-medium text-slate-800">{{ $att->file_name }}</p>
                                    <p class="text-[10px] text-slate-400">{{ number_format($att->size / 1024, 1) }} KB</p>
                                </div>
                                <a href="{{ Storage::disk('public')->url($att->file_path) }}" target="_blank" class="ml-2 px-2.5 py-1 rounded bg-slate-900 text-white text-xs hover:bg-slate-800">
                                    Open
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Payments against this Bill --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-800 text-sm sm:text-base">Payments Settled for this Bill</h2>
                    <p class="text-xs text-slate-500">Record of full or partial payments applied to this voucher</p>
                </div>
                @if ($bill->due_amount > 0)
                    <a href="{{ route('supplier-bills.pay', $bill) }}" class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold">
                        + Make Payment
                    </a>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm">
                    <thead>
                        <tr class="text-left text-slate-600 bg-slate-100/70 border-b border-slate-200 font-semibold">
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Account Deducted</th>
                            <th class="py-2.5 px-3">Reference</th>
                            <th class="py-2.5 px-3 text-right">Amount (৳)</th>
                            <th class="py-2.5 px-3">Notes</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($bill->payments as $payment)
                            <tr class="hover:bg-slate-50/70">
                                <td class="py-2.5 px-3 whitespace-nowrap font-mono text-xs text-slate-600">
                                    {{ $payment->payment_date->format('d M Y') }}
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $payment->account?->is_main ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' }}">
                                        {{ $payment->account->name ?? '—' }} ({{ $payment->account?->is_main ? 'Main' : 'Petty Cash' }})
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-xs text-slate-600">
                                    {{ $payment->reference ?: '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-bold text-emerald-700">
                                    ৳{{ number_format((float) $payment->amount, 2) }}
                                </td>
                                <td class="py-2.5 px-3 text-slate-600 text-xs">
                                    {{ $payment->notes ?: '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <form method="POST" action="{{ route('bill-payments.destroy', $payment) }}" class="inline" onsubmit="return confirm('Void/delete this payment? This will restore the bill balance and revert the cash deduction.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline text-xs">Void</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400">
                                    No payments recorded yet for this bill.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
