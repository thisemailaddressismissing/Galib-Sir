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

class PersonReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_report_downloads_as_pdf(): void
    {
        $person = Person::create(['name' => 'Report Person', 'type' => 'employee', 'phone' => '0123']);
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 10000, 'current_balance' => 10000,
        ]);
        Transaction::create([
            'person_id' => $person->id, 'account_id' => $account->id, 'type' => 'income',
            'date' => now()->toDateString(), 'amount' => 3000, 'purpose' => 'Funding',
        ]);
        Transaction::create([
            'person_id' => $person->id, 'account_id' => $account->id, 'type' => 'expense',
            'date' => now()->toDateString(), 'amount' => 500, 'purpose' => 'Supplies',
        ]);
        $advance = Advance::create(['person_id' => $person->id, 'amount' => 1500, 'status' => 'partial']);
        $category = Category::create(['name' => 'Travel', 'type' => 'expense']);
        Expense::create([
            'advance_id' => $advance->id, 'amount' => 1000,
            'expense_date' => now()->toDateString(), 'category_id' => $category->id,
            'description' => 'Hotel bill',
        ]);
        AdvanceReturn::create(['advance_id' => $advance->id, 'amount' => 200, 'returned_at' => now()]);

        $response = $this->get(route('people.report', $person));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("person-{$person->id}-report.pdf", $response->headers->get('Content-Disposition'));

        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $body);
        $this->assertStringEndsWith('%%EOF', $body);
        $this->assertStringContainsString('(Report Person', $body);
        $this->assertStringContainsString('(Tk. 3,000.00)', $body);
        $this->assertStringContainsString('(Hotel bill)', $body);
        $this->assertStringContainsString('(Amount)', $body);
        $this->assertStringContainsString('(Notes)', $body);
        $this->assertStringNotContainsString('(AmountNotes)', $body);
        $this->assertStringContainsString('(Balance)', $body);
        $this->assertStringContainsString('(Status)', $body);
        $this->assertStringNotContainsString('(Balance Status)', $body);
    }

    public function test_person_report_with_date_range_filters_records_and_displays_range(): void
    {
        $person = Person::create(['name' => 'Date Person', 'type' => 'vendor', 'phone' => '0199']);
        $account = Account::create([
            'name' => 'Bank', 'type' => 'bank',
            'opening_balance' => 50000, 'current_balance' => 50000,
        ]);

        // Transaction within range
        Transaction::create([
            'person_id' => $person->id, 'account_id' => $account->id, 'type' => 'expense',
            'date' => '2026-09-15', 'amount' => 2500, 'purpose' => 'InRange Tx',
        ]);
        // Transaction outside range
        Transaction::create([
            'person_id' => $person->id, 'account_id' => $account->id, 'type' => 'expense',
            'date' => '2026-08-01', 'amount' => 9999, 'purpose' => 'OutRange Tx',
        ]);

        $response = $this->get(route('people.report', [
            'person' => $person,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $body = $response->getContent();
        $this->assertStringContainsString('Date Range: 01 Sep 2026 to 30 Sep 2026', $body);
        $this->assertStringContainsString('InRange Tx', $body);
        $this->assertStringNotContainsString('OutRange Tx', $body);
    }

    public function test_people_index_exports_pdf_with_date_range(): void
    {
        $person1 = Person::create(['name' => 'Alice Worker', 'type' => 'employee']);
        $person1->created_at = '2026-09-10 10:00:00';
        $person1->save();

        $person2 = Person::create(['name' => 'Bob Supplier', 'type' => 'vendor']);
        $person2->created_at = '2026-08-01 10:00:00';
        $person2->save();

        $response = $this->get(route('people.index', [
            'export' => 'pdf',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('people-', $response->headers->get('Content-Disposition'));

        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $body);
        $this->assertStringContainsString('(People Directory)', $body);
        $this->assertStringContainsString('Date Range: 01 Sep 2026 to 30 Sep 2026', $body);
        $this->assertStringContainsString('(Alice Worker', $body);
        $this->assertStringNotContainsString('(Bob Supplier', $body);
    }
}
