<?php

namespace App\Http\Controllers;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SecurityEventController extends Controller
{
    // How far back the per-address rollup looks. Long enough to show an
    // overnight run of attempts on a page you check in the morning.
    private const SUMMARY_HOURS = 12;

    // The trail is read by eye, so the recent list is capped rather than paged.
    private const RECENT_LIMIT = 50;

    /**
     * Two views of the same trail: who has been trying lately and how often,
     * and then the raw run of attempts underneath it.
     */
    public function index(Request $request): JsonResponse
    {
        $since = Carbon::now()->subHours(self::SUMMARY_HOURS);

        return response()->json([
            'window_hours' => self::SUMMARY_HOURS,
            'retention_days' => SecurityEvent::RETENTION_DAYS,
            'by_address' => $this->byAddress($since),
            'totals' => $this->totals($since),
            'recent' => SecurityEvent::query()
                ->with('user:id,name')
                ->orderByDesc('id')
                ->limit(self::RECENT_LIMIT)
                ->get()
                ->map(fn (SecurityEvent $event) => [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'ip_address' => $event->ip_address,
                    'email' => $event->email,
                    'user' => $event->user?->name,
                    'created_at' => $event->created_at,
                ]),
        ]);
    }

    /**
     * Grouped in SQL rather than by pulling every row into PHP — the whole
     * point of this table is that a noisy day has a lot of rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function byAddress(Carbon $since): array
    {
        $rows = SecurityEvent::query()
            ->since($since)
            ->selectRaw('ip_address, type, count(*) as attempts, max(created_at) as last_seen')
            ->groupBy('ip_address', 'type')
            ->get();

        return $rows
            ->groupBy('ip_address')
            ->map(fn ($group, $address) => [
                'ip_address' => $address ?: null,
                'attempts' => (int) $group->sum('attempts'),
                // One count per outcome, named the same way the totals above
                // are: "failed" is a wrong password and nothing else, so a
                // row's three counts add up to its attempts.
                'succeeded' => $this->countOf($group, SecurityEventType::LoginSucceeded),
                'failed' => $this->countOf($group, SecurityEventType::LoginFailed),
                'blocked' => $this->countOf($group, SecurityEventType::LoginBlocked),
                'last_seen' => $group->max('last_seen'),
            ])
            // Noisiest first: that is the row worth looking at.
            ->sortByDesc('attempts')
            ->values()
            ->all();
    }

    /** @param  Collection<int, SecurityEvent>  $group */
    private function countOf($group, SecurityEventType $type): int
    {
        return (int) $group->where('type', $type)->sum('attempts');
    }

    /** @return array<string, int> */
    private function totals(Carbon $since): array
    {
        $counts = SecurityEvent::query()
            ->since($since)
            ->selectRaw('type, count(*) as attempts')
            ->groupBy('type')
            ->pluck('attempts', 'type');

        return [
            'succeeded' => (int) ($counts[SecurityEventType::LoginSucceeded->value] ?? 0),
            'failed' => (int) ($counts[SecurityEventType::LoginFailed->value] ?? 0),
            'blocked' => (int) ($counts[SecurityEventType::LoginBlocked->value] ?? 0),
        ];
    }
}
