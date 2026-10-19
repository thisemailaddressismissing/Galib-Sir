<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Person;
use App\Models\SupplierBill;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SupplierBillController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = SupplierBill::with(['person', 'category', 'payments']);

        if ($personId = $request->get('person_id')) {
            $query->where('person_id', $personId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($request->filled('from')) {
            $query->whereDate('bill_date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('bill_date', '<=', $request->get('to'));
        }
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('person', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest('bill_date')->get()->map(fn ($b) => [
                $b->bill_date->format('Y-m-d'),
                $b->bill_number ?? '',
                $b->person->name ?? '',
                $b->category->name ?? '',
                number_format((float) $b->amount, 2, '.', ''),
                number_format((float) $b->paid_amount, 2, '.', ''),
                number_format((float) $b->due_amount, 2, '.', ''),
                ucfirst($b->status),
                $b->due_date ? $b->due_date->format('Y-m-d') : '',
                $b->notes ?? '',
            ]);

            return CsvExport::response(
                'supplier-bills-'.now()->format('Ymd-His').'.csv',
                ['Date', 'Bill #', 'Supplier', 'Category', 'Total Amount', 'Paid', 'Due', 'Status', 'Due Date', 'Notes'],
                $rows
            );
        }

        $bills = $query->latest('bill_date')->latest('id')->paginate(20)->withQueryString();
        $suppliers = Person::where('type', 'vendor')->orderBy('name')->get();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('supplier_bills.index', compact('bills', 'suppliers', 'categories'));
    }

    public function create(Request $request): View
    {
        return view('supplier_bills.form', [
            'bill' => new SupplierBill([
                'person_id' => $request->get('person_id'),
                'bill_date' => now()->toDateString(),
            ]),
            'suppliers' => Person::where('type', 'vendor')->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'person_id' => 'required|exists:people,id',
            'category_id' => 'nullable|exists:categories,id',
            'bill_number' => 'nullable|string|max:255',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'voucher' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $bill = DB::transaction(function () use ($data, $request) {
            $bill = SupplierBill::create([
                'person_id' => $data['person_id'],
                'category_id' => $data['category_id'] ?? null,
                'bill_number' => $data['bill_number'] ?? null,
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'amount' => $data['amount'],
                'paid_amount' => 0.00,
                'due_amount' => $data['amount'],
                'status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($request->hasFile('voucher')) {
                $file = $request->file('voucher');
                $path = $file->store('attachments/bills', 'public');

                Attachment::create([
                    'supplier_bill_id' => $bill->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);
            }

            return $bill;
        });

        return redirect()->route('suppliers.show', $bill->person_id)->with('success', 'Bill / Voucher saved successfully.');
    }

    public function show(SupplierBill $supplierBill): View
    {
        $supplierBill->load(['person', 'category', 'payments.account', 'attachments']);

        return view('supplier_bills.show', ['bill' => $supplierBill]);
    }

    public function edit(SupplierBill $supplierBill): View
    {
        return view('supplier_bills.form', [
            'bill' => $supplierBill,
            'suppliers' => Person::where('type', 'vendor')->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SupplierBill $supplierBill): RedirectResponse
    {
        $data = $request->validate([
            'person_id' => 'required|exists:people,id',
            'category_id' => 'nullable|exists:categories,id',
            'bill_number' => 'nullable|string|max:255',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'voucher' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        if ((float) $data['amount'] < (float) $supplierBill->paid_amount) {
            return back()->withErrors(['amount' => 'Bill amount cannot be less than the already paid amount (৳'.number_format((float) $supplierBill->paid_amount, 2).').'])->withInput();
        }

        DB::transaction(function () use ($data, $supplierBill, $request) {
            $supplierBill->update([
                'person_id' => $data['person_id'],
                'category_id' => $data['category_id'] ?? null,
                'bill_number' => $data['bill_number'] ?? null,
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            $supplierBill->recalculateStatus();

            if ($request->hasFile('voucher')) {
                $file = $request->file('voucher');
                $path = $file->store('attachments/bills', 'public');

                Attachment::create([
                    'supplier_bill_id' => $supplierBill->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        });

        return redirect()->route('suppliers.show', $supplierBill->person_id)->with('success', 'Bill updated successfully.');
    }

    public function destroy(SupplierBill $supplierBill): RedirectResponse
    {
        $personId = $supplierBill->person_id;

        if ($supplierBill->payments()->exists()) {
            return back()->with('error', 'Cannot delete bill with existing payments. Delete or reverse the payments first.');
        }

        DB::transaction(function () use ($supplierBill) {
            foreach ($supplierBill->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }
            $supplierBill->delete();
        });

        return redirect()->route('suppliers.show', $personId)->with('success', 'Bill deleted successfully.');
    }
}
