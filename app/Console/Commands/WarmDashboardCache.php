<?php

namespace App\Console\Commands;

use App\Jobs\WarmDashboardCacheJob;
use App\Models\User;
use Illuminate\Console\Command;

class WarmDashboardCache extends Command
{
    protected $signature = 'dashboard:warm-cache {--user_id=} {--org_id=}';

    protected $description = 'Queue dashboard cache warming jobs for active users';

    public function handle(): int
    {
        $query = User::query()->where('is_active', true);

        if ($this->option('user_id')) {
            $query->where('id', (int) $this->option('user_id'));
        }

        if ($this->option('org_id')) {
            $query->where('org_id', (int) $this->option('org_id'));
        }

        $users = $query->get(['id']);

        foreach ($users as $user) {
            WarmDashboardCacheJob::dispatch($user->id);
        }

        $this->info("Queued dashboard cache warm jobs: {$users->count()}");

        return self::SUCCESS;
    }
}
