<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Advance;
use App\Models\Expense;
use App\Models\AdvanceReturn;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettleAdvanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settle_with_vouchers_and_cash_return(): void
    {
        [$advance, $account] = $this->makeAdvance(amount: 1500);

        $response = $this->post(route('advances.settle.store', $advance), [
            'vouchers' => [
                ['amount' => 1000, 'expense_date' => now()->toDateString(), 'description' => 'Hotel bill'],
            ],
            'return_amount' => 500,
            'account_id' => $account->id,
            'returned_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('advances.show', $advance));
        $this->assertDatabaseHas('expenses', [
            'advance_id' => $advance->id, 'amount' => 1000, 'description' => 'Hotel bill',
        ]);
        $this->assertDatabaseHas('returns', ['advance_id' => $advance->id, 'amount' => 500]);
        $this->assertDatabaseHas('transactions', ['purpose' => 'Advance return', 'amount' => 500]);
        $this->assertSame('settled', $advance->fresh()->status);
        $this->assertSame(5500.0, (float) $account->fresh()->current_balance);
    }

    public function test_settle_with_vouchers_only(): void
    {
        [$advance] = $this->makeAdvance(amount: 1500);

        $response = $this->post(route('advances.settle.store', $advance), [
            'vouchers' => [
                ['amount' => 1000, 'expense_date' => now()->toDateString()],
            ],
        ]);

        $response->assertRedirect(route('advances.show', $advance));
        $this->assertSame('partial', $advance->fresh()->status);
        $this->assertSame(0, AdvanceReturn::count());
    }

    public function test_settle_with_cash_only(): void
    {
        [$advance, $account] = $this->makeAdvance(amount: 1500);

        $response = $this->post(route('advances.settle.store', $advance), [
            'return_amount' => 1500,
            'account_id' => $account->id,
            'returned_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('advances.show', $advance));
        $this->assertSame('settled', $advance->fresh()->status);
        $this->assertSame(0, Expense::count());
    }

    public function test_settle_rejects_empty_submission(): void
    {
        [$advance] = $this->makeAdvance();

        $response = $this->from(route('advances.settle', $advance))
            ->post(route('advances.settle.store', $advance), [
                'vouchers' => [['amount' => '']],
            ]);

        $response->assertRedirect(route('advances.settle', $advance));
        $response->assertSessionHas('error');
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, AdvanceReturn::count());
    }

    public function test_settle_rejects_cancelled_advance(): void
    {
        [$advance] = $this->makeAdvance(status: 'cancelled');

        $response = $this->from(route('advances.settle', $advance))
            ->post(route('advances.settle.store', $advance), [
                'vouchers' => [
                    ['amount' => 100, 'expense_date' => now()->toDateString()],
                ],
            ]);

        $response->assertRedirect(route('advances.settle', $advance));
        $response->assertSessionHas('error');
        $this->assertSame(0, Expense::count());
    }

    /** @return array{Advance, Account} */
    private function makeAdvance(float $amount = 1500, string $status = 'pending'): array
    {
        $person = Person::create(['name' => 'Test Person', 'type' => 'employee']);
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 5000, 'current_balance' => 5000,
        ]);
        $advance = Advance::create([
            'person_id' => $person->id, 'amount' => $amount, 'status' => $status,
        ]);

        return [$advance, $account];
    }
}
