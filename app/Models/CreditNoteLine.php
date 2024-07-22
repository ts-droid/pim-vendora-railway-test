<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'line_key',
        'article_number',
        'description',
        'order_number',
        'shipment_number',
        'quantity',
        'unit_price',
        'amount',
        'cost',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id', 'id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number')->with('supplier');
    }
}
