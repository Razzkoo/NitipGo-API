<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ─── SHARED HELPERS ──────────────────────────────────────────────────────

    /**
     * Buat base query berdasarkan role user yang request.
     * - admin / customer  → filter by user_id
     * - traveler          → filter by traveler_id
     */
    private function baseQuery(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? 'customer'; // pastikan model User punya kolom role

        if ($role === 'traveler') {
            return Notification::forTraveler($user->id)->latest();
        }

        return Notification::forUser($user->id)->latest();
    }

    // ─── GET /notifications ───────────────────────────────────────────────────
    /**
     * List notifikasi dengan pagination.
     * Query param: ?type=order&unread=1
     */
    public function index(Request $request)
    {
        $query = $this->baseQuery($request);

        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        if ($request->boolean('unread')) {
            $query->unread();
        }

        $notifications = $query->paginate(20);
        $unreadCount   = (clone $this->baseQuery($request))->unread()->count();

        $notifications->getCollection()->transform(fn($n) => $n->toArray());

        return response()->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    // ─── GET /notifications/unread-count ─────────────────────────────────────
    /**
     * Hanya mengembalikan jumlah notifikasi belum dibaca.
     * Dipakai untuk badge di navbar.
     */
    public function unreadCount(Request $request)
    {
        $count = $this->baseQuery($request)->unread()->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    // ─── PATCH /notifications/{id}/read ──────────────────────────────────────
    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, int $id)
    {
        $notif = $this->baseQuery($request)->findOrFail($id);
        $notif->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data'    => $notif->toArray(),
        ]);
    }

    // ─── PATCH /notifications/read-all ───────────────────────────────────────
    /**
     * Tandai semua notifikasi sebagai sudah dibaca.
     */
    public function markAllRead(Request $request)
    {
        $updated = $this->baseQuery($request)
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notifikasi ditandai sudah dibaca.",
            'updated' => $updated,
        ]);
    }

    // ─── DELETE /notifications/{id} ──────────────────────────────────────────
    /**
     * Hapus satu notifikasi milik user yang request.
     */
    public function destroy(Request $request, int $id)
    {
        $notif = $this->baseQuery($request)->findOrFail($id);
        $notif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi dihapus.',
        ]);
    }

    // ─── DELETE /notifications ────────────────────────────────────────────────
    /**
     * Hapus semua notifikasi milik user yang request.
     * Query param: ?read_only=1 → hanya hapus yang sudah dibaca
     */
    public function destroyAll(Request $request)
    {
        $query = $this->baseQuery($request);

        if ($request->boolean('read_only')) {
            $query->read();
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} notifikasi dihapus.",
            'deleted' => $deleted,
        ]);
    }

    // ─── ADMIN: POST /admin/notifications/send ────────────────────────────────
    /**
     * Admin kirim notifikasi manual ke user / traveler / broadcast.
     */
    public function adminSend(Request $request)
    {
        $request->validate([
            'target'      => 'required|in:user,traveler,all_admins,all_customers,all_travelers',
            'target_id'   => 'required_if:target,user,traveler|nullable|integer',
            'type'        => 'required|string|max:50',
            'title'       => 'required|string|max:255',
            'message'     => 'required|string|max:1000',
            'action_url'  => 'nullable|string|max:500',
            'action_label'=> 'nullable|string|max:100',
        ]);

        $opts = [
            'action_url'   => $request->action_url,
            'action_label' => $request->action_label,
        ];

        switch ($request->target) {
            case 'user':
                $notif = Notification::sendToUser(
                    $request->target_id, 'customer',
                    $request->type, $request->title, $request->message, $opts
                );
                $sent = 1;
                break;

            case 'traveler':
                $notif = Notification::sendToTraveler(
                    $request->target_id,
                    $request->type, $request->title, $request->message, $opts
                );
                $sent = 1;
                break;

            case 'all_admins':
                Notification::sendToAllAdmins(
                    $request->type, $request->title, $request->message, $opts
                );
                $sent = \App\Models\User::where('role', 'admin')->count();
                break;

            case 'all_customers':
                $customers = \App\Models\User::where('role', 'customer')->pluck('id');
                foreach ($customers as $uid) {
                    Notification::sendToUser($uid, 'customer', $request->type, $request->title, $request->message, $opts);
                }
                $sent = $customers->count();
                break;

            case 'all_travelers':
                $travelers = \App\Models\Traveler::pluck('id');
                foreach ($travelers as $tid) {
                    Notification::sendToTraveler($tid, $request->type, $request->title, $request->message, $opts);
                }
                $sent = $travelers->count();
                break;
        }

        return response()->json([
            'success' => true,
            'message' => "Notifikasi terkirim ke {$sent} penerima.",
            'sent'    => $sent,
        ], 201);
    }
}