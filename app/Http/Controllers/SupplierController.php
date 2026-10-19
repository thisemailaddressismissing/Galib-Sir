<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BillPayment;
use App\Models\Person;
use App\Support\CsvExport;
use App\Support\SimplePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = Person::where('type', 'vendor')
            ->withSum('supplierBills as total_billed', 'amount')
            ->withSum('supplierBills as total_paid', 'paid_amount')
            ->withSum('supplierBills as total_due', 'due_amount');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->get('status') === 'due') {
                $query->having('total_due', '>', 0);
            } elseif ($request->get('status') === 'cleared') {
                $query->having('total_due', '<=', 0);
            }
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->orderBy('name')->get()->map(fn ($s) => [
                $s->name,
                $s->phone ?? '',
                $s->email ?? '',
                $s->address ?? '',
                number_format((float) ($s->total_billed ?? 0), 2, '.', ''),
                number_format((float) ($s->total_paid ?? 0), 2, '.', ''),
                number_format((float) ($s->total_due ?? 0), 2, '.', ''),
                $s->is_active ? 'Active' : 'Inactive',
            ]);

            return CsvExport::response(
                'suppliers-'.now()->format('Ymd-His').'.csv',
                ['Supplier Name', 'Phone', 'Email', 'Address', 'Total Billed', 'Total Paid', 'Current Due', 'Status'],
                $rows
            );
        }

        if ($request->get('export') === 'pdf') {
            $suppliers = (clone $query)->orderBy('name')->get();
            $pdf = new SimplePdf();
            $pdf->heading('Suppliers Directory');
            $pdf->text('Total: '.$suppliers->count().' suppliers | Generated '.now()->format('d M Y, h:i A'));
            $pdf->gap(4);

            $rows = $suppliers->map(fn ($s) => [
                $s->name,
                $s->phone ?? '-',
                '৳'.number_format((float) ($s->total_billed ?? 0), 2),
                '৳'.number_format((float) ($s->total_paid ?? 0), 2),
                '৳'.number_format((float) ($s->total_due ?? 0), 2),
                $s->is_active ? 'Active' : 'Inactive',
            ])->all();

            $pdf->table(
                ['Supplier Name', 'Phone', 'Total Billed', 'Total Paid', 'Current Due', 'Status'],
                $rows,
                [140, 85, 80, 80, 80, 50],
                [false, false, true, true, true, false]
            );

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="suppliers-'.now()->format('Ymd-His').'.pdf"',
            ]);
        }

        $allVendors = (clone $query)->get();
        $totalBilled = (float) $allVendors->sum('total_billed');
        $totalPaid = (float) $allVendors->sum('total_paid');
        $totalDue = (float) $allVendors->sum('total_due');

        $suppliers = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'totalBilled', 'totalPaid', 'totalDue'));
    }

    public function create(): View
    {
        return view('suppliers.form', [
            'supplier' => new Person(['type' => 'vendor', 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'designation' => 'nullable|string|max:255',
            'nid_no' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $data['type'] = 'vendor';
        $data['is_active'] = $request->boolean('is_active', true);

        $supplier = Person::create($data);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created successfully.');
    }

    public function show(Person $supplier): View
    {
        abort_unless($supplier->type === 'vendor', 404);

        $supplier->loadSum('supplierBills as total_billed', 'amount')
            ->loadSum('supplierBills as total_paid', 'paid_amount')
            ->loadSum('supplierBills as total_due', 'due_amount');

        $bills = $supplier->supplierBills()
            ->with(['category', 'payments.account', 'attachments'])
            ->latest('bill_date')
            ->latest('id')
            ->get();

        $payments = BillPayment::whereIn('supplier_bill_id', $bills->pluck('id'))
            ->with(['bill', 'account'])
            ->latest('payment_date')
            ->latest('id')
            ->get();

        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return view('suppliers.show', compact('supplier', 'bills', 'payments', 'accounts'));
    }

    public function edit(Person $supplier): View
    {
        abort_unless($supplier->type === 'vendor', 404);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Person $supplier): RedirectResponse
    {
        abort_unless($supplier->type === 'vendor', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'designation' => 'nullable|string|max:255',
            'nid_no' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $supplier->update($data);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Person $supplier): RedirectResponse
    {
        abort_unless($supplier->type === 'vendor', 404);

        if ($supplier->supplierBills()->exists()) {
            return redirect()->route('suppliers.index')->with('error', 'Cannot delete supplier with existing bills. Settle or remove bills first.');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }
}
