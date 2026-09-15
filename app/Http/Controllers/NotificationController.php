<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

class NotificationController extends Controller
{
    /**
     * Kişisel bildirimler (favori turda fiyat düşüşü vb.) + genel duyurular
     * (yeni turlar). Duyurular kullanıcı başına satır yazılmadan tek kayıttan
     * okunur; sayfayı açmak duyuruları "görüldü" sayar (rozet sıfırlanır).
     */
    public function index()
    {
        $user = auth()->user();

        $notifications = $user->notifications()->paginate(20);

        $announcementSeenAt = $user->announcements_seen_at ?? $user->created_at;
        $announcements = Announcement::forUser($user) // A6: role göre
            ->with('tour')
            ->latest()
            ->take(15)
            ->get();

        $user->forceFill(['announcements_seen_at' => now()])->save();
        $user->forgetBadgeCache(); // A7

        return view('notifications.index', compact('notifications', 'announcements', 'announcementSeenAt'));
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        auth()->user()->forgetBadgeCache(); // A7

        return back();
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        $user->unreadNotifications->markAsRead();
        $user->forceFill(['announcements_seen_at' => now()])->save();
        $user->forgetBadgeCache(); // A7

        return back()->with('success', 'Tüm bildirimler okundu olarak işaretlendi.');
    }
}
