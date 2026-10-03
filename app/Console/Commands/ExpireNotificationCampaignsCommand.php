<?php

namespace App\Console\Commands;

use App\Models\NotificationCampaign;
use Illuminate\Console\Command;

class ExpireNotificationCampaignsCommand extends Command
{
    protected $signature = 'notifications:expire';
    protected $description = 'Expire notification campaign public periods';
    public function handle(): int
    {
        NotificationCampaign::where('status', 'published')->where('publicity_enabled', true)->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['status' => 'expired']);
        return self::SUCCESS;
    }
}
