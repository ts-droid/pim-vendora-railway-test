<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaData extends Model
{
    use HasFactory;

    /**
     * The meta_data table uses `name` as its primary key.
     */
    protected $primaryKey = 'name';

    /**
     * `name` is a string key and it does not auto-increment.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'value'
    ];

    protected $casts = [
        'value' => 'array'
    ];
}
