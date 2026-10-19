<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Advance;
use App\Models\AdvanceReturn;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Person;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdvanceController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = Advance::with(['person', 'expenses', 'returns']);
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($personId = $request->get('person_id')) {
            $query->where('person_id', $personId);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest()->get()->map(function ($advance) {
                $spent = (float) $advance->expenses->sum('amount');
                $returned = (float) $advance->returns->sum('amount');

                return [
                    $advance->id,
                    $advance->person->name ?? '',
                    $advance->created_at->format('Y-m-d'),
                    number_format((float) $advance->amount, 2, '.', ''),
                    number_format($spent, 2, '.', ''),
                    number_format($returned, 2, '.', ''),
                    number_format((float) $advance->amount - $spent - $returned, 2, '.', ''),
                    ucfirst($advance->status),
                    $advance->due_date?->format('Y-m-d') ?? '',
                ];
            });

            return CsvExport::response(
                'advances-'.now()->format('Ymd-His').'.csv',
                ['ID', 'Person', 'Issued date', 'Amount', 'Spent', 'Returned', 'Balance', 'Status', 'Due date'],
                $rows
            );
        }

        $advances = $query->latest()->paginate(15)->withQueryString();
        $people = Person::where('is_active', true)->orderBy('name')->get();

        return view('advances.index', compact('advances', 'people'));
    }

    public function create(): View
    {
        return view('advances.form', [
            'advance' => new Advance(['due_date' => now()->addDays(7)->toDateString()]),
            'people' => Person::where('is_active', true)->orderBy('name')->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'person_id' => 'required|exists:people,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            $transaction = Transaction::create([
                'person_id' => $data['person_id'],
                'account_id' => $data['account_id'],
                'type' => 'expense',
                'date' => now()->toDateString(),
                'amount' => $data['amount'],
                'purpose' => 'Advance issued',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            Advance::create([
                'person_id' => $data['person_id'],
                'transaction_id' => $transaction->id,
                'amount' => $data['amount'],
                'due_date' => $data['due_date'] ?? null,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncAccountBalance($data['account_id']);
        });

        return redirect()->route('advances.index')->with('success', 'Advance issued.');
    }

    public function show(Advance $advance): View
    {
        $advance->load(['person', 'transaction.account', 'expenses.category', 'returns']);

        return view('advances.show', compact('advance'));
    }

    public function settle(Advance $advance): View
    {
        $advance->load(['person', 'expenses', 'returns']);

        return view('advances.settle', [
            'advance' => $advance,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function settleStore(Request $request, Advance $advance): RedirectResponse
    {
        if ($advance->status === 'cancelled') {
            return back()->with('error', 'Cannot settle a cancelled advance.');
        }

        $data = $request->validate([
            'vouchers' => 'nullable|array',
            'vouchers.*.amount' => 'nullable|numeric|min:0.01',
            'vouchers.*.expense_date' => 'nullable|date',
            'vouchers.*.category_id' => 'nullable|exists:categories,id',
            'vouchers.*.description' => 'nullable|string',
            'vouchers.*.reference' => 'nullable|string|max:255',
            'return_amount' => 'nullable|numeric|min:0.01',
            'account_id' => 'required_with:return_amount|nullable|exists:accounts,id',
            'returned_at' => 'required_with:return_amount|nullable|date',
            'return_notes' => 'nullable|string',
        ]);

        // Blank voucher rows are ignored; at least one voucher or a cash return is required.
        $vouchers = collect($data['vouchers'] ?? [])
            ->filter(fn ($voucher) => filled($voucher['amount'] ?? null))
            ->values();

        if ($vouchers->isEmpty() && empty($data['return_amount'])) {
            return back()->withInput()->with('error', 'Add at least one voucher or a cash return.');
        }

        DB::transaction(function () use ($advance, $vouchers, $data) {
            foreach ($vouchers as $voucher) {
                Expense::create([
                    'advance_id' => $advance->id,
                    'amount' => $voucher['amount'],
                    'expense_date' => $voucher['expense_date'] ?? now()->toDateString(),
                    'category_id' => $voucher['category_id'] ?? null,
                    'description' => $voucher['description'] ?? null,
                    'reference' => $voucher['reference'] ?? null,
                ]);
            }

            if (! empty($data['return_amount'])) {
                $transaction = Transaction::create([
                    'person_id' => $advance->person_id,
                    'account_id' => $data['account_id'],
                    'type' => 'income',
                    'date' => substr($data['returned_at'], 0, 10),
                    'amount' => $data['return_amount'],
                    'purpose' => 'Advance return',
                    'notes' => $data['return_notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                AdvanceReturn::create([
                    'advance_id' => $advance->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $data['return_amount'],
                    'returned_at' => $data['returned_at'],
                    'notes' => $data['return_notes'] ?? null,
                ]);

                $this->syncAccountBalance($data['account_id']);
            }

            $advance->recalculateStatus();
        });

        return redirect()->route('advances.show', $advance)->with('success', 'Settlement recorded.');
    }

    public function edit(Advance $advance): View
    {
        return view('advances.form', [
            'advance' => $advance,
            'people' => Person::where('is_active', true)->orderBy('name')->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Advance $advance): RedirectResponse
    {
        $data = $request->validate([
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,partial,settled,overdue,cancelled',
            'notes' => 'nullable|string',
        ]);
        $advance->update($data);

        return redirect()->route('advances.show', $advance)->with('success', 'Advance updated.');
    }

    public function destroy(Advance $advance): RedirectResponse
    {
        if ($advance->expenses()->exists() || $advance->returns()->exists()) {
            return back()->with('error', 'Cannot delete advance with expenses or returns.');
        }
        DB::transaction(function () use ($advance) {
            $accountId = $advance->transaction?->account_id;
            $advance->transaction?->delete();
            $advance->delete();
            if ($accountId) {
                $this->syncAccountBalance($accountId);
            }
        });

        return redirect()->route('advances.index')->with('success', 'Advance deleted.');
    }

    private function syncAccountBalance(int $accountId): void
    {
        Account::recalculateBalances();
    }
}
