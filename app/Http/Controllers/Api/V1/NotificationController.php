<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $audience = $this->audience($request);
        $items = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('audience', $audience)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map->toApi()
            ->values();

        return response()->json([
            'data' => $items,
            'unreadCount' => $this->countUnread($request->user()->id, $audience),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $audience = $this->audience($request);

        return response()->json([
            'data' => [
                'unreadCount' => $this->countUnread($request->user()->id, $audience),
            ],
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['data' => $notification->fresh()->toApi()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $audience = $this->audience($request);
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('audience', $audience)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['unreadCount' => 0],
        ]);
    }

    private function audience(Request $request): string
    {
        $requested = $request->query('audience', $request->input('audience'));
        if (in_array($requested, ['customer', 'staff'], true)) {
            return $requested;
        }

        return in_array($request->user()->role, Roles::staff(), true) ? 'staff' : 'customer';
    }

    private function countUnread(int $userId, string $audience): int
    {
        return AppNotification::query()
            ->where('user_id', $userId)
            ->where('audience', $audience)
            ->whereNull('read_at')
            ->count();
    }
}
