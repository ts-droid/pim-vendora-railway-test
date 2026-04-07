<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'to',
        'cc',
        'bcc',
        'subject',
        'body',
        'attachments',
        'sender_name',
        'sender_email',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];
}
