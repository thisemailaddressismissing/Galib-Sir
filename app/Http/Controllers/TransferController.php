<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function create(): View
    {
        return view('transfers.create', [
            'transfer' => ['date' => now()->toDateString()],
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            $from = Account::findOrFail($data['from_account_id']);
            $to = Account::findOrFail($data['to_account_id']);
            $reference = 'TRF-'.now()->format('YmdHis');

            Transaction::create([
                'account_id' => $from->id,
                'type' => 'expense',
                'date' => $data['date'],
                'amount' => $data['amount'],
                'purpose' => 'Transfer to '.$to->name,
                'reference' => $reference,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            Transaction::create([
                'account_id' => $to->id,
                'type' => 'income',
                'date' => $data['date'],
                'amount' => $data['amount'],
                'purpose' => 'Transfer from '.$from->name,
                'reference' => $reference,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncAccountBalance($from->id);
            $this->syncAccountBalance($to->id);
        });

        return redirect()->route('accounts.index')->with('success', 'Transfer completed.');
    }

    private function syncAccountBalance(int $accountId): void
    {
        Account::recalculateBalances();
    }
}
