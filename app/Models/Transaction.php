<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Transaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'transactions';

    protected $fillable = [
        'statement_id',
        'owner_id',
        'card_holder',
        'card_last_digits',
        'date',
        'description',
        'amount',
        'installment',
        'installment_group_key',
        'category',
        'is_payment',
    ];

    protected $casts = [
        'amount'      => 'float',
        'is_payment'  => 'boolean',
        'installment' => 'array',
        'date'        => 'datetime',
    ];

    public function statement()
    {
        return $this->belongsTo(Statement::class, 'statement_id');
    }
}
