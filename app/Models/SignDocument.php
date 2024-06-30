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
        'customer_id',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function recipients()
    {
        return $this->hasMany(SignDocumentRecipient::class);
    }

    public function mainRecipient()
    {
        return $this->recipients->where('is_main', 1)->first();
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

    public function getActiveCollectables()
    {
        $documentText = $this->document;

        $collectables = $this->collectables ? json_decode($this->collectables, true) : [];
        $collectables = array_filter($collectables, function ($collectable) use ($documentText) {
            return str_contains($documentText, $collectable);
        });

        return $collectables;
    }

    public function getFormattedDocument()
    {
        $document = $this->document;

        if (!$this->collectables) {
            return $document;
        }

        $collectableKeys = json_decode($this->collectables, true);
        $collectableData = $this->collectables_data ? json_decode($this->collectables_data, true) : [];

        if (!$collectableKeys) {
            return $document;
        }

        foreach ($collectableKeys as $key) {
            $value = $collectableData[$key] ?? '';
            $value = $value ?: '<span style="background-color: yellow;">' . $key . '</span>';

            $document = str_replace('$' . $key . '$', $value, $document);
        }

        return $document;
    }
}
