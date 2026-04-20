<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TestUsersSeeder — F27 升級版
 *
 * 核心變更（2026-04-20）：
 *  - 全部使用 updateOrCreate（以 email 為 key），冪等可重複執行
 *  - 50 人分 6 群組：高端男女 / 普通男女 / 低活躍男女
 *  - 差異化 fill_rate：高端 90%、普通 50-60%、低活躍 10-15%
 *  - 新增 9 個 F27 profile 欄位：style/dating_budget/dating_frequency/
 *    dating_type/relationship_goal/smoking/drinking/car_owner/availability
 *  - 地區加權分布（台北 40%、新北 15%、桃園 10%、台中 15%、高雄 10%、台南 10%）
 *  - email 統一 @mimeet.test 域名
 *  - 密碼統一 Test1234 方便測試登入
 *
 * 另保留 8 個固定身份帳號（Lv1/Lv2/Lv3/受限/停權）作為 dev check 快速切換用。
 */
class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Test1234');

        // ── 1. 固定身份測試帳號（8 人）──────────────────────────
        $this->seedFixedAccounts($password);

        // ── 2. 50 人分群組測試資料 ────────────────────────────
        $groups = [
            ['prefix' => 'elite_m', 'count' => 8, 'gender' => 'male',
             'levels' => [2, 3], 'credit' => [80, 100], 'fill' => 0.90,
             'budget' => ['generous', 'luxury'],
             'goal' => ['long_term', 'short_term'],
             'style' => ['intellectual', 'sporty'],
             'car' => true],

            ['prefix' => 'reg_m', 'count' => 10, 'gender' => 'male',
             'levels' => [1, 2], 'credit' => [50, 80], 'fill' => 0.60,
             'budget' => ['casual', 'moderate'],
             'goal' => ['short_term', 'open', 'undisclosed'],
             'style' => ['fresh', 'sporty', 'intellectual'],
             'car' => null],

            ['prefix' => 'low_m', 'count' => 7, 'gender' => 'male',
             'levels' => [0, 1], 'credit' => [30, 60], 'fill' => 0.15,
             'budget' => [null, 'undisclosed'],
             'goal' => [null],
             'style' => [null],
             'car' => null],

            ['prefix' => 'elite_f', 'count' => 8, 'gender' => 'female',
             'levels' => [1.5, 3], 'credit' => [80, 100], 'fill' => 0.90,
             'budget' => ['generous', 'luxury'],
             'goal' => ['long_term'],
             'style' => ['sweet', 'sexy', 'intellectual'],
             'car' => null],

            ['prefix' => 'reg_f', 'count' => 10, 'gender' => 'female',
             'levels' => [1, 1.5], 'credit' => [50, 80], 'fill' => 0.50,
             'budget' => ['moderate', 'casual'],
             'goal' => ['long_term', 'open'],
             'style' => ['fresh', 'sweet', 'sporty'],
             'car' => null],

            ['prefix' => 'low_f', 'count' => 7, 'gender' => 'female',
             'levels' => [0, 1], 'credit' => [30, 60], 'fill' => 0.10,
             'budget' => [null],
             'goal' => [null],
             'style' => [null],
             'car' => null],
        ];

        $created = 0;
        foreach ($groups as $group) {
            for ($i = 1; $i <= $group['count']; $i++) {
                $email = sprintf('test_%s_%03d@mimeet.test', $group['prefix'], $i);
                $data = $this->buildUserData($group, $i, $password);
                User::updateOrCreate(['email' => $email], array_merge($data, ['email' => $email]));
                $created++;
            }
        }

        $total = User::where('email', 'like', 'test_%@mimeet.test')->count();
        $this->command->info("  ✓ TestUsersSeeder: {$created} records upserted, table has {$total} test_*@mimeet.test users");
    }

    private function seedFixedAccounts(string $password): void
    {
        $fixed = [
            ['email' => 'female_lv1@test.tw',  'gender' => 'female', 'membership_level' => 1,   'credit_score' => 75, 'nickname' => '測試女一級'],
            ['email' => 'female_lv2@test.tw',  'gender' => 'female', 'membership_level' => 2,   'credit_score' => 85, 'nickname' => '測試女二級'],
            ['email' => 'female_lv3@test.tw',  'gender' => 'female', 'membership_level' => 3,   'credit_score' => 90, 'nickname' => '測試女付費'],
            ['email' => 'male_lv0@test.tw',    'gender' => 'male',   'membership_level' => 0,   'credit_score' => 60, 'nickname' => '測試男零級'],
            ['email' => 'male_lv2@test.tw',    'gender' => 'male',   'membership_level' => 2,   'credit_score' => 78, 'nickname' => '測試男二級'],
            ['email' => 'male_lv3@test.tw',    'gender' => 'male',   'membership_level' => 3,   'credit_score' => 88, 'nickname' => '測試男付費'],
            ['email' => 'suspended@test.tw',   'gender' => 'female', 'membership_level' => 1,   'credit_score' => 0,  'nickname' => '停權用戶',  'status' => 'auto_suspended', 'suspended_at' => now()->subDays(3)],
            ['email' => 'low_score@test.tw',   'gender' => 'male',   'membership_level' => 1,   'credit_score' => 25, 'nickname' => '低分用戶'],
        ];

        foreach ($fixed as $acct) {
            User::updateOrCreate(
                ['email' => $acct['email']],
                array_merge($acct, [
                    'password' => $password,
                    'birth_date' => now()->subYears(rand(20, 35))->subDays(rand(0, 365))->format('Y-m-d'),
                    'email_verified' => true,
                    'status' => $acct['status'] ?? 'active',
                    'suspended_at' => $acct['suspended_at'] ?? null,
                    'avatar_url' => 'https://i.pravatar.cc/300?img=' . rand(1, 70),
                    'last_active_at' => now()->subMinutes(rand(5, 1440)),
                ]),
            );
        }
    }

    private function buildUserData(array $group, int $seq, string $password): array
    {
        $shouldFill = fn () => (mt_rand(1, 100) / 100) <= $group['fill'];
        $pick = fn (array $arr) => $arr[array_rand($arr)];
        $level = is_array($group['levels']) ? $pick($group['levels']) : $group['levels'];

        // 年齡分布：20-25 歲 30%、26-30 歲 35%、31-40 歲 25%、41+ 10%
        $r = mt_rand(1, 100);
        if ($r <= 30)       $birthYears = [20, 25];
        elseif ($r <= 65)   $birthYears = [26, 30];
        elseif ($r <= 90)   $birthYears = [31, 40];
        else                $birthYears = [41, 50];
        $birthDate = now()->subYears(rand($birthYears[0], $birthYears[1]))->subDays(rand(0, 365))->format('Y-m-d');

        // 地區加權分布（只在 shouldFill 時套用）
        $weightedCities = [
            '台北市' => 40, '新北市' => 15, '桃園市' => 10,
            '台中市' => 15, '高雄市' => 10, '台南市' => 10,
        ];
        $location = $shouldFill() ? $this->weightedPick($weightedCities) : null;

        // 暱稱池
        $femaleNames = ['甜心寶貝', '小雨', '曉晴', '欣妤', '雅婷', '佳蓉', '品萱', '怡君', '子涵', '柔安', '詩涵', '依依', '心妍', '若薇', '曉彤', '靜宜'];
        $maleNames = ['陽光男孩', '阿哲', '建豪', '志遠', '冠廷', '宸瑋', '品睿', '育霖', '宗翰', '聖傑', '彥廷', '俊彥', '文豪'];
        $nickname = $pick($group['gender'] === 'female' ? $femaleNames : $maleNames) . sprintf('%03d', $seq);

        // 職業池
        $femaleJobs = ['護理師', '設計師', '老師', '行政', '業務', '工程師', '自由業', '美妝師', '空服員'];
        $maleJobs = ['工程師', '業務', '醫師', '創業者', '設計師', '金融業', '教師', '律師', '主管'];

        // bio 池
        $bios = [
            '喜歡旅遊和美食，希望認識有趣的人',
            '平時喜歡看書、聽音樂，個性隨和好相處',
            '熱愛生活，喜歡嘗試新事物',
            '工作之餘喜歡健身和烹飪',
            '喜歡電影和咖啡，希望找到有共同話題的朋友',
            '認真工作，努力生活',
            '喜歡戶外活動，個性開朗',
            '創業中，熱愛挑戰',
            '週末喜歡爬山和攝影',
            '專業工作者，期待認識聊得來的對象',
        ];

        // 輔助：選填欄位（根據 fill_rate 決定是否填入）
        $maybe = fn (callable $fn) => $shouldFill() ? $fn() : null;

        // style：有些群組的 style 池是 [null] → 直接回 null
        $styleFromGroup = null;
        if (!empty($group['style'])) {
            $nonNullStyles = array_values(array_filter($group['style'], fn ($v) => $v !== null));
            if (count($nonNullStyles) > 0) {
                $styleFromGroup = $maybe(fn () => $pick($nonNullStyles));
            }
        }

        return [
            'password' => $password,
            'nickname' => $nickname,
            'gender' => $group['gender'],
            'birth_date' => $birthDate,
            'location' => $location,
            'height' => $maybe(fn () => rand(155, 185)),
            'weight' => $maybe(fn () => rand(45, 85)),
            'occupation' => $maybe(fn () => $pick($group['gender'] === 'female' ? $femaleJobs : $maleJobs)),
            'education' => $maybe(fn () => $pick(['high_school', 'associate', 'bachelor', 'master', 'phd'])),
            'bio' => $maybe(fn () => $pick($bios)),

            // F27 profile fields
            'style' => $styleFromGroup,
            'dating_budget' => $pick($group['budget']),  // 群組可能含 null
            'dating_frequency' => $maybe(fn () => $pick(['occasional', 'weekly', 'flexible'])),
            'dating_type' => $maybe(fn () => $this->randomSubset(['dining', 'travel', 'companion', 'mentorship'], rand(1, 3))),
            'relationship_goal' => $pick($group['goal']),
            'smoking' => $maybe(fn () => $pick(['never', 'sometimes', 'often'])),
            'drinking' => $maybe(fn () => $pick(['never', 'social', 'often'])),
            'car_owner' => $group['car'] ?? ($shouldFill() ? (mt_rand(1, 100) <= 40) : null),
            'availability' => $maybe(fn () => $this->randomSubset(['weekday_day', 'weekday_night', 'weekend', 'flexible'], rand(1, 3))),

            // 會員屬性
            'membership_level' => $level,
            'credit_score' => rand($group['credit'][0], $group['credit'][1]),
            'email_verified' => true,
            'status' => 'active',
            'avatar_url' => 'https://i.pravatar.cc/300?img=' . mt_rand(1, 70),
            'last_active_at' => now()->subMinutes(rand(5, 10080)), // 5 分鐘 ~ 7 天
        ];
    }

    /**
     * 從加權表 ['name' => weight] 中隨機選一個 key。
     */
    private function weightedPick(array $weighted): string
    {
        $total = array_sum($weighted);
        $pick = mt_rand(1, $total);
        $cum = 0;
        foreach ($weighted as $key => $w) {
            $cum += $w;
            if ($pick <= $cum) return (string) $key;
        }
        return (string) array_key_first($weighted);
    }

    /**
     * 從陣列中隨機挑 n 個不重複項目。
     */
    private function randomSubset(array $arr, int $n): array
    {
        shuffle($arr);
        return array_slice($arr, 0, min($n, count($arr)));
    }
}
