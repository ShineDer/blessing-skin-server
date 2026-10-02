<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationHistoryService
{
    public function paginate($user, int $page = 1): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate(20, ['*'], 'page', $page);
    }

    public function read($user, string $id): DatabaseNotification
    {
        $n = $user->notifications()->whereKey($id)->firstOrFail();
        if (!$n->read_at) $n->markAsRead();
        return $n->refresh();
    }

    public function markUnread($user, string $id): void
    {
        $n = $user->notifications()->whereKey($id)->firstOrFail();
        $n->markAsUnread();
    }

    public function readAll($user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function delete($user, string $id): void
    {
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->delete();
        $this->syncDelivery($notification, true);
    }

    public function bulkDelete($user, array $ids): int
    {
        $notifications = $user->notifications()->whereIn('id', $ids)->get();
        foreach ($notifications as $notification) { $notification->delete(); $this->syncDelivery($notification, true); }
        return $notifications->count();
    }

    public function setRetention($user, int $days): void
    {
        $allowed = [0, 30, 90, 180, 365, 730];
        abort_unless(in_array($days, $allowed, true), 422, 'Invalid retention period.');
        $old = (int) ($user->notification_retention_days ?? 0);
        $user->forceFill(['notification_retention_days' => $days])->save();
        if ($days > 0 && ($old === 0 || $days < $old)) {
            $cutoff = now()->subDays($days);
            $notifications = $user->readNotifications()->where('read_at', '<=', $cutoff)->get();
            foreach ($notifications as $notification) { $notification->delete(); $this->syncDelivery($notification, true); }
        }
    }

    public function cleanup($user): int
    {
        $days = (int) ($user->notification_retention_days ?? 0);
        if (!$days) return 0;
        $notifications = $user->readNotifications()->where('read_at', '<=', now()->subDays($days))->get();
        foreach ($notifications as $notification) { $notification->delete(); $this->syncDelivery($notification, true); }
        return $notifications->count();
    }

    private function syncDelivery($notification, bool $deleted): void
    {
        $data = $notification->data ?? [];
        if (!empty($data['delivery_id'])) {
            \App\Models\NotificationDelivery::whereKey($data['delivery_id'])->update(['visible' => !$deleted, 'deleted_at' => $deleted ? now() : null]);
        }
    }
}
