<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ArticleImage extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
        'updated_at',
        'created_at',
    ];

    public function getBase64()
    {
        if ($this->base64) {
            return $this->base64;
        }

        $imageContent = file_get_contents($this->path_url);
        $base64 = base64_encode($imageContent);

        DB::table('article_images')
            ->where('id', $this->id)
            ->update(['base64' => $base64]);

        return $base64;
    }
}
