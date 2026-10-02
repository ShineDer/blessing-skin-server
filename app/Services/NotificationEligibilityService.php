<?php

namespace App\Services;

use App\Models\NotificationCampaign;
use App\Models\NotificationDelivery;
use App\Models\NotificationCampaignRun;

class NotificationEligibilityService
{
    public function deliverFor($user): int
    {
        $count = 0;
        $campaigns = NotificationCampaign::where('status', 'published')->where('popup_enabled', true)->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })->get();
        foreach ($campaigns as $campaign) {
            $run = $campaign->runs()->oldest('id')->first();
            if (!$run || !in_array($user->uid, $run->audience_snapshot ?? [], true)) continue;
            $delivery = NotificationDelivery::firstOrCreate(['campaign_run_id' => $run->id, 'user_id' => $user->uid], ['eligible' => true]);
            if (!$delivery->delivered) { $delivery->update(['delivered' => true]); $count++; }
        }
        return $count;
    }
}
