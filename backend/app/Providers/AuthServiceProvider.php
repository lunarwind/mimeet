<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\DateInvitation;
use App\Models\Report;
use App\Policies\ConversationPolicy;
use App\Policies\DateInvitationPolicy;
use App\Policies\ReportPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Conversation::class => ConversationPolicy::class,
        Report::class => ReportPolicy::class,
        DateInvitation::class => DateInvitationPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
