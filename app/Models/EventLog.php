<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'display_name',
        'log',
        'change_key',
        'change_from',
        'change_to',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];
}
