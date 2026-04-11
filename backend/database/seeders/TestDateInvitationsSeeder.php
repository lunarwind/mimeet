<?php

namespace Database\Seeders;

use App\Models\DateInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDateInvitationsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->where('status', 'active')->get();
        $females = $users->where('gender', 'female')->values();
        $males = $users->where('gender', 'male')->values();

        if ($females->count() < 3 || $males->count() < 3) return;

        $locations = [
            ['name' => '台北 101', 'lat' => 25.0336, 'lng' => 121.5645],
            ['name' => '信義誠品', 'lat' => 25.0380, 'lng' => 121.5681],
            ['name' => '大安森林公園', 'lat' => 25.0298, 'lng' => 121.5361],
            ['name' => '西門町', 'lat' => 25.0428, 'lng' => 121.5076],
            ['name' => '台中歌劇院', 'lat' => 24.1636, 'lng' => 120.6452],
            ['name' => '高雄愛河', 'lat' => 22.6321, 'lng' => 120.2944],
        ];

        $configs = [
            ['status' => 'pending'],
            ['status' => 'pending'],
            ['status' => 'accepted'],
            ['status' => 'accepted'],
            ['status' => 'verified'],
            ['status' => 'verified'],
            ['status' => 'verified'],
            ['status' => 'cancelled'],
        ];

        foreach ($configs as $i => $cfg) {
            $inviter = $males[$i % $males->count()];
            $invitee = $females[$i % $females->count()];
            $loc = $locations[$i % count($locations)];
            $dateTime = now()->addDays(rand(1, 14))->setTime(rand(10, 20), 0);

            $data = [
                'inviter_id' => $inviter->id,
                'invitee_id' => $invitee->id,
                'date_time' => $dateTime,
                'location_name' => $loc['name'],
                'latitude' => $loc['lat'],
                'longitude' => $loc['lng'],
                'qr_token' => bin2hex(random_bytes(32)),
                'status' => $cfg['status'],
                'expires_at' => $dateTime->copy()->addMinutes(30),
                'created_at' => now()->subDays(rand(1, 10)),
            ];

            if ($cfg['status'] === 'verified') {
                $data['inviter_scanned_at'] = now()->subDays(rand(1, 5));
                $data['invitee_scanned_at'] = now()->subDays(rand(1, 5));
                $data['inviter_gps_lat'] = $loc['lat'] + (rand(-5, 5) / 10000);
                $data['inviter_gps_lng'] = $loc['lng'] + (rand(-5, 5) / 10000);
                $data['invitee_gps_lat'] = $loc['lat'] + (rand(-5, 5) / 10000);
                $data['invitee_gps_lng'] = $loc['lng'] + (rand(-5, 5) / 10000);
                $data['inviter_gps_verified'] = 1;
                $data['invitee_gps_verified'] = 1;
                $data['gps_verification_passed'] = 1;
                $data['score_awarded'] = 5;
                $data['verified_at'] = now()->subDays(rand(1, 5));
            }

            DateInvitation::create($data);
        }

        $this->command->info('Created ' . DateInvitation::count() . ' date invitations');
    }
}
