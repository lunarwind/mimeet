<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastCampaign extends Model
{
    protected $fillable = ['title', 'content', 'delivery_mode', 'target_gender', 'target_level', 'target_credit_min', 'target_credit_max', 'target_count', 'sent_count', 'status', 'created_by', 'scheduled_at', 'completed_at'];
    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];
}
