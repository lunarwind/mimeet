<?php

namespace Database\Seeders;

use App\Models\SeoMeta;
use Illuminate\Database\Seeder;

class SeoMetaSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'route'          => '/',
                'title'          => 'MiMeet - 台灣高端交友平台',
                'description'    => '誠信分數系統，讓每一次相遇都值得信賴',
                'og_title'       => 'MiMeet 交友平台',
                'og_description' => '安全、真實、高品質的交友體驗',
                'og_image_url'   => null,
            ],
            [
                'route'          => '/login',
                'title'          => '登入 - MiMeet',
                'description'    => '登入 MiMeet，開始你的交友旅程',
                'og_title'       => null,
                'og_description' => null,
                'og_image_url'   => null,
            ],
            [
                'route'          => '/register',
                'title'          => '註冊 - MiMeet',
                'description'    => '加入 MiMeet，認識優質對象',
                'og_title'       => null,
                'og_description' => null,
                'og_image_url'   => null,
            ],
        ];

        foreach ($defaults as $meta) {
            SeoMeta::updateOrCreate(
                ['route' => $meta['route']],
                $meta,
            );
        }
    }
}
