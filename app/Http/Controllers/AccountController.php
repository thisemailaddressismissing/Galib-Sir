<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = Account::query();
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest()->get()->map(fn ($a) => [
                $a->name,
                ucfirst(str_replace('_', ' ', $a->type)),
                $a->account_number ?? '',
                $a->bank_name ?? '',
                number_format((float) $a->opening_balance, 2, '.', ''),
                number_format((float) $a->current_balance, 2, '.', ''),
                $a->is_active ? 'Active' : 'Inactive',
                $a->created_at->format('Y-m-d'),
            ]);

            return CsvExport::response(
                'accounts-'.now()->format('Ymd-His').'.csv',
                ['Name', 'Type', 'Number', 'Bank', 'Opening', 'Current', 'Status', 'Created'],
                $rows
            );
        }

        $accounts = $query->latest()->paginate(15)->withQueryString();

        return view('accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        return view('accounts.form', ['account' => new Account()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:accounts,name',
            'type' => 'required|in:cash,bank,mobile_banking,other',
            'account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'opening_balance' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        $data['current_balance'] = $data['opening_balance'];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_main'] = $request->boolean('is_main') || ! Account::where('is_main', true)->exists();
        $account = Account::create($data);
        $this->applyMainFlag($account);

        return redirect()->route('accounts.index')->with('success', 'Account created.');
    }

    public function edit(Account $account): View
    {
        return view('accounts.form', compact('account'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:accounts,name,'.$account->id,
            'type' => 'required|in:cash,bank,mobile_banking,other',
            'account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'opening_balance' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_main'] = $request->boolean('is_main');
        $account->update($data);
        $this->applyMainFlag($account);

        return redirect()->route('accounts.index')->with('success', 'Account updated.');
    }

    /** Keep exactly one main account, then recompute balances under the new roles. */
    private function applyMainFlag(Account $account): void
    {
        if ($account->is_main) {
            Account::where('id', '!=', $account->id)->update(['is_main' => false]);
        } elseif (! Account::where('is_main', true)->exists()) {
            Account::orderBy('id')->limit(1)->update(['is_main' => true]);
        }
        Account::recalculateBalances();
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->transactions()->exists()) {
            return back()->with('error', 'Cannot delete account with transactions.');
        }
        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Account deleted.');
    }
}
