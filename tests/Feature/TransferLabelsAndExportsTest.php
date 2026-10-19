<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Advance;
use App\Models\AdvanceReturn;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Person;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferLabelsAndExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_movement_is_labelled_transfer_not_income(): void
    {
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 1000, 'current_balance' => 1000,
        ]);
        Transaction::create([
            'account_id' => $account->id, 'type' => 'income',
            'date' => '2026-01-10', 'amount' => 500, 'purpose' => 'Transfer from Bank',
        ]);

        $this->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Transfer in')
            ->assertDontSee('Income');

        $this->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('Transfer in')
            ->assertSee('Transfer out')
            ->assertDontSee('Income');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Transfer in')
            ->assertSee('Transfer out')
            ->assertDontSee('Total income');
    }

    public function test_transactions_tab_is_last_in_sidebar(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $last = 0;
        foreach (['/advances', '/expenses', '/returns', '/people', '/accounts', '/categories', '/transactions'] as $path) {
            $pos = strpos($html, 'href="'.url($path).'"');
            $this->assertNotFalse($pos, "Missing sidebar link for {$path}");
            $this->assertGreaterThan($last, $pos, "Sidebar link {$path} is out of order");
            $last = $pos;
        }
    }

    public function test_every_data_page_offers_date_filter_and_export(): void
    {
        foreach (['transactions', 'advances', 'expenses', 'returns', 'people', 'accounts', 'categories'] as $page) {
            $this->get(route("{$page}.index"))
                ->assertOk()
                ->assertSee('name="from"', false)
                ->assertSee('name="to"', false)
                ->assertSee('Export CSV');
        }
    }

    public function test_every_data_page_exports_csv_filtered_by_date(): void
    {
        $person = Person::create(['name' => 'Export Person', 'type' => 'employee']);
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 10000, 'current_balance' => 10000,
        ]);
        Category::create(['name' => 'ExportCat', 'type' => 'expense']);

        Transaction::create([
            'account_id' => $account->id, 'type' => 'income',
            'date' => '2026-01-10', 'amount' => 500, 'purpose' => 'AlphaInRange',
        ]);
        Transaction::create([
            'account_id' => $account->id, 'type' => 'expense',
            'date' => '2026-05-10', 'amount' => 50, 'purpose' => 'BetaOutOfRange',
        ]);

        $inAdvance = Advance::create(['person_id' => $person->id, 'amount' => 111.11, 'status' => 'pending']);
        Advance::where('id', $inAdvance->id)->update(['created_at' => '2026-02-05 10:00:00']);
        Advance::create(['person_id' => $person->id, 'amount' => 222.22, 'status' => 'pending']);

        Expense::create([
            'advance_id' => $inAdvance->id, 'amount' => 10,
            'expense_date' => '2026-03-10', 'description' => 'GammaInRange',
        ]);
        Expense::create([
            'advance_id' => $inAdvance->id, 'amount' => 20,
            'expense_date' => '2026-06-10', 'description' => 'DeltaOutOfRange',
        ]);

        AdvanceReturn::create([
            'advance_id' => $inAdvance->id, 'amount' => 5,
            'returned_at' => '2026-04-10 10:00:00', 'notes' => 'EpsilonInRange',
        ]);
        AdvanceReturn::create([
            'advance_id' => $inAdvance->id, 'amount' => 6,
            'returned_at' => '2026-07-10 10:00:00', 'notes' => 'ZetaOutOfRange',
        ]);

        $inPerson = Person::create(['name' => 'EtaInRange', 'type' => 'employee']);
        Person::where('id', $inPerson->id)->update(['created_at' => '2026-01-15 10:00:00']);
        Person::create(['name' => 'ThetaOutOfRange', 'type' => 'vendor']);

        $inAccount = Account::create([
            'name' => 'IotaInRange', 'type' => 'bank',
            'opening_balance' => 0, 'current_balance' => 0,
        ]);
        Account::where('id', $inAccount->id)->update(['created_at' => '2026-01-15 10:00:00']);
        Account::create([
            'name' => 'KappaOutOfRange', 'type' => 'bank',
            'opening_balance' => 0, 'current_balance' => 0,
        ]);

        $inCategory = Category::create(['name' => 'LambdaInRange', 'type' => 'expense']);
        Category::where('id', $inCategory->id)->update(['created_at' => '2026-01-15 10:00:00']);
        Category::create(['name' => 'MuOutOfRange', 'type' => 'both']);

        $cases = [
            ['transactions', 'from=2026-01-01&to=2026-01-31', 'AlphaInRange', 'BetaOutOfRange'],
            ['advances', 'from=2026-02-01&to=2026-02-28', '111.11', '222.22'],
            ['expenses', 'from=2026-03-01&to=2026-03-31', 'GammaInRange', 'DeltaOutOfRange'],
            ['returns', 'from=2026-04-01&to=2026-04-30', 'EpsilonInRange', 'ZetaOutOfRange'],
            ['people', 'from=2026-01-01&to=2026-01-31', 'EtaInRange', 'ThetaOutOfRange'],
            ['accounts', 'from=2026-01-01&to=2026-01-31', 'IotaInRange', 'KappaOutOfRange'],
            ['categories', 'from=2026-01-01&to=2026-01-31', 'LambdaInRange', 'MuOutOfRange'],
        ];

        foreach ($cases as [$page, $range, $expected, $excluded]) {
            $response = $this->get(route("{$page}.index")."?{$range}&export=csv");

            $response->assertOk();
            $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString("{$page}-", $response->headers->get('Content-Disposition'));
            $response->assertSee($expected);
            $response->assertDontSee($excluded);
        }
    }
}
