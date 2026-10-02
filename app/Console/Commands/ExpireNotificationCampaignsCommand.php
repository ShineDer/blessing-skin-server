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
        NotificationCampaign::where('status', 'published')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['status' => 'expired', 'popup_enabled' => false]);
        return self::SUCCESS;
    }
}
