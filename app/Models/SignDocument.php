<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'template_id',
        'template_sections',
        'system',
        'prompt',
        'document',
        'name',
        'filename',
        'sent_at',
        'signed_at',
        'hash',
    ];

    public function recipients()
    {
        return $this->hasMany(SignDocumentRecipient::class);
    }

    public function getAccessHash()
    {
        return md5($this->id . $this->created_at . '6VYADDntAadd%aH4mM');
    }

    public function base64PDF()
    {
        return base64_encode(file_get_contents($this->filename));
    }
}
