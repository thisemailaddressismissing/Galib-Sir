<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BillPayment;
use App\Models\Person;
use App\Models\SupplierBill;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillPaymentController extends Controller
{
    public function create(Request $request, ?SupplierBill $supplierBill = null): View|RedirectResponse
    {
        $selectedBill = ($supplierBill && $supplierBill->exists)
            ? $supplierBill
            : ($request->filled('bill_id') ? SupplierBill::find($request->get('bill_id')) : null);

        if ($selectedBill && $selectedBill->due_amount <= 0) {
            return redirect()->route('suppliers.show', $selectedBill->person_id)
                ->with('error', 'This bill has already been fully paid.');
        }

        $selectedSupplierId = $selectedBill?->person_id ?? $request->get('supplier_id');

        $suppliers = Person::where('type', 'vendor')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $dueBills = SupplierBill::where('due_amount', '>', 0)
            ->with(['person', 'category'])
            ->orderBy('bill_date', 'asc')
            ->get();

        $accounts = Account::where('is_active', true)
            ->orderBy('is_main', 'desc')
            ->orderBy('name')
            ->get();

        return view('bill_payments.create', [
            'selectedBill' => $selectedBill,
            'selectedSupplierId' => $selectedSupplierId,
            'suppliers' => $suppliers,
            'dueBills' => $dueBills,
            'accounts' => $accounts,
            'defaultAmount' => $selectedBill ? (float) $selectedBill->due_amount : 0.0,
        ]);
    }

    public function store(Request $request, ?SupplierBill $supplierBill = null): RedirectResponse
    {
        $billId = ($supplierBill && $supplierBill->exists) ? $supplierBill->id : $request->input('supplier_bill_id');

        if (!$billId) {
            return back()->withErrors(['supplier_bill_id' => 'Please select a valid bill/voucher to pay.'])->withInput();
        }

        $bill = SupplierBill::with('person')->findOrFail($billId);

        if ($bill->due_amount <= 0) {
            return redirect()->route('suppliers.show', $bill->person_id)
                ->with('error', 'This bill has already been fully paid.');
        }

        $maxAmount = (float) $bill->due_amount;

        $data = $request->validate([
            'supplier_bill_id' => 'sometimes|exists:supplier_bills,id',
            'account_id' => 'required|exists:accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:'.$maxAmount,
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $account = Account::findOrFail($data['account_id']);

        DB::transaction(function () use ($data, $bill, $account) {
            $billLabel = $bill->bill_number ? 'Bill #'.$bill->bill_number : 'Bill #'.$bill->id;
            $purpose = "Supplier {$bill->person->name} — {$billLabel} payment";

            // 1. Create cash expense transaction
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'person_id' => $bill->person_id,
                'category_id' => $bill->category_id,
                'type' => 'expense',
                'date' => $data['payment_date'],
                'amount' => $data['amount'],
                'purpose' => $purpose,
                'reference' => $data['reference'] ?? ('BPAY-'.str_pad((string) $bill->id, 5, '0', STR_PAD_LEFT)),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // 2. Create bill payment link
            BillPayment::create([
                'supplier_bill_id' => $bill->id,
                'account_id' => $account->id,
                'transaction_id' => $transaction->id,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // 3. Recalculate bill status & account balances
            $bill->recalculateStatus();
            Account::recalculateBalances();
        });

        $paidFormatted = number_format((float) $data['amount'], 2);
        $accountType = $account->is_main ? 'Main Account' : 'Petty Cash / Sub-account';

        return redirect()->route('suppliers.show', $bill->person_id)
            ->with('success', "Payment of ৳{$paidFormatted} recorded via {$account->name} ({$accountType}).");
    }

    public function destroy(BillPayment $billPayment): RedirectResponse
    {
        $bill = $billPayment->bill;
        $personId = $bill ? $bill->person_id : null;

        DB::transaction(function () use ($billPayment, $bill) {
            $transaction = $billPayment->transaction;
            $billPayment->delete();

            if ($transaction) {
                $transaction->delete();
            }

            if ($bill) {
                $bill->recalculateStatus();
            }

            Account::recalculateBalances();
        });

        $redirectRoute = $personId ? route('suppliers.show', $personId) : route('suppliers.index');

        return redirect($redirectRoute)->with('success', 'Payment deleted and cash transaction reversed.');
    }
}
