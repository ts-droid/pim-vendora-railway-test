<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'table',
        'table_id',
        'field',
        'language_code',
        'service_id',
        'translation',
    ];

    public function service()
    {
        return $this->belongsTo(TranslationService::class);
    }
}
