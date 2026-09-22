<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(in_array('audit.view', $request->user()?->abilities() ?? [], true), 403);

        $input = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $user->loadMissing('shop');

        $query = AuditLog::query()
            ->with('user:id,name')
            ->where('shop_id', $user->shop_id)
            ->latest('created_at');

        if (! empty($input['action'])) {
            $query->where('action', $input['action']);
        }

        if (! empty($input['date'])) {
            $day = Carbon::parse($input['date'], $user->shop->timezone);
            $query->whereBetween('created_at', [
                $day->copy()->startOfDay()->utc(),
                $day->copy()->endOfDay()->utc(),
            ]);
        }

        if (! empty($input['q'])) {
            $term = $input['q'];
            $query->where(function ($inner) use ($term) {
                $inner->where('entity_id', $term)
                    ->orWhere('action', 'like', '%'.$term.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$term.'%'));
            });
        }

        $logs = $query->limit(100)->get()->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'user' => $log->user?->name,
            'before' => $log->before,
            'after' => $log->after,
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return response()->json(['data' => $logs]);
    }
}
