<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'account_id',
        'category_id',
        'type',
        'date',
        'amount',
        'purpose',
        'reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** User-facing labels. Cash only moves internally, so there is no "income". */
    public static function typeLabels(): array
    {
        return ['income' => 'Transfer in', 'expense' => 'Transfer out'];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst((string) $this->type);
    }

    /** Internal move between accounts (created by TransferController). */
    public function isTransfer(): bool
    {
        return str_starts_with((string) $this->reference, 'TRF-');
    }

    /** Real money movements (deposits, spending, returns) — excludes internal transfers. */
    public function scopeExternal($query)
    {
        return $query->where(fn ($q) => $q->whereNull('reference')->orWhere('reference', 'not like', 'TRF-%'));
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advance(): HasOne
    {
        return $this->hasOne(Advance::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
