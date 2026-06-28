<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Statement extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'statements';

    protected $fillable = [
        'owner_id',
        'bank_label',
        'card_last_digits',
        'reference_month',
        'due_date',
        'closing_date',
        'total_amount',
        'previous_balance',
        'raw_file',
    ];

    protected $casts = [
        'total_amount'      => 'float',
        'previous_balance'  => 'float',
        'due_date'          => 'datetime',
        'closing_date'      => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'statement_id');
    }
}
