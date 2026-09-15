<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePortfolioRequest;
use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Services\PortfolioContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function __construct(private PortfolioContentService $content) {}

    public function app(Request $request): View
    {
        // Not activeProfile(), which throws: every page must still render on
        // a fresh install before anything is seeded.
        $profile = PortfolioProfile::query()->where('is_active', true)->first();

        $role = $profile ? ($profile->role['en'] ?? $profile->role['nl'] ?? '') : '';
        $title = $profile ? trim($profile->initials.($role ? " | {$role}" : '')) : 'Digital Visit Card';

        return view('app', [
            'siteTitle' => $title,
            // Every page renders this same shell, so emitting the prefix
            // always would put the private URL in the public page's source.
            // Only requests already inside the workspace get it — reaching
            // one of those URLs means you already knew the prefix.
            'adminPath' => $this->insideWorkspace($request) ? config('admin.path') : null,
        ]);
    }

    private function insideWorkspace(Request $request): bool
    {
        $path = config('admin.path');

        return $request->is($path) || $request->is($path.'/*');
    }

    public function show(): JsonResponse
    {
        return response()->json(
            $this->content->payload($this->content->activeProfile(), publicOnly: true)
        );
    }

    public function edit(): JsonResponse
    {
        return response()->json(
            $this->content->payload($this->content->activeProfile(), publicOnly: false)
        );
    }

    public function update(UpdatePortfolioRequest $request): JsonResponse
    {
        $profile = $this->content->save($request->validated(), $request->user());

        return response()->json($this->content->payload($profile, publicOnly: false));
    }

    public function seedDefaults(Request $request): JsonResponse
    {
        $profile = $this->content->seedDefaults($request->user());

        return response()->json($this->content->payload($profile, publicOnly: false));
    }

    /**
     * The saved-version list. Excludes `payload`, which runs to tens of
     * kilobytes a row; the list only shows when and by whom.
     */
    public function revisions(): JsonResponse
    {
        $revisions = $this->content->activeProfile()
            ->revisions()
            ->with('user:id,name')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'created_at'])
            ->map(fn (PortfolioRevision $revision) => [
                'id' => $revision->id,
                'created_at' => $revision->created_at,
                'author' => $revision->user?->name,
            ]);

        return response()->json(['revisions' => $revisions]);
    }

    public function restore(Request $request, PortfolioRevision $revision): JsonResponse
    {
        $profile = $this->content->activeProfile();

        // Another profile's revision is not this page's history at all.
        abort_unless($revision->portfolio_profile_id === $profile->id, 404);

        $restored = $this->content->restore($revision, $request->user());

        return response()->json($this->content->payload($restored, publicOnly: false));
    }
}
