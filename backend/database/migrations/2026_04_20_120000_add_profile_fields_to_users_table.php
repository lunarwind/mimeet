<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 外貌風格
            $table->string('style', 20)->nullable()->after('education')
                ->comment('自我風格（gender-strict）：男 business_elite/british_gentleman/smart_casual/outdoor/boy_next_door/minimalist/japanese/warm_guy/preppy；女 fresh/sweet/sexy/intellectual/sporty/elegant/korean/pure_student/petite_japanese');

            // 約會偏好
            $table->string('dating_budget', 20)->nullable()->after('style')
                ->comment('約會預算：casual/moderate/generous/luxury/undisclosed');
            $table->string('dating_frequency', 20)->nullable()->after('dating_budget')
                ->comment('見面頻率：occasional/weekly/flexible');
            $table->json('dating_type')->nullable()->after('dating_frequency')
                ->comment('約會類型（複選）：dining/travel/companion/mentorship/undisclosed');
            $table->string('relationship_goal', 20)->nullable()->after('dating_type')
                ->comment('關係期望：short_term/long_term/open/undisclosed');

            // 生活資訊
            $table->string('smoking', 20)->nullable()->after('relationship_goal')
                ->comment('抽菸：never/sometimes/often');
            $table->string('drinking', 20)->nullable()->after('smoking')
                ->comment('飲酒：never/social/often');
            $table->boolean('car_owner')->nullable()->after('drinking')
                ->comment('有無自備車');
            $table->json('availability')->nullable()->after('car_owner')
                ->comment('可約時段（複選）：weekday_day/weekday_night/weekend/flexible');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'style',
                'dating_budget',
                'dating_frequency',
                'dating_type',
                'relationship_goal',
                'smoking',
                'drinking',
                'car_owner',
                'availability',
            ]);
        });
    }
};
