<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignDocumentRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'sign_document_id',
        'name',
        'email',
        'ip',
        'user_agent',
        'access_key',
        'signed_at',
        'sent_at',
    ];

    public function signDocument()
    {
        return $this->belongsTo(SignDocument::class);
    }
}
