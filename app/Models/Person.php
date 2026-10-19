<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'type',
        'designation',
        'nid_no',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function supplierBills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function scopeVendors($query)
    {
        return $query->where('type', 'vendor');
    }

    public function totalBilledAmount(): float
    {
        return (float) $this->supplierBills()->sum('amount');
    }

    public function totalPaidAmount(): float
    {
        return (float) $this->supplierBills()->sum('paid_amount');
    }

    public function totalDueAmount(): float
    {
        return (float) $this->supplierBills()->sum('due_amount');
    }
}
