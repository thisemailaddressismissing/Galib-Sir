<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Advance;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordWritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_can_be_recorded_with_no_users_in_database(): void
    {
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 1000, 'current_balance' => 1000,
        ]);

        $response = $this->post(route('transactions.store'), [
            'account_id' => $account->id,
            'type' => 'expense',
            'date' => now()->toDateString(),
            'amount' => 2000,
            'purpose' => 'Advance issued',
        ]);

        $response->assertRedirect(route('transactions.index'));
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => 2000,
            'created_by' => null,
        ]);
    }

    public function test_advance_can_be_issued_with_no_users_in_database(): void
    {
        $person = Person::create(['name' => 'Test Person', 'type' => 'employee']);
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 5000, 'current_balance' => 5000,
        ]);

        $response = $this->post(route('advances.store'), [
            'person_id' => $person->id,
            'account_id' => $account->id,
            'amount' => 1500,
        ]);

        $response->assertRedirect(route('advances.index'));
        $this->assertDatabaseHas('advances', [
            'person_id' => $person->id,
            'amount' => 1500,
        ]);
        $this->assertDatabaseHas('transactions', ['purpose' => 'Advance issued']);
    }

    public function test_return_can_be_recorded_with_no_users_in_database(): void
    {
        $person = Person::create(['name' => 'Test Person', 'type' => 'employee']);
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 5000, 'current_balance' => 5000,
        ]);
        $advance = Advance::create([
            'person_id' => $person->id, 'amount' => 1500, 'status' => 'pending',
        ]);

        $response = $this->post(route('returns.store'), [
            'advance_id' => $advance->id,
            'account_id' => $account->id,
            'amount' => 500,
            'returned_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('returns.index'));
        $this->assertDatabaseHas('returns', [
            'advance_id' => $advance->id,
            'amount' => 500,
        ]);
    }
}
