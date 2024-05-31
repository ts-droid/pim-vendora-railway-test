<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'system',
        'prompt',
        'document',
        'filename',
        'name',
        'recipient_email',
        'recipient_name',
        'recipient_company',
        'recipient_org_nr',
    ];
}
