<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Advance;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Person;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|Response
    {
        $main = Account::main();
        $petty = Account::where('is_main', false)->first() ?? Account::where('id', '!=', $main?->id)->first();

        $totalIncome = (float) Transaction::where('type', 'income')->sum('amount');
        $totalExpense = (float) Transaction::where('type', 'expense')->sum('amount');
        $balance = $main ? (float) $main->current_balance : ($totalIncome - $totalExpense);

        $thisMonthIncome = (float) Transaction::where('type', 'income')
            ->whereMonth('date', now()->month)->whereYear('date', now()->year)->sum('amount');
        $thisMonthExpense = (float) Transaction::where('type', 'expense')
            ->whereMonth('date', now()->month)->whereYear('date', now()->year)->sum('amount');

        $pendingAdvances = (float) Advance::whereIn('status', ['pending', 'partial', 'overdue'])->sum('amount');
        $pendingAdvanceCount = Advance::whereIn('status', ['pending', 'partial', 'overdue'])->count();

        $stats = [
            'balance' => $balance,
            'main_balance' => $main ? (float) $main->current_balance : 0,
            'petty_balance' => $petty ? (float) $petty->current_balance : 0,
            'income' => $totalIncome,
            'expense' => $totalExpense,
            'month_income' => $thisMonthIncome,
            'month_expense' => $thisMonthExpense,
            'pending_advances' => $pendingAdvances,
            'pending_count' => $pendingAdvanceCount,
            'people' => Person::count(),
            'accounts' => Account::count(),
            'transactions' => Transaction::count(),
        ];

        $accounts = Account::where('is_active', true)->get();

        // 1. Calculate running account balance chronologically
        // Main balance is the true outstanding balance. Petty cash is a sub account.
        // Transfers between Main and Petty Cash do not alter Main's outstanding balance.
        // Spending or returns from either account directly updates Main's outstanding balance.
        $mainBal = $main ? (float) $main->opening_balance : 0;
        $pettyBal = $petty ? (float) $petty->opening_balance : 0;

        $allTransactions = Transaction::with(['person', 'account', 'category'])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($allTransactions as $t) {
            $isTrf = $t->isTransfer();
            $isPetty = $petty && $t->account_id === $petty->id;

            if ($isTrf) {
                if ($isPetty && $t->type === 'income') {
                    $pettyBal += (float) $t->amount;
                    $t->petty_amount = (float) $t->amount;
                } elseif ($isPetty && $t->type === 'expense') {
                    $pettyBal -= (float) $t->amount;
                    $t->petty_amount = -(float) $t->amount;
                } else {
                    $t->petty_amount = null;
                }
            } else {
                if ($t->type === 'income') {
                    $mainBal += (float) $t->amount;
                    if ($isPetty) {
                        $pettyBal += (float) $t->amount;
                        $t->petty_amount = (float) $t->amount;
                    } else {
                        $t->petty_amount = null;
                    }
                } else {
                    $mainBal -= (float) $t->amount;
                    if ($isPetty) {
                        $pettyBal -= (float) $t->amount;
                        $t->petty_amount = -(float) $t->amount;
                    } else {
                        $t->petty_amount = null;
                    }
                }
            }

            $t->main_balance = $mainBal;
            $t->petty_balance = $pettyBal;
            $t->outstanding_amount = $mainBal;
            $t->is_petty = $isPetty;
        }

        // 2. Filter transactions for the Excel sheet view
        $sheetTransactions = $allTransactions;

        if ($accountId = $request->get('account_id')) {
            $sheetTransactions = $sheetTransactions->where('account_id', (int) $accountId);
        }
        if ($type = $request->get('type')) {
            $sheetTransactions = $sheetTransactions->where('type', $type);
        }
        if ($from = $request->get('from')) {
            $sheetTransactions = $sheetTransactions->filter(fn ($t) => $t->date->toDateString() >= $from);
        }
        if ($to = $request->get('to')) {
            $sheetTransactions = $sheetTransactions->filter(fn ($t) => $t->date->toDateString() <= $to);
        }
        if ($search = $request->get('search')) {
            $needle = strtolower($search);
            $sheetTransactions = $sheetTransactions->filter(function ($t) use ($needle) {
                return str_contains(strtolower($t->purpose ?? ''), $needle)
                    || str_contains(strtolower($t->reference ?? ''), $needle)
                    || str_contains(strtolower($t->notes ?? ''), $needle)
                    || str_contains(strtolower($t->person->name ?? ''), $needle)
                    || str_contains(strtolower($t->account->name ?? ''), $needle)
                    || str_contains(strtolower($t->category->name ?? ''), $needle);
            });
        }

        $sort = $request->get('sort', 'asc');
        if ($sort === 'desc') {
            $sheetTransactions = $sheetTransactions->sortByDesc(fn ($t) => $t->date->toDateString().'-'.str_pad((string) $t->id, 8, '0', STR_PAD_LEFT))->values();
        } else {
            $sheetTransactions = $sheetTransactions->values();
        }

        // CSV Export of the spreadsheet view
        if ($request->get('export') === 'csv') {
            $exportRows = $sheetTransactions->map(fn ($t, $i) => [
                $i + 1,
                $t->date->format('Y-m-d'),
                $t->purpose ?? '',
                $t->person->name ?? '',
                $t->account->name ?? '',
                $t->category->name ?? '',
                $t->reference ?? '',
                number_format((float) $t->amount, 2, '.', ''),
                $t->petty_amount !== null ? number_format((float) $t->petty_amount, 2, '.', '') : '',
                number_format((float) $t->petty_balance, 2, '.', ''),
                number_format((float) $t->outstanding_amount, 2, '.', ''),
                $t->notes ?? '',
            ]);

            return CsvExport::response(
                'excel-ledger-'.now()->format('Ymd-His').'.csv',
                ['#', 'Date', 'Purpose', 'Person', 'Account', 'Category', 'Reference', 'Amount', 'Petty Cash', 'Petty Balance', 'Outstanding Amount', 'Notes'],
                $exportRows
            );
        }

        // Sheet Summary Totals
        $sheetTotalAmount = (float) $sheetTransactions->sum('amount');
        $sheetTotalIn = (float) $sheetTransactions->where('type', 'income')->sum('amount');
        $sheetTotalOut = (float) $sheetTransactions->where('type', 'expense')->sum('amount');
        $sheetExternalIn = (float) $sheetTransactions->filter(fn ($t) => ! $t->isTransfer() && $t->type === 'income')->sum('amount');
        $sheetExternalOut = (float) $sheetTransactions->filter(fn ($t) => ! $t->isTransfer() && $t->type === 'expense')->sum('amount');
        $sheetPettySpent = (float) $sheetTransactions->filter(fn ($t) => ! $t->isTransfer() && $t->is_petty && $t->type === 'expense')->sum('amount');

        $selectedAccount = $accountId ? Account::find($accountId) : null;

        // Last 6 months income vs expense for chart
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $months[] = [
                'label' => $d->format('M'),
                'income' => (float) Transaction::where('type', 'income')
                    ->whereMonth('date', $d->month)->whereYear('date', $d->year)->sum('amount'),
                'expense' => (float) Transaction::where('type', 'expense')
                    ->whereMonth('date', $d->month)->whereYear('date', $d->year)->sum('amount'),
            ];
        }

        $expensesByCategory = Expense::selectRaw('category_id, SUM(amount) as total')
            ->with('category')->groupBy('category_id')->get();
        $recentAdvances = Advance::with('person')->latest()->limit(8)->get();
        $recentExpenses = Expense::with(['advance.person', 'category'])->latest('expense_date')->limit(8)->get();

        return view('dashboard', compact(
            'stats', 'accounts', 'main', 'petty', 'sheetTransactions',
            'sheetTotalAmount', 'sheetTotalIn', 'sheetTotalOut', 'sheetExternalIn', 'sheetExternalOut', 'sheetPettySpent',
            'selectedAccount', 'sort', 'months', 'expensesByCategory', 'recentAdvances', 'recentExpenses'
        ));
    }
}
