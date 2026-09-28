<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

/** Spec F6: "notifications" — mirrors ⚡notification-bell.blade.php's own read/unread/clear actions (spec A7's Notification Center). */
class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $base = $request->user()->notifications()->whereNull('cleared_at');

        $paginated = (clone $base)->latest()->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->success(
            NotificationResource::collection($paginated->items()),
            [
                'page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'unread_count' => (clone $base)->whereNull('read_at')->count(),
            ]
        );
    }

    public function markRead(Request $request, int $notification)
    {
        $request->user()->notifications()->where('id', $notification)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->success(['marked_read' => true]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->whereNull('cleared_at')->update(['read_at' => now()]);

        return $this->success(['marked_read' => true]);
    }

    public function clear(Request $request, int $notification)
    {
        $request->user()->notifications()->where('id', $notification)->update(['cleared_at' => now()]);

        return $this->success(['cleared' => true]);
    }

    public function clearAll(Request $request)
    {
        $request->user()->notifications()->whereNull('cleared_at')->update(['cleared_at' => now()]);

        return $this->success(['cleared' => true]);
    }
}
