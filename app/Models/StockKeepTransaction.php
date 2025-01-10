<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockKeepTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_number',
        'identifiers',
        'values',
        'diffs',
        'type',
        'status',
    ];
}
