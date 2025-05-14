<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'description',
        'email_id'
    ];

    public function email()
    {
        return $this->belongsTo(Email::class, 'email_id', 'id');
    }
}
