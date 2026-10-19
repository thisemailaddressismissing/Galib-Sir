<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BillPayment;
use App\Models\Category;
use App\Models\Person;
use App\Models\SupplierBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierBillManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_list_suppliers(): void
    {
        $response = $this->post(route('suppliers.store'), [
            'name' => 'Apex Raw Materials Ltd',
            'phone' => '01711000000',
            'email' => 'apex@example.com',
            'address' => 'Tejgaon I/A, Dhaka',
            'designation' => 'Sales Director',
            'is_active' => true,
        ]);

        $supplier = Person::where('name', 'Apex Raw Materials Ltd')->first();
        $this->assertNotNull($supplier);
        $this->assertEquals('vendor', $supplier->type);
        $response->assertRedirect(route('suppliers.show', $supplier));

        $indexResponse = $this->get(route('suppliers.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Apex Raw Materials Ltd');
        $indexResponse->assertSee('Total Outstanding Due');
    }

    public function test_can_record_supplier_bill_with_voucher_attachment(): void
    {
        Storage::fake('public');

        $supplier = Person::create([
            'name' => 'Bashundhara Paper Mills',
            'type' => 'vendor',
        ]);
        $category = Category::create([
            'name' => 'Stationery & Printing',
            'type' => 'expense',
        ]);

        $file = UploadedFile::fake()->create('bill-voucher.pdf', 500, 'application/pdf');

        $response = $this->post(route('supplier-bills.store'), [
            'person_id' => $supplier->id,
            'category_id' => $category->id,
            'bill_number' => 'INV-2026-9901',
            'bill_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'amount' => 12500.00,
            'notes' => 'A4 Paper reams and cartridge supplies',
            'voucher' => $file,
        ]);

        $response->assertRedirect(route('suppliers.show', $supplier));

        $bill = SupplierBill::where('bill_number', 'INV-2026-9901')->first();
        $this->assertNotNull($bill);
        $this->assertEquals('12500.00', $bill->amount);
        $this->assertEquals('0.00', $bill->paid_amount);
        $this->assertEquals('12500.00', $bill->due_amount);
        $this->assertEquals('unpaid', $bill->status);
        $this->assertCount(1, $bill->attachments);

        Storage::disk('public')->assertExists($bill->attachments->first()->file_path);
    }

    public function test_partial_payment_from_petty_cash_deducts_properly(): void
    {
        $main = Account::create([
            'name' => 'Main Account',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 100000,
            'current_balance' => 100000,
        ]);

        $petty = Account::create([
            'name' => 'Petty Cash',
            'type' => 'cash',
            'is_main' => false,
            'opening_balance' => 20000,
            'current_balance' => 20000,
        ]);

        $supplier = Person::create([
            'name' => 'Office Supply Hub',
            'type' => 'vendor',
        ]);

        $bill = SupplierBill::create([
            'person_id' => $supplier->id,
            'bill_number' => 'OSH-001',
            'bill_date' => '2026-10-02',
            'amount' => 10000,
            'paid_amount' => 0,
            'due_amount' => 10000,
            'status' => 'unpaid',
        ]);

        // Make partial payment of 4,000 from Petty Cash
        $payResponse = $this->post(route('supplier-bills.pay.store', $bill), [
            'account_id' => $petty->id,
            'payment_date' => '2026-10-05',
            'amount' => 4000,
            'reference' => 'PC-VCH-44',
            'notes' => 'Partial cash advance payment',
        ]);

        $payResponse->assertRedirect(route('suppliers.show', $supplier));

        $bill->refresh();
        $this->assertEquals('4000.00', $bill->paid_amount);
        $this->assertEquals('6000.00', $bill->due_amount);
        $this->assertEquals('partial', $bill->status);

        // Verify transaction created
        $trx = Transaction::where('amount', 4000)->where('account_id', $petty->id)->first();
        $this->assertNotNull($trx);
        $this->assertEquals('expense', $trx->type);

        // Verify account balance updated
        $petty->refresh();
        $this->assertEquals('16000.00', $petty->current_balance);

        // Verify on dashboard
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Office Supply Hub');
        // Outstanding amount on dashboard drops by 4000 (100,000 - 4000 = 96,000)
        $dashboardResponse->assertSee('96,000.00');
    }

    public function test_full_payment_from_main_account_settles_bill(): void
    {
        $main = Account::create([
            'name' => 'Main Account',
            'type' => 'bank',
            'is_main' => true,
            'opening_balance' => 50000,
            'current_balance' => 50000,
        ]);

        $supplier = Person::create([
            'name' => 'Power Equipment Co.',
            'type' => 'vendor',
        ]);

        $bill = SupplierBill::create([
            'person_id' => $supplier->id,
            'bill_number' => 'PE-99',
            'bill_date' => '2026-10-03',
            'amount' => 15000,
            'paid_amount' => 0,
            'due_amount' => 15000,
            'status' => 'unpaid',
        ]);

        // Full payment of 15,000 from Main Account
        $payResponse = $this->post(route('supplier-bills.pay.store', $bill), [
            'account_id' => $main->id,
            'payment_date' => '2026-10-04',
            'amount' => 15000,
            'reference' => 'CHQ-5501',
        ]);

        $payResponse->assertRedirect(route('suppliers.show', $supplier));

        $bill->refresh();
        $this->assertEquals('15000.00', $bill->paid_amount);
        $this->assertEquals('0.00', $bill->due_amount);
        $this->assertEquals('paid', $bill->status);

        $main->refresh();
        $this->assertEquals('35000.00', $main->current_balance);
    }

    public function test_vendor_page_shows_accurate_paid_and_due_totals(): void
    {
        $main = Account::create([
            'name' => 'Company Main',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 80000,
            'current_balance' => 80000,
        ]);

        $supplier = Person::create([
            'name' => 'Metro Packaging',
            'type' => 'vendor',
        ]);

        // Bill 1: 20,000 (Paid 8,000, Due 12,000)
        $bill1 = SupplierBill::create([
            'person_id' => $supplier->id,
            'bill_number' => 'MP-001',
            'bill_date' => '2026-10-01',
            'amount' => 20000,
            'paid_amount' => 0,
            'due_amount' => 20000,
            'status' => 'unpaid',
        ]);
        $this->post(route('supplier-bills.pay.store', $bill1), [
            'account_id' => $main->id,
            'payment_date' => '2026-10-02',
            'amount' => 8000,
        ]);

        // Bill 2: 5,000 (Unpaid)
        SupplierBill::create([
            'person_id' => $supplier->id,
            'bill_number' => 'MP-002',
            'bill_date' => '2026-10-03',
            'amount' => 5000,
            'paid_amount' => 0,
            'due_amount' => 5000,
            'status' => 'unpaid',
        ]);

        $response = $this->get(route('suppliers.show', $supplier));
        $response->assertOk();

        // Total Billed: 25,000
        $response->assertSee('25,000.00');
        // Total Paid: 8,000
        $response->assertSee('8,000.00');
        // Total Due: 17,000
        $response->assertSee('17,000.00');
    }

    public function test_voiding_payment_reverses_cash_and_reopens_bill(): void
    {
        $main = Account::create([
            'name' => 'Company Main',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 50000,
            'current_balance' => 50000,
        ]);

        $supplier = Person::create([
            'name' => 'City Printers',
            'type' => 'vendor',
        ]);

        $bill = SupplierBill::create([
            'person_id' => $supplier->id,
            'bill_number' => 'CP-101',
            'bill_date' => '2026-10-01',
            'amount' => 6000,
            'paid_amount' => 0,
            'due_amount' => 6000,
            'status' => 'unpaid',
        ]);

        // Pay full 6,000
        $this->post(route('supplier-bills.pay.store', $bill), [
            'account_id' => $main->id,
            'payment_date' => '2026-10-02',
            'amount' => 6000,
        ]);

        $bill->refresh();
        $this->assertEquals('paid', $bill->status);
        $this->assertEquals('0.00', $bill->due_amount);

        $payment = BillPayment::where('supplier_bill_id', $bill->id)->first();
        $this->assertNotNull($payment);

        // Delete / Void payment
        $deleteResponse = $this->delete(route('bill-payments.destroy', $payment));
        $deleteResponse->assertRedirect(route('suppliers.show', $supplier));

        $bill->refresh();
        $this->assertEquals('unpaid', $bill->status);
        $this->assertEquals('0.00', $bill->paid_amount);
        $this->assertEquals('6000.00', $bill->due_amount);

        $main->refresh();
        $this->assertEquals('50000.00', $main->current_balance);
    }

    public function test_general_bill_payment_form_allows_selecting_supplier_and_bill(): void
    {
        $main = Account::create([
            'name' => 'Main Account',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 40000,
            'current_balance' => 40000,
        ]);

        $supplier = Person::create([
            'name' => 'National Electronics',
            'type' => 'vendor',
        ]);

        $bill = SupplierBill::create([
            'person_id' => $supplier->id,
            'bill_number' => 'NE-8821',
            'bill_date' => '2026-10-05',
            'amount' => 7500,
            'paid_amount' => 0,
            'due_amount' => 7500,
            'status' => 'unpaid',
        ]);

        // 1. Visit general payment form
        $createResponse = $this->get(route('bill-payments.create', ['supplier_id' => $supplier->id]));
        $createResponse->assertOk();
        $createResponse->assertSee('Pay Supplier Bill / Voucher');
        $createResponse->assertSee('National Electronics');
        $createResponse->assertSee('NE-8821');

        // 2. Submit payment using general store endpoint
        $storeResponse = $this->post(route('bill-payments.store'), [
            'supplier_bill_id' => $bill->id,
            'account_id' => $main->id,
            'payment_date' => '2026-10-06',
            'amount' => 5000,
            'reference' => 'TX-NE-5000',
            'notes' => 'General payment selecting supplier and bill',
        ]);

        $storeResponse->assertRedirect(route('suppliers.show', $supplier));

        $bill->refresh();
        $this->assertEquals('5000.00', $bill->paid_amount);
        $this->assertEquals('2500.00', $bill->due_amount);
        $this->assertEquals('partial', $bill->status);

        $main->refresh();
        $this->assertEquals('35000.00', $main->current_balance);

        $this->assertDatabaseHas('transactions', [
            'amount' => 5000,
            'account_id' => $main->id,
            'person_id' => $supplier->id,
            'type' => 'expense',
        ]);
    }
}

