<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierArticlePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_number',
        'currency',
        'price',
    ];
}
