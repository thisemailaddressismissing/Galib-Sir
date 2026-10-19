<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advance extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'transaction_id',
        'amount',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(AdvanceReturn::class, 'advance_id');
    }

    /** Total spent against this advance. */
    public function totalSpent(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    /** Total cash handed back against this advance. */
    public function totalReturned(): float
    {
        return (float) $this->returns()->sum('amount');
    }

    /** Remaining unsettled balance: advance - expenses - returns. */
    public function balance(): float
    {
        return (float) $this->amount - $this->totalSpent() - $this->totalReturned();
    }

    /** Recompute status from spent + returned totals. Cancelled advances stay cancelled. */
    public function recalculateStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }
        $spent = (float) $this->expenses()->sum('amount');
        $returned = (float) $this->returns()->sum('amount');
        $balance = (float) $this->amount - $spent - $returned;
        $overdue = $this->due_date && $this->due_date->isPast();

        if ($balance <= 0) {
            $this->status = 'settled';
        } elseif ($spent > 0 || $returned > 0) {
            $this->status = $overdue ? 'overdue' : 'partial';
        } else {
            $this->status = $overdue ? 'overdue' : 'pending';
        }
        $this->save();
    }
}
