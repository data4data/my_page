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
        // Soft lookup (not activeProfile()'s firstOrFail) so every page —
        // including /login — still renders on a fresh install before
        // anything has been seeded, just with a generic title.
        $profile = PortfolioProfile::query()->where('is_active', true)->first();

        $role = $profile ? ($profile->role['en'] ?? $profile->role['nl'] ?? '') : '';
        $title = $profile ? trim($profile->initials.($role ? " | {$role}" : '')) : 'Digital Visit Card';

        return view('app', [
            'siteTitle' => $title,
            // The workspace prefix is deliberately unguessable (config/admin.php)
            // and every page in the app renders this same shell — so emitting it
            // unconditionally served the private URL to anyone who viewed source
            // on the public visit card. Only someone who can actually reach the
            // workspace is told where it is; admin-path.js needs it nowhere else.
            'adminPath' => $request->user()?->hasRole('admin') ? config('admin.path') : null,
        ]);
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
     * The saved-version list for the Reset content tab. Deliberately excludes
     * `payload` — that column can run to tens of kilobytes per row, and the
     * list only needs to show when and by whom.
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

        // A revision belonging to a different profile is not this page's
        // history, so it is not found here rather than merely forbidden.
        abort_unless($revision->portfolio_profile_id === $profile->id, 404);

        $restored = $this->content->restore($revision, $request->user());

        return response()->json($this->content->payload($restored, publicOnly: false));
    }
}
