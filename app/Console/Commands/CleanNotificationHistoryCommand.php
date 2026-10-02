<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationHistoryService;
use Illuminate\Console\Command;

class CleanNotificationHistoryCommand extends Command
{
    protected $signature = 'notifications:cleanup';
    protected $description = 'Remove read notifications past user retention periods';
    public function handle(NotificationHistoryService $history): int
    {
        User::query()->where('notification_retention_days', '>', 0)->chunkById(100, function ($users) use ($history) { foreach ($users as $user) $history->cleanup($user); }, 'uid');
        return self::SUCCESS;
    }
}
