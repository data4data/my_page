<?php

namespace App\Http\Controllers;

use App\Enums\ReflectionPeriodType;
use App\Models\Reflection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReflectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_type' => ['required', Rule::enum(ReflectionPeriodType::class)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $reflection = Reflection::query()
            ->where('user_id', $request->user()->id)
            ->where('period_type', $data['period_type'])
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->first();

        return response()->json(['reflection' => $reflection]);
    }

    // Upsert by (user, period_type, period_start, period_end) — matched via
    // whereDate() rather than Eloquent's updateOrCreate(), because the
    // 'date' cast stores period_start/period_end with a time component
    // ("Y-m-d H:i:s"); updateOrCreate's raw search array bypasses that cast
    // and compares against the plain "Y-m-d" input, so it would never find
    // the existing row and would hit the table's unique constraint instead.
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_type' => ['required', Rule::enum(ReflectionPeriodType::class)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:8000'],
        ]);

        $reflection = Reflection::query()
            ->where('user_id', $request->user()->id)
            ->where('period_type', $data['period_type'])
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->first();

        if ($reflection) {
            $reflection->update(['notes' => $data['notes'] ?? null]);
        } else {
            $reflection = Reflection::create([
                'user_id' => $request->user()->id,
                'period_type' => $data['period_type'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return response()->json(['reflection' => $reflection]);
    }
}
