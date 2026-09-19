<?php

namespace App\Http\Controllers;

use App\Enums\ReflectionPeriodType;
use App\Models\Reflection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReflectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate($this->periodRules());

        return response()->json(['reflection' => $this->forPeriod($request, $data)->first()]);
    }

    /**
     * Upsert by (user, period_type, period_start). `period_end` is generated
     * from the type and the start, so it is neither written nor looked up —
     * see the reflections migration. Both halves find the row the same way,
     * through scopeForPeriod().
     */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate($this->periodRules([
            'notes' => ['nullable', 'string', 'max:8000'],
        ]));

        $reflection = $this->forPeriod($request, $data)->first();

        if ($reflection) {
            $reflection->update(['notes' => $data['notes'] ?? null]);
        } else {
            $reflection = Reflection::create([
                'user_id' => $request->user()->id,
                'period_type' => $data['period_type'],
                'period_start' => $data['period_start'],
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return response()->json(['reflection' => $reflection]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function periodRules(array $extra = []): array
    {
        return [
            'period_type' => ['required', Rule::enum(ReflectionPeriodType::class)],
            'period_start' => ['required', 'date'],
            // Still accepted so the frontend need not change, and still
            // checked for sense — but the column is derived, so the value is
            // not stored and not looked up.
            'period_end' => ['sometimes', 'date', 'after_or_equal:period_start'],
            ...$extra,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function forPeriod(Request $request, array $data): Builder
    {
        return Reflection::forPeriod(
            $request->user()->id,
            $data['period_type'],
            $data['period_start'],
        );
    }
}
