<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\Category;
use App\Models\Expense;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = Expense::with(['advance.person', 'category']);
        if ($advanceId = $request->get('advance_id')) {
            $query->where('advance_id', $advanceId);
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->get('to'));
        }
        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest('expense_date')->get()->map(fn ($e) => [
                $e->expense_date->format('Y-m-d'),
                $e->description ?? '',
                $e->reference ?? '',
                $e->category->name ?? '',
                $e->advance ? '#'.$e->advance->id : '',
                $e->advance->person->name ?? '',
                number_format((float) $e->amount, 2, '.', ''),
            ]);

            return CsvExport::response(
                'expenses-'.now()->format('Ymd-His').'.csv',
                ['Date', 'Description', 'Reference', 'Category', 'Advance', 'Person', 'Amount'],
                $rows
            );
        }

        $expenses = $query->latest('expense_date')->paginate(20)->withQueryString();
        $advances = Advance::with('person')->latest()->limit(100)->get();

        return view('expenses.index', compact('expenses', 'advances'));
    }

    public function create(Request $request): View
    {
        return view('expenses.form', [
            'expense' => new Expense([
                'advance_id' => $request->get('advance_id'),
                'expense_date' => now()->toDateString(),
            ]),
            'advances' => Advance::with('person')->whereIn('status', ['pending', 'partial', 'overdue'])->latest()->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'advance_id' => 'nullable|exists:advances,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
        ]);
        $expense = Expense::create($data);
        $this->refreshAdvanceStatus($expense->advance_id);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense): View
    {
        return view('expenses.form', [
            'expense' => $expense,
            'advances' => Advance::with('person')->latest()->limit(200)->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'advance_id' => 'nullable|exists:advances,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
        ]);
        $oldAdvanceId = $expense->advance_id;
        $expense->update($data);
        $this->refreshAdvanceStatus($expense->advance_id);
        if ($oldAdvanceId && $oldAdvanceId !== $expense->advance_id) {
            $this->refreshAdvanceStatus($oldAdvanceId);
        }

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $advanceId = $expense->advance_id;
        $expense->delete();
        $this->refreshAdvanceStatus($advanceId);

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    private function refreshAdvanceStatus(?int $advanceId): void
    {
        if (! $advanceId) {
            return;
        }
        $advance = Advance::find($advanceId);
        if (! $advance) {
            return;
        }
        $advance->recalculateStatus();
    }
}
