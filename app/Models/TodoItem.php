<?php

namespace App\Models;

use App\Enums\TodoQueue;
use App\Enums\TodoType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TodoItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue',
        'type',
        'list_order',
        'title',
        'description',
        'data',
        'created_by',
        'reserved_by',
        'reserved_at',
        'completed_at',
        'source',
        'on_hold',
    ];

    protected $casts = [
        'queue' => TodoQueue::class,
        'type' => TodoType::class,
        'data' => 'array',
    ];
}
