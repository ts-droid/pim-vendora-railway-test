<?php

namespace App\Models;

use App\Http\Controllers\DoSpacesController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'tab_id',
        'type',
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
        'valid_until',
        'collectables',
        'collectables_data',
    ];

    public function recipients()
    {
        return $this->hasMany(SignDocumentRecipient::class);
    }

    public function getAccessHash()
    {
        return md5($this->id . $this->created_at . '6VYADDntAadd%aH4mM');
    }

    public function getDocumentContent()
    {
        return DoSpacesController::getContent($this->filename);
    }

    public function base64PDF()
    {
        return base64_encode($this->getDocumentContent());
    }
}
