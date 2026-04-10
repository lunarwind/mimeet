<?php
namespace App\Jobs;

use App\Models\BroadcastCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = BroadcastCampaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== 'sending') return;

        Log::info("[Broadcast] Processing campaign #{$this->campaignId}: {$campaign->title}");
        // In production: query matching users, send notifications/DMs
        $campaign->update(['status' => 'completed', 'completed_at' => now()]);
    }
}
