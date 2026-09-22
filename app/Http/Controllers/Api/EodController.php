<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\EodReport;
use App\Services\DayReport;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EodController extends Controller
{
    public function __construct(private readonly DayReport $days) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizeEod($request);

        $date = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ])['date'];

        return response()->json([
            'data' => $this->payload($request, $date),
        ]);
    }

    public function close(Request $request): JsonResponse
    {
        $this->authorizeEod($request);

        $input = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'closing_notes' => ['nullable', 'string', 'max:500'],
            'opening_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $user->loadMissing('shop');
        $existing = EodReport::query()
            ->where('report_date', $input['date'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'data' => $this->payload($request, $input['date'], alreadyClosed: true),
            ]);
        }

        try {
            DB::transaction(function () use ($user, $input): void {
                $locked = EodReport::query()
                    ->where('report_date', $input['date'])
                    ->lockForUpdate()
                    ->first();

                if ($locked !== null) {
                    return;
                }

                $live = $this->days->forShopDate($user->shop, $input['date']);

                $report = EodReport::query()->create([
                    'shop_id' => $user->shop_id,
                    'report_date' => $input['date'],
                    'closed_by' => $user->id,
                    'opening_notes' => $input['opening_notes'] ?? null,
                    'closing_notes' => $input['closing_notes'] ?? null,
                    'sales_count' => $live['sales_count'],
                    'revenue_minor' => $live['revenue_minor'],
                    'cogs_minor' => $live['cogs_minor'],
                    'gross_profit_minor' => $live['gross_profit_minor'],
                    'void_count' => $live['void_count'],
                    'stock_summary' => $live['stock'],
                ]);

                AuditLogger::record(
                    $user,
                    'eod.closed',
                    'eod_report',
                    $report->id,
                    after: [
                        'date' => $input['date'],
                        'sales_count' => $report->sales_count,
                        'revenue_minor' => $report->revenue_minor,
                        'gross_profit_minor' => $report->gross_profit_minor,
                    ],
                );
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }
        }

        return response()->json([
            'data' => $this->payload($request, $input['date']),
        ], 201);
    }

    private function authorizeEod(Request $request): void
    {
        abort_unless(in_array('reports.eod', $request->user()?->abilities() ?? [], true), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, string $date, bool $alreadyClosed = false): array
    {
        $user = $request->user();
        $user->loadMissing('shop');
        $live = $this->days->forShopDate($user->shop, $date);
        $closed = EodReport::query()->where('report_date', $date)->first();

        return [
            'date' => $date,
            'timezone' => $user->shop->timezone,
            'already_closed' => $alreadyClosed || $closed !== null,
            'closed' => $closed === null ? null : [
                'sales_count' => $closed->sales_count,
                'revenue_minor' => $closed->revenue_minor,
                'cogs_minor' => $closed->cogs_minor,
                'gross_profit_minor' => $closed->gross_profit_minor,
                'void_count' => $closed->void_count,
                'closing_notes' => $closed->closing_notes,
                'stock' => $closed->stock_summary,
                'closed_at' => $closed->created_at?->toISOString(),
            ],
            'live' => [
                'sales_count' => $live['sales_count'],
                'revenue_minor' => $live['revenue_minor'],
                'cogs_minor' => $live['cogs_minor'],
                'gross_profit_minor' => $live['gross_profit_minor'],
                'void_count' => $live['void_count'],
                'stock' => $live['stock'],
                'bills' => SaleResource::collection($live['sales'])->resolve(),
            ],
        ];
    }
}
