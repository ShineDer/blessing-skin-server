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
            'audience' => $data['receiver'], 'audience_snapshot' => $users->pluck('uid')->values()->all(),
            'popup_enabled' => (bool) ($data['popup_enabled'] ?? false),
            'published_at' => now(), 'expires_at' => !empty($data['public_days']) ? now()->addDays((int) $data['public_days']) : null,
            'status' => 'published',
        ]);
        $run = $campaign->runs()->create(['run_number' => 1, 'mode' => 'initial', 'audience_snapshot' => $campaign->audience_snapshot, 'created_by' => $sender?->uid, 'started_at' => now(), 'status' => 'running']);
        $failed = [];
        foreach ($users as $user) {
            $delivery = NotificationDelivery::firstOrCreate(['campaign_run_id' => $run->id, 'user_id' => $user->uid], ['eligible' => true]);
            try {
                $notification = new SiteMessage($campaign->title, $campaign->content, ['campaign_id' => $campaign->id, 'campaign_run_id' => $run->id, 'delivery_id' => $delivery->id, 'popup_enabled' => $campaign->popup_enabled, 'content_html' => $campaign->content_html]);
                Notification::send($user, $notification);
                $delivery->update(['delivered' => true, 'notification_id' => DB::table('notifications')->where('notifiable_id', $user->uid)->latest('created_at')->value('id')]);
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
        DB::transaction(function () use ($campaign) { $campaign->update(['status' => 'revoked', 'revoked_at' => now()]); $campaign->runs()->each(fn ($run) => $run->deliveries()->update(['visible' => false])); });
    }

    public function reopen(NotificationCampaign $campaign, array $userIds = []): NotificationCampaignRun
    {
        $run = $campaign->runs()->create(['run_number' => $campaign->runs()->max('run_number') + 1, 'mode' => 'reopen', 'audience_snapshot' => $userIds ?: $campaign->audience_snapshot, 'created_by' => auth()->id(), 'started_at' => now(), 'status' => 'completed']);
        foreach ($userIds as $id) NotificationDelivery::firstOrCreate(['campaign_run_id' => $run->id, 'user_id' => $id], ['eligible' => true]);
        $campaign->update(['status' => 'published', 'revoked_at' => null]); return $run;
    }
}
