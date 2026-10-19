<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Advance;
use App\Models\AdvanceReturn;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdvanceReturnController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = AdvanceReturn::with(['advance.person']);
        if ($request->filled('from')) {
            $query->whereDate('returned_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('returned_at', '<=', $request->get('to'));
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest('returned_at')->get()->map(fn ($r) => [
                $r->returned_at->format('Y-m-d H:i'),
                $r->advance ? '#'.$r->advance->id : '',
                $r->advance->person->name ?? '',
                number_format((float) $r->amount, 2, '.', ''),
                $r->notes ?? '',
            ]);

            return CsvExport::response(
                'returns-'.now()->format('Ymd-His').'.csv',
                ['Returned at', 'Advance', 'Person', 'Amount', 'Notes'],
                $rows
            );
        }

        $returns = $query->latest('returned_at')->paginate(20)->withQueryString();

        return view('returns.index', compact('returns'));
    }

    public function create(Request $request): View
    {
        return view('returns.form', [
            'advanceReturn' => new AdvanceReturn([
                'advance_id' => $request->get('advance_id'),
                'returned_at' => now()->toDateTimeString('minute'),
            ]),
            'advances' => Advance::with('person')->whereIn('status', ['pending', 'partial', 'overdue'])->latest()->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'advance_id' => 'required|exists:advances,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'returned_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            $advance = Advance::findOrFail($data['advance_id']);

            $transaction = Transaction::create([
                'person_id' => $advance->person_id,
                'account_id' => $data['account_id'],
                'type' => 'income',
                'date' => substr($data['returned_at'], 0, 10),
                'amount' => $data['amount'],
                'purpose' => 'Advance return',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            AdvanceReturn::create([
                'advance_id' => $data['advance_id'],
                'transaction_id' => $transaction->id,
                'amount' => $data['amount'],
                'returned_at' => $data['returned_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            Account::recalculateBalances();
        });

        return redirect()->route('returns.index')->with('success', 'Return recorded.');
    }

    public function destroy(AdvanceReturn $return): RedirectResponse
    {
        $return->delete();

        return redirect()->route('returns.index')->with('success', 'Return deleted.');
    }
}
