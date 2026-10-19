<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Advance;
use App\Models\AdvanceReturn;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pages_render_successfully(): void
    {
        $user = User::factory()->create();
        $person = Person::create(['name' => 'Test Person', 'type' => 'employee']);
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 1000, 'current_balance' => 1000,
        ]);
        $category = Category::create(['name' => 'Office', 'type' => 'expense']);
        $transaction = Transaction::create([
            'account_id' => $account->id, 'type' => 'expense',
            'date' => now()->toDateString(), 'amount' => 50,
            'created_by' => $user->id,
        ]);
        $advance = Advance::create([
            'person_id' => $person->id, 'amount' => 500, 'status' => 'pending',
        ]);
        $expense = Expense::create([
            'advance_id' => $advance->id, 'amount' => 100,
            'expense_date' => now()->toDateString(),
        ]);
        AdvanceReturn::create([
            'advance_id' => $advance->id, 'amount' => 50, 'returned_at' => now(),
        ]);

        $pages = [
            '/',
            '/people', '/people/create', "/people/{$person->id}/edit", "/people/{$person->id}/report",
            '/accounts', '/accounts/create', "/accounts/{$account->id}/edit",
            '/categories', '/categories/create', "/categories/{$category->id}/edit",
            '/transactions', '/transactions/create', "/transactions/{$transaction->id}/edit",
            '/advances', '/advances/create',
            "/advances/{$advance->id}", "/advances/{$advance->id}/edit", "/advances/{$advance->id}/settle",
            '/expenses', '/expenses/create', "/expenses/{$expense->id}/edit",
            '/returns', '/returns/create',
            '/transfers/create',
            '/backups',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertStatus(200);
        }
    }
}
