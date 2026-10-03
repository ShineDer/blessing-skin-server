<?php

namespace App\Services;

use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRun;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\SiteMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class NotificationCampaignService
{
    public function send(array $data, $sender = null): array
    {
        $users = $this->audience($data);
        $markdown = $data['content'] ?? '';
        $campaign = NotificationCampaign::create([
            'sender_id' => $sender?->uid,
            'title' => $data['title'], 'content' => $markdown,
            'content_html' => app(NotificationMarkdownService::class)->render($markdown),
            'audience' => $data['receiver'],
            'audience_snapshot' => $data['receiver'] === 'email'
                ? $users->pluck('email')->values()->all()
                : $users->pluck('uid')->values()->all(),
            'popup_enabled' => (bool) ($data['popup_enabled'] ?? false),
            'publicity_enabled' => !empty($data['popup_enabled']) && !empty($data['publicity_enabled']),
            'public_days' => !empty($data['popup_enabled']) && !empty($data['publicity_enabled'])
                ? (int) ($data['public_days'] ?? 0)
                : 0,
            'published_at' => now(),
            'expires_at' => !empty($data['popup_enabled']) && !empty($data['publicity_enabled']) && (int) ($data['public_days'] ?? 0) > 0
                ? now()->addDays((int) $data['public_days'])
                : null,
            'status' => 'published',
        ]);
        $run = $campaign->runs()->create(['run_number' => 1, 'mode' => 'initial', 'audience_snapshot' => $campaign->audience_snapshot, 'created_by' => $sender?->uid, 'started_at' => now(), 'status' => 'running']);
        $failed = [];
        foreach ($users as $user) {
            $delivery = NotificationDelivery::firstOrCreate(['campaign_run_id' => $run->id, 'user_id' => $user->uid], ['eligible' => true]);
            try {
                $notification = new SiteMessage($campaign->title, $campaign->content, ['campaign_id' => $campaign->id, 'campaign_run_id' => $run->id, 'delivery_id' => $delivery->id, 'popup_enabled' => $campaign->popup_enabled, 'content_html' => $campaign->content_html]);
                Notification::send($user, $notification);
                $notificationId = DB::table('notifications')
                    ->where('notifiable_id', $user->uid)
                    ->where('type', SiteMessage::class)
                    ->where('data', 'like', '%delivery_id%'.$delivery->id.'%')
                    ->latest('created_at')
                    ->value('id');
                if (!$notificationId) {
                    throw new \RuntimeException('Notification record was not created for delivery '.$delivery->id);
                }
                $delivery->update(['delivered' => true, 'notification_id' => $notificationId]);
            } catch (\Throwable $e) { $delivery->update(['failure_reason' => Str::limit($e->getMessage(), 1000)]); $failed[] = $user->uid; }
        }
        $run->update(['completed_at' => now(), 'status' => empty($failed) ? 'completed' : 'partial']);
        return ['campaign' => $campaign, 'run' => $run, 'failed' => $failed, 'delivered' => count($users) - count($failed)];
    }

    public function audience(array $data)
    {
        return match ($data['receiver']) {
            'all' => User::query()->get(),
            'normal' => User::where('permission', User::NORMAL)->get(),
            'verified' => User::where('verified', true)->get(),
            'uid' => User::where('uid', $data['uid'])->get(),
            'email' => User::where('email', $data['email'])->get(),
            default => collect(),
        };
    }

    public function revoke(NotificationCampaign $campaign): void
    {
        abort_unless($campaign->published_at && $campaign->published_at->gte(now()->subDay()), 422, 'Campaign can only be revoked within 24 hours.');
        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'revoked', 'revoked_at' => now()]);
            $campaign->runs()->with('deliveries')->each(function ($run) {
                $notificationIds = $run->deliveries->pluck('notification_id')->filter();
                if ($notificationIds->isNotEmpty()) {
                    DB::table('notifications')->whereIn('id', $notificationIds)->delete();
                }
                $run->deliveries()->update(['visible' => false, 'deleted_at' => now()]);
            });
        });
    }

    public function reopen(NotificationCampaign $campaign, array $userIds = []): NotificationCampaignRun
    {
        abort_if($campaign->status === 'revoked', 422, 'Revoked campaigns cannot be reopened.');
        abort_unless($campaign->popup_enabled && $campaign->publicity_enabled && $campaign->public_days > 0, 422, 'Only publicity campaigns can be reopened.');

        $run = $campaign->runs()->create([
            'run_number' => $campaign->runs()->max('run_number') + 1,
            'mode' => 'reopen',
            'audience_snapshot' => $userIds ?: $campaign->audience_snapshot,
            'created_by' => auth()->id(),
            'started_at' => now(),
            'status' => 'running',
        ]);
        $campaign->update([
            'status' => 'published',
            'revoked_at' => null,
            'published_at' => now(),
            'expires_at' => $campaign->publicity_enabled && $campaign->public_days > 0
                ? now()->addDays($campaign->public_days)
                : null,
        ]);

        $targets = $userIds ? User::whereIn('uid', $userIds)->get() : $this->audience([
            'receiver' => $campaign->audience,
            'uid' => null,
            'email' => null,
        ]);
        foreach ($targets as $user) {
            $delivery = NotificationDelivery::firstOrCreate([
                'campaign_run_id' => $run->id,
                'user_id' => $user->uid,
            ], ['eligible' => true]);
            if ($delivery->delivered) continue;
            try {
                $notification = new SiteMessage($campaign->title, $campaign->content, [
                    'campaign_id' => $campaign->id,
                    'campaign_run_id' => $run->id,
                    'delivery_id' => $delivery->id,
                    'popup_enabled' => $campaign->popup_enabled,
                    'content_html' => $campaign->content_html,
                ]);
                Notification::send($user, $notification);
                $notificationId = DB::table('notifications')
                    ->where('notifiable_id', $user->uid)
                    ->where('type', SiteMessage::class)
                    ->where('data', 'like', '%delivery_id%'.$delivery->id.'%')
                    ->latest('created_at')
                    ->value('id');
                if (!$notificationId) throw new \RuntimeException('Notification record was not created.');
                $delivery->update(['delivered' => true, 'notification_id' => $notificationId]);
            } catch (\Throwable $e) {
                $delivery->update(['failure_reason' => Str::limit($e->getMessage(), 1000)]);
            }
        }
        $run->update(['completed_at' => now(), 'status' => 'completed']);
        return $run;
    }

    public function updatePublicity(NotificationCampaign $campaign, int $days): void
    {
        abort_unless($days >= 1 && $days <= 365, 422, 'A publicity period must be between 1 and 365 days.');
        abort_if($campaign->status === 'revoked' || !$campaign->popup_enabled || !$campaign->publicity_enabled, 422, 'Campaign is not publicized.');

        $expires = $campaign->published_at->copy()->addDays($days);
        if ($expires->lte(now())) {
            $expires = now()->addDay();
            $days = max(1, (int) ceil($campaign->published_at->diffInSeconds($expires) / 86400));
        }
        $campaign->update(['public_days' => $days, 'expires_at' => $expires, 'status' => 'published']);
    }

    public function endPublicity(NotificationCampaign $campaign): void
    {
        abort_if(!$campaign->is_publicity_active, 422, 'Campaign is not publicized.');
        $campaign->update(['status' => 'expired', 'expires_at' => now()]);
    }
}
