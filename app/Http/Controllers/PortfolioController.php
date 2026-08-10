<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProfile;
use App\Support\DefaultPortfolioContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function app(): View
    {
        // Soft lookup (not activeProfile()'s firstOrFail) so every page —
        // including /login — still renders on a fresh install before
        // anything has been seeded, just with a generic title.
        $profile = PortfolioProfile::query()->where('is_active', true)->first();

        $role = $profile ? ($profile->role['en'] ?? $profile->role['nl'] ?? '') : '';
        $title = $profile ? trim($profile->initials.($role ? " | {$role}" : '')) : 'Digital Visit Card';

        return view('app', ['siteTitle' => $title]);
    }

    public function show(): JsonResponse
    {
        $profile = $this->activeProfile();

        return response()->json($this->payload($profile, publicOnly: true));
    }

    public function edit(): JsonResponse
    {
        $profile = $this->activeProfile();

        return response()->json($this->payload($profile, publicOnly: false));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile' => ['required', 'array'],
            'metrics' => ['array'],
            'expertise_items' => ['array'],
            'projects' => ['array'],
            'process_steps' => ['array'],
        ]);

        $profile = $this->activeProfile();

        DB::transaction(function () use ($profile, $data): void {
            $profile->update(Arr::only($data['profile'], [
                'initials',
                'role',
                'headline',
                'summary',
                'primary_cta_label',
                'primary_cta_url',
                'secondary_cta_label',
                'secondary_cta_url',
                'location_note',
                'availability_note',
                'quote',
                'quote_author',
                'social_links',
            ]));

            $this->replaceOrdered($profile, 'metrics', $data['metrics'] ?? [], [
                'value',
                'label',
                'is_visible',
            ]);

            $this->replaceOrdered($profile, 'expertiseItems', $data['expertise_items'] ?? [], [
                'title',
                'description',
                'icon',
                'category',
                'is_visible',
            ]);

            $this->replaceOrdered($profile, 'projects', $data['projects'] ?? [], [
                'title',
                'summary',
                'result',
                'tags',
                'visual_style',
                'is_visible',
            ]);

            $this->replaceOrdered($profile, 'processSteps', $data['process_steps'] ?? [], [
                'group',
                'title',
                'description',
                'icon',
                'is_visible',
            ]);
        });

        return response()->json($this->payload($this->activeProfile(), publicOnly: false));
    }

    public function seedDefaults(): JsonResponse
    {
        DefaultPortfolioContent::seed();

        return response()->json($this->payload($this->activeProfile(), publicOnly: false));
    }

    private function activeProfile(): PortfolioProfile
    {
        return PortfolioProfile::query()
            ->where('is_active', true)
            ->with([
                'metrics' => fn ($query) => $query->orderBy('sort_order'),
                'expertiseItems' => fn ($query) => $query->orderBy('sort_order'),
                'projects' => fn ($query) => $query->orderBy('sort_order'),
                'processSteps' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->firstOrFail();
    }

    private function payload(PortfolioProfile $profile, bool $publicOnly): array
    {
        $visible = fn ($items) => $publicOnly ? $items->where('is_visible', true)->values() : $items->values();

        return [
            'profile' => $profile,
            'metrics' => $visible($profile->metrics),
            'expertise_items' => $visible($profile->expertiseItems),
            'projects' => $visible($profile->projects),
            'process_steps' => $visible($profile->processSteps),
        ];
    }

    private function replaceOrdered(PortfolioProfile $profile, string $relation, array $items, array $keys): void
    {
        $profile->{$relation}()->delete();

        foreach (array_values($items) as $index => $item) {
            $payload = Arr::only($item, $keys);
            $payload['sort_order'] = $index + 1;
            $payload['is_visible'] = $item['is_visible'] ?? true;

            $profile->{$relation}()->create($payload);
        }
    }
}
