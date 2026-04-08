<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'image_url',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
