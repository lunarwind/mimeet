<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * User must be the reporter to view a report.
     */
    public function view(User $user, Report $report): bool
    {
        return $report->reporter_id === $user->id;
    }

    /**
     * User must be the reporter to cancel/delete a report.
     */
    public function delete(User $user, Report $report): bool
    {
        return $report->reporter_id === $user->id;
    }
}
