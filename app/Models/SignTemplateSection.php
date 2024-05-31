<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignTemplateSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'sign_template_id',
        'title',
        'content',
    ];

    public function template()
    {
        return $this->belongsTo(SignTemplate::class);
    }
}
