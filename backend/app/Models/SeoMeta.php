<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $table = 'seo_metas';

    protected $fillable = [
        'route',
        'title',
        'description',
        'og_title',
        'og_description',
        'og_image_url',
    ];
}
