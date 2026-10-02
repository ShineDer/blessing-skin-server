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
        $user->notifications()->whereKey($id)->delete();
    }

    public function bulkDelete($user, array $ids): int
    {
        return $user->notifications()->whereIn('id', $ids)->delete();
    }
}
