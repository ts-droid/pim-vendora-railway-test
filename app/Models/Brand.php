<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Brand-level default margins. Cascade:
 *   article.standard_reseller_margin (if set)
 *     → brand.standard_reseller_margin (if set)
 *       → global fallback
 */
class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'standard_reseller_margin',
        'minimum_margin',
    ];

    protected $casts = [
        'standard_reseller_margin' => 'float',
        'minimum_margin' => 'float',
    ];
}
