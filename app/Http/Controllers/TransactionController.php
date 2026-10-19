<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = Transaction::with(['person', 'account', 'category']);

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($accountId = $request->get('account_id')) {
            $query->where('account_id', $accountId);
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->get('to'));
        }
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('purpose', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest('date')->latest('id')->get()->map(fn ($t) => [
                $t->date->format('Y-m-d'),
                $t->typeLabel(),
                $t->purpose ?? '',
                $t->reference ?? '',
                $t->person->name ?? '',
                $t->account->name ?? '',
                $t->category->name ?? '',
                number_format((float) $t->amount, 2, '.', ''),
                $t->notes ?? '',
            ]);

            return CsvExport::response(
                'transactions-'.now()->format('Ymd-His').'.csv',
                ['Date', 'Type', 'Purpose', 'Reference', 'Person', 'Account', 'Category', 'Amount', 'Notes'],
                $rows
            );
        }

        $transactions = $query->latest('date')->latest('id')->paginate(20)->withQueryString();
        $accounts = Account::where('is_active', true)->get();

        return view('transactions.index', compact('transactions', 'accounts'));
    }

    public function create(): View
    {
        return view('transactions.form', [
            'transaction' => new Transaction(['date' => now()->toDateString(), 'type' => 'expense']),
            'people' => Person::where('is_active', true)->orderBy('name')->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'person_id' => 'nullable|exists:people,id',
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        $data['created_by'] = auth()->id();
        $this->ensureDepositGoesToMain($data);

        $transaction = Transaction::create($data);
        $this->syncAccountBalance($transaction->account_id);

        return redirect()->route('transactions.index')->with('success', 'Transaction recorded.');
    }

    public function edit(Transaction $transaction): View
    {
        return view('transactions.form', [
            'transaction' => $transaction,
            'people' => Person::where('is_active', true)->orderBy('name')->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $oldAccountId = $transaction->account_id;
        $data = $request->validate([
            'person_id' => 'nullable|exists:people,id',
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        if (! $transaction->isTransfer() && $transaction->purpose !== 'Advance return') {
            $this->ensureDepositGoesToMain($data);
        }
        $transaction->update($data);
        $this->syncAccountBalance($transaction->account_id);
        if ($oldAccountId !== $transaction->account_id) {
            $this->syncAccountBalance($oldAccountId);
        }

        return redirect()->route('transactions.index')->with('success', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $accountId = $transaction->account_id;
        $transaction->delete();
        $this->syncAccountBalance($accountId);

        return redirect()->route('transactions.index')->with('success', 'Transaction deleted.');
    }

    private function syncAccountBalance(int $accountId): void
    {
        Account::recalculateBalances();
    }

    /** External deposits ("Transfer in") may only go into the main account. */
    private function ensureDepositGoesToMain(array $data): void
    {
        if ($data['type'] === 'income' && (int) $data['account_id'] !== Account::main()?->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account_id' => 'Only the main account can receive a deposit. Use Returns for advance returns or Transfers to fund petty cash.',
            ]);
        }
    }
}
