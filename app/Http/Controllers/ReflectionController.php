<?php

namespace App\Http\Controllers;

use App\Enums\ReflectionPeriodType;
use App\Models\Reflection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * from the first two, so it is neither written nor looked up.
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
                'period_start' => $this->periodStart($data),
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
            ...$extra,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function forPeriod(Request $request, array $data): Builder
    {
        return Reflection::forPeriod(
            $request->user()->id,
            $data['period_type'],
            $this->periodStart($data),
        );
    }

    /**
     * The first day of the period asked for. Normalised, or a Wednesday and
     * the Monday before it would key two reflections to one week — and the
     * report beside them already counts that week whole.
     *
     * @param  array<string, mixed>  $data
     */
    private function periodStart(array $data): string
    {
        return ReflectionPeriodType::from($data['period_type'])
            ->startFor(Carbon::parse($data['period_start']))
            ->toDateString();
    }
}
