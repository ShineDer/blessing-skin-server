<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\NotificationCampaign;
use App\Notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Services\NotificationHistoryService;
use App\Services\NotificationCampaignService;
use App\Services\NotificationMarkdownService;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class NotificationsController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'receiver' => 'required|in:all,normal,verified,uid,email',
            'popup_enabled' => 'boolean',
            'public_days' => 'integer|min:0|max:365',
            'uid' => 'required_if:receiver,uid|nullable|integer|exists:users',
            'email' => 'required_if:receiver,email|nullable|email|exists:users',
            'title' => 'required|max:20',
            'content' => 'string|nullable',
        ]);

        $campaignResult = app(NotificationCampaignService::class)->send($data, auth()->user());
        session(['sentResult' => trans('admin.notifications.send.success')]);
        return redirect('/admin');

        $notification = new Notifications\SiteMessage($data['title'], $data['content']);

        switch ($data['receiver']) {
            case 'all':
                $users = User::all();
                break;
            case 'normal':
                $users = User::where('permission', User::NORMAL)->get();
                break;
            case 'uid':
                $users = User::where('uid', $data['uid'])->get();
                break;
            case 'email':
                $users = User::where('email', $data['email'])->get();
                break;
        }
        Notification::send($users, $notification);

        session(['sentResult' => trans('admin.notifications.send.success')]);

        return redirect('/admin');
    }

    public function campaigns()
    {
        return NotificationCampaign::withCount(['runs'])->latest()->paginate(20);
    }

    public function revokeCampaign($id, NotificationCampaignService $service)
    {
        $service->revoke(\App\Models\NotificationCampaign::findOrFail($id));
        return response()->noContent();
    }

    public function reopenCampaign($id, Request $request, NotificationCampaignService $service)
    {
        $data = $request->validate(['user_ids' => 'array', 'user_ids.*' => 'integer']);
        $service->reopen(NotificationCampaign::findOrFail($id), $data['user_ids'] ?? []);
        return response()->noContent();
    }

    public function updatePublicity($id, Request $request, NotificationCampaignService $service)
    {
        $data = $request->validate(['days' => 'required|integer|min:0|max:365']);
        $service->updatePublicity(NotificationCampaign::findOrFail($id), (int) $data['days']);
        return response()->noContent();
    }

    public function endPublicity($id, NotificationCampaignService $service)
    {
        $service->endPublicity(NotificationCampaign::findOrFail($id));
        return response()->noContent();
    }

    public function all()
    {
        return auth()->user()->unreadNotifications->map(fn ($notification) => [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? '',
        ]);
    }

    public function page()
    {
        return view('user.notifications');
    }

    public function history(Request $request, NotificationHistoryService $history)
    {
        return response()->json($history->paginate(auth()->user(), (int) $request->query('page', 1)));
    }

    public function markRead($id, NotificationHistoryService $history)
    {
        $n = $history->read(auth()->user(), $id);
        return ['id' => $n->id, 'title' => $n->data['title'] ?? '', 'content' => app(NotificationMarkdownService::class)->render($n->data['content'] ?? ''), 'time' => $n->created_at->toDateTimeString()];
    }

    public function markUnread($id, NotificationHistoryService $history) { $history->markUnread(auth()->user(), $id); return response()->noContent(); }
    public function readAll(NotificationHistoryService $history) { $history->readAll(auth()->user()); return response()->noContent(); }
    public function delete($id, NotificationHistoryService $history) { $history->delete(auth()->user(), $id); return response()->noContent(); }
    public function bulkDelete(Request $request, NotificationHistoryService $history) { $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'string']); $history->bulkDelete(auth()->user(), $data['ids']); return response()->noContent(); }
    public function retention(Request $request, NotificationHistoryService $history)
    {
        $data = $request->validate(['days' => 'required|integer|in:0,30,90,180,365,730']);
        $history->setRetention(auth()->user(), (int) $data['days']);
        return ['days' => (int) $data['days']];
    }

    public function read($id)
    {
        $notification = auth()->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return [
            'title' => $notification->data['title'] ?? '',
            'content' => app(NotificationMarkdownService::class)->render($notification->data['content'] ?? ''),
            'time' => $notification->created_at->toDateTimeString(),
        ];
    }
}
