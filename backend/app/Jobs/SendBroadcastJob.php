<?php
<<<<<<< HEAD
namespace App\Jobs;

use App\Models\BroadcastCampaign;
=======

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Models\User;
>>>>>>> develop
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
<<<<<<< HEAD
=======
use Illuminate\Support\Facades\DB;
>>>>>>> develop
use Illuminate\Support\Facades\Log;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

<<<<<<< HEAD
    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = BroadcastCampaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== 'sending') return;

        Log::info("[Broadcast] Processing campaign #{$this->campaignId}: {$campaign->title}");
        // In production: query matching users, send notifications/DMs
        $campaign->update(['status' => 'completed', 'completed_at' => now()]);
=======
    public function __construct(
        private readonly BroadcastCampaign $campaign,
    ) {}

    public function handle(): void
    {
        $filters = $this->campaign->filters ?? [];
        $query = User::where('status', 'active');

        if (!empty($filters['gender']) && $filters['gender'] !== 'all') {
            $query->where('gender', $filters['gender']);
        }
        if (isset($filters['level_min'])) {
            $query->where('membership_level', '>=', $filters['level_min']);
        }
        if (isset($filters['level_max'])) {
            $query->where('membership_level', '<=', $filters['level_max']);
        }
        if (isset($filters['credit_min'])) {
            $query->where('credit_score', '>=', $filters['credit_min']);
        }
        if (isset($filters['credit_max'])) {
            $query->where('credit_score', '<=', $filters['credit_max']);
        }

        $sentCount = 0;

        $query->select('id')->chunkById(100, function ($users) use (&$sentCount) {
            foreach ($users as $user) {
                // Create notification for each user
                try {
                    DB::table('notifications')->insert([
                        'user_id' => $user->id,
                        'type' => 'broadcast',
                        'title' => $this->campaign->title,
                        'body' => $this->campaign->content,
                        'is_read' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $sentCount++;
                } catch (\Throwable $e) {
                    Log::warning('Broadcast send failed for user ' . $user->id, ['error' => $e->getMessage()]);
                }
            }

            // Update progress
            $this->campaign->update(['sent_count' => $sentCount]);
        });

        $this->campaign->update([
            'status' => 'completed',
            'sent_count' => $sentCount,
            'completed_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->campaign->update(['status' => 'failed']);
        Log::error('Broadcast job failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);
>>>>>>> develop
    }
}
