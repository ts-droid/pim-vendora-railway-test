<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusIndicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'ping_time',
        'ping_expires',
    ];

    public function isGreen()
    {
        return time() < intval($this->ping_expires ?? 0);
    }
}
