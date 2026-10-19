<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardExcelSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_excel_sheet_with_outstanding_amount_per_row(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Petty Cash',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 5000,
            'current_balance' => 6000,
        ]);

        // Transaction 1: Inflow of 3000 -> balance becomes 8000
        Transaction::create([
            'account_id' => $account->id,
            'type' => 'income',
            'date' => '2026-09-01',
            'amount' => 3000,
            'purpose' => 'Replenishment',
            'created_by' => $user->id,
        ]);

        // Transaction 2: Outflow of 2000 -> balance becomes 6000
        Transaction::create([
            'account_id' => $account->id,
            'type' => 'expense',
            'date' => '2026-09-05',
            'amount' => 2000,
            'purpose' => 'Office Stationery',
            'created_by' => $user->id,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('General Cash Ledger — Excel Sheet View');
        $response->assertSee('Outstanding Amount');
        $response->assertSee('Replenishment');
        $response->assertSee('Office Stationery');

        // Check running balances in order
        $response->assertSee('8,000.00');
        $response->assertSee('6,000.00');
    }

    public function test_main_account_balance_rules_and_petty_cash_subaccount_tracking(): void
    {
        $main = Account::create([
            'name' => 'Main',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 100000,
            'current_balance' => 100000,
        ]);

        $petty = Account::create([
            'name' => 'Petty Cash',
            'type' => 'cash',
            'is_main' => false,
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);

        // 1. Transfer 20,000 from Main to Petty Cash (Internal transfer TRF)
        Transaction::create([
            'account_id' => $main->id,
            'type' => 'expense',
            'date' => '2026-09-01',
            'amount' => 20000,
            'purpose' => 'Transfer to Petty Cash',
            'reference' => 'TRF-20260901001',
        ]);
        Transaction::create([
            'account_id' => $petty->id,
            'type' => 'income',
            'date' => '2026-09-01',
            'amount' => 20000,
            'purpose' => 'Transfer from Main',
            'reference' => 'TRF-20260901001',
        ]);

        // 2. Spending 5,000 from Petty Cash
        Transaction::create([
            'account_id' => $petty->id,
            'type' => 'expense',
            'date' => '2026-09-02',
            'amount' => 5000,
            'purpose' => 'Office Supplies via Petty',
        ]);

        // 3. Advance return 1,000 back into Petty Cash
        Transaction::create([
            'account_id' => $petty->id,
            'type' => 'income',
            'date' => '2026-09-03',
            'amount' => 1000,
            'purpose' => 'Advance return',
        ]);

        // 4. Spending 2,000 directly from Main
        Transaction::create([
            'account_id' => $main->id,
            'type' => 'expense',
            'date' => '2026-09-04',
            'amount' => 2000,
            'purpose' => 'Direct Main Payment',
        ]);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Verify that:
        // - Main Outstanding Balance drops by 5000 (Petty spend), rises by 1000 (return), drops by 2000 (Main spend)
        // - Final outstanding balance = 100000 - 5000 + 1000 - 2000 = 94,000.00
        // - Petty Cash balance = 20000 - 5000 + 1000 = 16,000.00
        $response->assertSee('94,000.00');
        $response->assertSee('16,000.00');
    }

    public function test_external_deposit_only_allowed_into_main_account(): void
    {
        $main = Account::create([
            'name' => 'Main',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $petty = Account::create([
            'name' => 'Petty Cash',
            'type' => 'cash',
            'is_main' => false,
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);

        // Deposit into Petty Cash should fail validation
        $response = $this->post(route('transactions.store'), [
            'account_id' => $petty->id,
            'type' => 'income',
            'date' => '2026-09-10',
            'amount' => 5000,
            'purpose' => 'Outside Deposit Attempt',
        ]);
        $response->assertSessionHasErrors('account_id');

        // Deposit into Main account should succeed
        $validResponse = $this->post(route('transactions.store'), [
            'account_id' => $main->id,
            'type' => 'income',
            'date' => '2026-09-10',
            'amount' => 5000,
            'purpose' => 'Legitimate Main Deposit',
        ]);
        $validResponse->assertRedirect(route('transactions.index'));
        $this->assertDatabaseHas('transactions', ['purpose' => 'Legitimate Main Deposit']);
    }

    public function test_dashboard_filters_by_account_and_shows_opening_balance(): void
    {
        $account1 = Account::create([
            'name' => 'Cash Box',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 2500,
            'current_balance' => 3500,
        ]);
        $account2 = Account::create([
            'name' => 'Bank Corp',
            'type' => 'bank',
            'is_main' => false,
            'opening_balance' => 20000,
            'current_balance' => 20000,
        ]);

        Transaction::create([
            'account_id' => $account1->id,
            'type' => 'income',
            'date' => '2026-09-10',
            'amount' => 1000,
            'purpose' => 'Box Deposit',
        ]);
        Transaction::create([
            'account_id' => $account2->id,
            'type' => 'income',
            'date' => '2026-09-11',
            'amount' => 5000,
            'purpose' => 'Advance return',
        ]);

        $response = $this->get(route('dashboard', ['account_id' => $account1->id]));

        $response->assertStatus(200);
        $response->assertSee('Opening Balance');
        $response->assertSee('2,500.00');
        $response->assertSee('Box Deposit');
        $response->assertDontSee('Advance return');
    }

    public function test_dashboard_exports_excel_sheet_as_csv(): void
    {
        $account = Account::create([
            'name' => 'Main Cash',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 1000,
            'current_balance' => 1500,
        ]);
        Transaction::create([
            'account_id' => $account->id,
            'type' => 'income',
            'date' => '2026-09-20',
            'amount' => 500,
            'purpose' => 'Export Tx',
        ]);

        $response = $this->get(route('dashboard', ['export' => 'csv']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertSee('Amount');
        $response->assertDontSee('Transfer In');
        $response->assertDontSee('Transfer Out');
        $response->assertSee('Export Tx');
        $response->assertSee('1500.00');
    }

    public function test_single_amount_column_and_neutral_transfer_color(): void
    {
        $main = Account::create([
            'name' => 'Main',
            'type' => 'cash',
            'is_main' => true,
            'opening_balance' => 50000,
            'current_balance' => 50000,
        ]);

        $petty = Account::create([
            'name' => 'Petty Cash',
            'type' => 'cash',
            'is_main' => false,
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);

        // 1. Internal transfer: Main to Petty Cash
        Transaction::create([
            'account_id' => $main->id,
            'type' => 'expense',
            'date' => '2026-09-01',
            'amount' => 5000,
            'purpose' => 'Transfer to Petty Cash',
            'reference' => 'TRF-001',
        ]);
        Transaction::create([
            'account_id' => $petty->id,
            'type' => 'income',
            'date' => '2026-09-01',
            'amount' => 5000,
            'purpose' => 'Transfer from Main',
            'reference' => 'TRF-001',
        ]);

        // 2. Regular income into Main
        Transaction::create([
            'account_id' => $main->id,
            'type' => 'income',
            'date' => '2026-09-02',
            'amount' => 10000,
            'purpose' => 'Client Revenue',
        ]);

        // 3. Regular expense from Petty Cash
        Transaction::create([
            'account_id' => $petty->id,
            'type' => 'expense',
            'date' => '2026-09-03',
            'amount' => 800,
            'purpose' => 'Office Tea & Snacks',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        // Assert single Amount column header is present, separate Inflow/Outflow are removed
        $response->assertSee('Amount (৳)');
        $response->assertDontSee('Inflow (৳)');
        $response->assertDontSee('Outflow (৳)');

        // Check HTML content for neutral styling on transfer
        $content = $response->getContent();
        // The transfer should use neutral styling text-slate-600
        $this->assertStringContainsString('text-slate-600" title="Internal markdown/transfer">৳5,000.00</span>', $content);
        // Regular income has emerald styling
        $this->assertStringContainsString('text-emerald-700">+৳10,000.00</span>', $content);
        // Regular expense has red styling
        $this->assertStringContainsString('text-red-600">-৳800.00</span>', $content);
    }
}
