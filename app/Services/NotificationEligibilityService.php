<?php

namespace App\Services;

use App\Models\NotificationCampaign;
use App\Models\NotificationDelivery;
use App\Models\NotificationCampaignRun;
use App\Notifications\SiteMessage;
use Illuminate\Support\Facades\Notification;

class NotificationEligibilityService
{
    public function deliverFor($user): int
    {
        $count = 0;
        $campaigns = NotificationCampaign::where('status', 'published')->where('popup_enabled', true)->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })->get();
        foreach ($campaigns as $campaign) {
            $run = $campaign->runs()->latest('id')->first();
            if (!$run || !$this->eligible($campaign, $run, $user)) continue;
            $delivery = NotificationDelivery::firstOrCreate(['campaign_run_id' => $run->id, 'user_id' => $user->uid], ['eligible' => true]);
            if (!$delivery->delivered) {
                $notification = new SiteMessage($campaign->title, $campaign->content, [
                    'campaign_id' => $campaign->id,
                    'campaign_run_id' => $run->id,
                    'delivery_id' => $delivery->id,
                    'popup_enabled' => true,
                    'content_html' => $campaign->content_html,
                ]);
                Notification::send($user, $notification);
                $delivery->update([
                    'delivered' => true,
                    'notification_id' => $user->notifications()->latest('created_at')->value('id'),
                ]);
                $count++;
            }
        }
        return $count;
    }

    private function eligible(NotificationCampaign $campaign, NotificationCampaignRun $run, $user): bool
    {
        if (in_array($campaign->audience, ['uid', 'email'], true)) {
            return in_array(
                $campaign->audience === 'uid' ? $user->uid : $user->email,
                $run->audience_snapshot ?? [],
                true
            );
        }

        if ($campaign->expires_at === null && $run->mode !== 'reopen') {
            return false;
        }

        return match ($campaign->audience) {
            'all' => true,
            'normal' => $user->permission === \App\Models\User::NORMAL,
            'verified' => (bool) $user->verified,
            default => false,
        };
    }
}
