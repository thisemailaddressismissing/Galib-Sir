<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Support\CsvExport;
use App\Support\SimplePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = Person::query();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest()->get()->map(fn ($p) => [
                $p->name,
                ucfirst($p->type),
                $p->phone ?? '',
                $p->email ?? '',
                $p->designation ?? '',
                $p->is_active ? 'Active' : 'Inactive',
                $p->created_at->format('Y-m-d'),
            ]);

            return CsvExport::response(
                'people-'.now()->format('Ymd-His').'.csv',
                ['Name', 'Type', 'Phone', 'Email', 'Designation', 'Status', 'Created'],
                $rows
            );
        }

        if ($request->get('export') === 'pdf') {
            $people = (clone $query)->latest()->get();
            $pdf = new SimplePdf();
            $pdf->heading('People Directory');
            if ($request->filled('from') || $request->filled('to')) {
                $period = 'Date Range: '.($request->filled('from') ? \Carbon\Carbon::parse($request->get('from'))->format('d M Y') : 'Start')
                    .' to '.($request->filled('to') ? \Carbon\Carbon::parse($request->get('to'))->format('d M Y') : 'Present');
                $pdf->text($period, 10, true);
            }
            if ($request->filled('type')) {
                $pdf->text('Type: '.ucfirst($request->get('type')));
            }
            $pdf->text('Total: '.$people->count().' people | Generated '.now()->format('d M Y, h:i A'));
            $pdf->gap(4);

            $rows = $people->map(fn ($p) => [
                $p->name.($p->designation ? ' ('.$p->designation.')' : ''),
                ucfirst($p->type),
                $p->phone ?? '-',
                $p->email ?? '-',
                $p->is_active ? 'Active' : 'Inactive',
                $p->created_at->format('d M Y'),
            ])->all();

            $pdf->table(
                ['Name', 'Type', 'Phone', 'Email', 'Status', 'Created'],
                $rows,
                [140, 65, 85, 105, 55, 65],
                [false, false, false, false, false, false]
            );

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="people-'.now()->format('Ymd-His').'.pdf"',
            ]);
        }

        $people = $query->latest()->paginate(15)->withQueryString();

        return view('people.index', compact('people'));
    }

    public function create(): View
    {
        return view('people.form', ['person' => new Person()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'type' => 'required|in:employee,vendor,client,other',
            'designation' => 'nullable|string|max:255',
            'nid_no' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        Person::create($data);

        return redirect()->route('people.index')->with('success', 'Person added.');
    }

    public function edit(Person $person): View
    {
        return view('people.form', compact('person'));
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'type' => 'required|in:employee,vendor,client,other',
            'designation' => 'nullable|string|max:255',
            'nid_no' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $person->update($data);

        return redirect()->route('people.index')->with('success', 'Person updated.');
    }

    public function report(Request $request, Person $person): Response
    {
        $from = $request->get('from');
        $to = $request->get('to');

        $txQuery = $person->transactions()->with('account')->orderBy('date')->orderBy('id');
        if ($from) {
            $txQuery->whereDate('date', '>=', $from);
        }
        if ($to) {
            $txQuery->whereDate('date', '<=', $to);
        }
        $transactions = $txQuery->get();

        $advQuery = $person->advances()->with([
            'expenses' => function ($q) use ($from, $to) {
                if ($from) {
                    $q->whereDate('expense_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('expense_date', '<=', $to);
                }
                $q->with('category')->orderBy('expense_date');
            },
            'returns' => function ($q) use ($from, $to) {
                if ($from) {
                    $q->whereDate('returned_at', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('returned_at', '<=', $to);
                }
                $q->orderBy('returned_at');
            },
        ])->orderBy('id');

        if ($from || $to) {
            $advQuery->where(function ($q) use ($from, $to) {
                if ($from && $to) {
                    $q->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
                        ->orWhereHas('expenses', fn ($eq) => $eq->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to))
                        ->orWhereHas('returns', fn ($rq) => $rq->whereDate('returned_at', '>=', $from)->whereDate('returned_at', '<=', $to));
                } elseif ($from) {
                    $q->whereDate('created_at', '>=', $from)
                        ->orWhereHas('expenses', fn ($eq) => $eq->whereDate('expense_date', '>=', $from))
                        ->orWhereHas('returns', fn ($rq) => $rq->whereDate('returned_at', '>=', $from));
                } else {
                    $q->whereDate('created_at', '<=', $to)
                        ->orWhereHas('expenses', fn ($eq) => $eq->whereDate('expense_date', '<=', $to))
                        ->orWhereHas('returns', fn ($rq) => $rq->whereDate('returned_at', '<=', $to));
                }
            });
        }
        $advances = $advQuery->get();

        $money = fn ($value) => 'Tk. '.number_format((float) $value, 2);

        $pdf = new SimplePdf();
        $pdf->heading('Person Report');
        $pdf->text($person->name.' ('.ucfirst($person->type).')', 12, true);
        if ($person->phone || $person->email) {
            $pdf->text(trim(($person->phone ?? '').'  '.($person->email ?? '')));
        }
        if ($from || $to) {
            $period = 'Date Range: '.($from ? \Carbon\Carbon::parse($from)->format('d M Y') : 'Start')
                .' to '.($to ? \Carbon\Carbon::parse($to)->format('d M Y') : 'Present');
            $pdf->text($period, 10, true);
        }
        $pdf->text('Generated '.now()->format('d M Y, h:i A'));
        $pdf->gap(4);

        $pdf->heading('Summary', 13);
        $pdf->table(
            ['Item', 'Amount'],
            [
                ['Cash received (transfer in)', $money($transactions->where('type', 'income')->sum('amount'))],
                ['Cash paid (transfer out)', $money($transactions->where('type', 'expense')->sum('amount'))],
                ['Advances issued', $money($advances->sum('amount'))],
                ['Advance spent (vouchers)', $money($advances->flatMap->expenses->sum('amount'))],
                ['Advance cash returned', $money($advances->flatMap->returns->sum('amount'))],
            ],
            [340, 175],
            [false, true]
        );

        if ($transactions->isNotEmpty()) {
            $pdf->heading('Transactions', 13);
            $pdf->table(
                ['Date', 'Type', 'Purpose', 'Account', 'Amount'],
                $transactions->map(fn ($t) => [
                    $t->date->format('d M Y'),
                    $t->typeLabel(),
                    $t->purpose ?? '-',
                    $t->account->name ?? '-',
                    $money($t->amount),
                ])->all(),
                [70, 75, 170, 95, 105],
                [false, false, false, false, true]
            );
        }

        if ($advances->isNotEmpty()) {
            $pdf->heading('Advances', 13);
            $pdf->table(
                ['#', 'Issued', 'Spent', 'Returned', 'Balance', 'Status'],
                $advances->map(fn ($a) => [
                    (string) $a->id,
                    $money($a->amount),
                    $money($a->expenses->sum('amount')),
                    $money($a->returns->sum('amount')),
                    $money((float) $a->amount - (float) $a->expenses->sum('amount') - (float) $a->returns->sum('amount')),
                    ucfirst($a->status),
                ])->all(),
                [30, 95, 95, 95, 100, 100],
                [false, true, true, true, true, false]
            );

            $pdf->heading('Vouchers (expenses)', 13);
            $voucherRows = [];
            foreach ($advances as $advance) {
                foreach ($advance->expenses as $expense) {
                    $voucherRows[] = [
                        $expense->expense_date->format('d M Y'),
                        '#'.$advance->id,
                        $expense->description ?? $expense->category->name ?? '-',
                        $money($expense->amount),
                    ];
                }
            }
            if ($voucherRows) {
                $pdf->table(['Date', 'Advance', 'Description', 'Amount'], $voucherRows, [75, 60, 275, 105], [false, false, false, true]);
            } else {
                $pdf->text('None');
            }

            $pdf->heading('Cash returns', 13);
            $returnRows = [];
            foreach ($advances as $advance) {
                foreach ($advance->returns as $return) {
                    $returnRows[] = [
                        $return->returned_at->format('d M Y'),
                        '#'.$advance->id,
                        $money($return->amount),
                        $return->notes ?? '-',
                    ];
                }
            }
            if ($returnRows) {
                $pdf->table(['Date', 'Advance', 'Amount', 'Notes'], $returnRows, [75, 60, 105, 275], [false, false, true, false]);
            } else {
                $pdf->text('None');
            }
        }

        $filename = 'person-'.$person->id;
        if ($from || $to) {
            $filename .= '-'.($from ?: 'start').'-to-'.($to ?: 'present');
        }
        $filename .= '-report.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function destroy(Person $person): RedirectResponse
    {
        $person->delete();

        return redirect()->route('people.index')->with('success', 'Person deleted.');
    }
}
