<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Balance rules:
 *  - The main account holds the real money. Only it receives external deposits.
 *  - Sub accounts (e.g. Petty Cash) only track a portion of that money.
 *  - Internal transfers (reference "TRF-...") move money between main and sub
 *    accounts; they change the sub account's balance but NOT the main balance.
 *  - Any other spending/return, from main or a sub account, updates the main
 *    balance (and the sub account's own balance when it went through it).
 */
class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'is_main',
        'account_number',
        'bank_name',
        'opening_balance',
        'current_balance',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_main' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** The main account; falls back to the oldest account if none is flagged. */
    public static function main(): ?self
    {
        return static::where('is_main', true)->first() ?? static::orderBy('id')->first();
    }

    public function isMain(): bool
    {
        return $this->id === static::main()?->id;
    }

    /** Recompute stored current_balance for every account based on its transactions. */
    public static function recalculateBalances(): void
    {
        foreach (static::all() as $account) {
            $in = (float) $account->transactions()->where('type', 'income')->sum('amount');
            $out = (float) $account->transactions()->where('type', 'expense')->sum('amount');
            $account->current_balance = (float) $account->opening_balance + $in - $out;
            $account->saveQuietly();
        }
    }

    /** Total outstanding cash across the organization (Main fund pool). */
    public static function totalOutstanding(): float
    {
        return (float) static::sum('current_balance');
    }
}
