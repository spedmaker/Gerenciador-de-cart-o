<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CategoryRule extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'category_rules';

    protected $fillable = [
        'pattern',
        'category',
    ];
}
