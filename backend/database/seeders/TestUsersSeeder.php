<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Test1234!');

        // Fixed test accounts
        $testAccounts = [
            ['email' => 'female_lv1@test.tw', 'gender' => 'female', 'membership_level' => 1, 'credit_score' => 75, 'nickname' => '測試女一級'],
            ['email' => 'female_lv2@test.tw', 'gender' => 'female', 'membership_level' => 2, 'credit_score' => 85, 'nickname' => '測試女二級'],
            ['email' => 'female_lv3@test.tw', 'gender' => 'female', 'membership_level' => 3, 'credit_score' => 90, 'nickname' => '測試女付費'],
            ['email' => 'male_lv0@test.tw', 'gender' => 'male', 'membership_level' => 0, 'credit_score' => 60, 'nickname' => '測試男零級'],
            ['email' => 'male_lv2@test.tw', 'gender' => 'male', 'membership_level' => 2, 'credit_score' => 78, 'nickname' => '測試男二級'],
            ['email' => 'male_lv3@test.tw', 'gender' => 'male', 'membership_level' => 3, 'credit_score' => 88, 'nickname' => '測試男付費'],
            ['email' => 'suspended@test.tw', 'gender' => 'female', 'membership_level' => 1, 'credit_score' => 0, 'nickname' => '停權用戶', 'status' => 'auto_suspended', 'suspended_at' => now()->subDays(3)],
            ['email' => 'low_score@test.tw', 'gender' => 'male', 'membership_level' => 1, 'credit_score' => 25, 'nickname' => '低分用戶'],
        ];

        foreach ($testAccounts as $acct) {
            User::firstOrCreate(['email' => $acct['email']], array_merge($acct, [
                'password' => $password,
                'birth_date' => fake()->dateTimeBetween('-35 years', '-20 years')->format('Y-m-d'),
                'email_verified' => true,
                'status' => $acct['status'] ?? 'active',
                'suspended_at' => $acct['suspended_at'] ?? null,
                'avatar_url' => 'https://i.pravatar.cc/300?img=' . rand(1, 70),
                'last_active_at' => now()->subMinutes(rand(5, 1440)),
                'created_at' => now()->subDays(rand(10, 50)),
            ]));
        }

        // Female nicknames
        $femaleNames = ['小雨', '曉晴', '欣妤', '雅婷', '佳蓉', '品萱', '怡君', '子涵', '柔安', '詩涵', '依依', '心妍'];
        $maleNames = ['阿哲', '建豪', '志遠', '冠廷', '宸瑋', '品睿', '育霖'];
        $locations = ['台北市', '新北市', '桃園市', '台中市', '高雄市', '新竹市', '台南市'];
        $femaleJobs = ['護理師', '設計師', '老師', '行政', '業務', '工程師', '自由業'];
        $maleJobs = ['工程師', '業務', '醫師', '創業者', '設計師', '金融業', '教師'];
        $bios = [
            '喜歡旅遊和美食，希望認識有趣的人', '平時喜歡看書、聽音樂，個性隨和好相處',
            '熱愛生活，喜歡嘗試新事物', '工作之餘喜歡健身和烹飪',
            '喜歡電影和咖啡，希望找到有共同話題的朋友', '認真工作，努力生活',
            '喜歡戶外活動，個性開朗', '創業中，熱愛挑戰',
        ];
        $interestPool = ['旅遊', '美食', '健身', '電影', '音樂', '閱讀', '攝影', '烹飪', '瑜珈', '咖啡', '投資', '電玩'];

        // 12 female users (various levels)
        foreach ($femaleNames as $i => $name) {
            $level = $i < 5 ? 1 : ($i < 8 ? 2 : 3);
            User::firstOrCreate(['nickname' => $name], [
                'email' => "female{$i}@mimeet.test",
                'password' => $password,
                'nickname' => $name,
                'gender' => 'female',
                'birth_date' => fake()->dateTimeBetween('-32 years', '-20 years')->format('Y-m-d'),
                'membership_level' => $level,
                'credit_score' => rand(60, 95),
                'status' => 'active',
                'email_verified' => true,
                'avatar_url' => 'https://i.pravatar.cc/300?img=' . ($i + 1),
                'bio' => $bios[array_rand($bios)],
                'height' => rand(155, 170),
                'location' => $locations[array_rand($locations)],
                'occupation' => $femaleJobs[array_rand($femaleJobs)],
                'education' => fake()->randomElement(['bachelor', 'master', 'high_school']),
                'interests' => fake()->randomElements($interestPool, rand(2, 4)),
                'last_active_at' => now()->subMinutes(rand(5, 2880)),
                'created_at' => now()->subDays(rand(15, 55)),
            ]);
        }

        // 7 male users
        foreach ($maleNames as $i => $name) {
            $level = $i < 3 ? 0 : ($i < 5 ? 2 : 3);
            User::firstOrCreate(['nickname' => $name], [
                'email' => "male{$i}@mimeet.test",
                'password' => $password,
                'nickname' => $name,
                'gender' => 'male',
                'birth_date' => fake()->dateTimeBetween('-38 years', '-22 years')->format('Y-m-d'),
                'membership_level' => $level,
                'credit_score' => rand(50, 88),
                'status' => 'active',
                'email_verified' => true,
                'avatar_url' => 'https://i.pravatar.cc/300?img=' . ($i + 30),
                'bio' => $bios[array_rand($bios)],
                'height' => rand(170, 185),
                'location' => $locations[array_rand($locations)],
                'occupation' => $maleJobs[array_rand($maleJobs)],
                'education' => fake()->randomElement(['bachelor', 'master', 'phd']),
                'interests' => fake()->randomElements($interestPool, rand(2, 4)),
                'last_active_at' => now()->subMinutes(rand(10, 4320)),
                'created_at' => now()->subDays(rand(10, 50)),
            ]);
        }

        $this->command->info('Created ' . User::count() . ' users (including admin)');
    }
}
