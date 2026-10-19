<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_moves_money_between_accounts(): void
    {
        $from = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 5000, 'current_balance' => 5000,
        ]);
        $to = Account::create([
            'name' => 'Bank', 'type' => 'bank',
            'opening_balance' => 1000, 'current_balance' => 1000,
        ]);

        $response = $this->post(route('transfers.store'), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 1500,
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertSame(3500.0, (float) $from->fresh()->current_balance);
        $this->assertSame(2500.0, (float) $to->fresh()->current_balance);

        $legs = Transaction::orderBy('id')->get();
        $this->assertCount(2, $legs);
        $this->assertSame('expense', $legs[0]->type);
        $this->assertSame('income', $legs[1]->type);
        $this->assertSame($legs[0]->reference, $legs[1]->reference);
        $this->assertStringStartsWith('TRF-', $legs[0]->reference);
    }

    public function test_transfer_to_same_account_is_rejected(): void
    {
        $account = Account::create([
            'name' => 'Cash', 'type' => 'cash',
            'opening_balance' => 5000, 'current_balance' => 5000,
        ]);

        $response = $this->from(route('transfers.create'))
            ->post(route('transfers.store'), [
                'from_account_id' => $account->id,
                'to_account_id' => $account->id,
                'amount' => 100,
                'date' => now()->toDateString(),
            ]);

        $response->assertRedirect(route('transfers.create'));
        $response->assertSessionHasErrors('to_account_id');
        $this->assertSame(0, Transaction::count());
    }
}
