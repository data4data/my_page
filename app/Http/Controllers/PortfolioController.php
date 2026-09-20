<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePortfolioRequest;
use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Services\PortfolioContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function __construct(private PortfolioContentService $content) {}

    public function app(Request $request, ?string $locale = null): View|RedirectResponse
    {
        // Not activeProfile(), which throws: every page must render on a
        // fresh install, before anything is seeded.
        $profile = PortfolioProfile::query()->first();
        $inWorkspace = $this->insideWorkspace($request);
        $default = $profile->default_language ?? 'en';

        // The default language lives at the unprefixed path, so /en and /
        // would otherwise be the same page at two addresses.
        if ($locale !== null && $locale === $default) {
            return redirect($this->barePath($request, $locale), 301);
        }

        $active = $locale ?? $default;

        $role = $profile ? $this->translated($profile->role, $active) : '';
        $title = $profile ? trim($profile->initials.($role ? " | {$role}" : '')) : 'Digital Visit Card';

        return view('app', [
            'siteTitle' => $title,
            // Only inside the workspace: every page renders this same shell,
            // so emitting it always would leak the private prefix publicly.
            'adminPath' => $inWorkspace ? config('admin.path') : null,
            // Which of the two bundles the shell loads. Kept separate from
            // adminPath so a change to one does not silently change the other.
            'inWorkspace' => $inWorkspace,
            // Null inside the workspace: private pages get noindex instead.
            'meta' => $inWorkspace ? null : $this->publicMeta($profile, $request, $title, $active, $default),
            'noindex' => $inWorkspace,
        ]);
    }

    /** The same path with the language prefix taken off: /en/hi-developer -> /hi-developer. */
    private function barePath(Request $request, string $locale): string
    {
        return '/'.ltrim(Str::after($request->path(), $locale), '/');
    }

    /**
     * What a crawler is told about the public visit card. The body is painted
     * by Vue, and link-preview crawlers run no JavaScript, so anything missing
     * from this response is missing from the preview.
     *
     * @return array<string, mixed>
     */
    private function publicMeta(?PortfolioProfile $profile, Request $request, string $title, string $locale, string $default): array
    {
        return [
            'title' => $title,
            'description' => $this->translated($profile?->summary, $locale),
            'locale' => $locale,
            // As asked for, so /hi-developer is canonical to itself.
            'url' => $request->url(),
            // hreflang: tells a crawler /nl is this page in Dutch, not a duplicate.
            'alternates' => $this->alternates($request, $locale, $default),
            'defaultLocale' => $default,
            // Null keeps the small card; the large one is a blank slab with no image.
            'image' => $profile?->social_image_url,
            'schema' => $profile ? $this->personSchema($profile, $locale, $request) : null,
        ];
    }

    /**
     * Absolute URL per language for the page being rendered.
     *
     * @return array<string, string>
     */
    private function alternates(Request $request, string $locale, string $default): array
    {
        $bare = trim($locale === $default ? $request->path() : Str::after($request->path(), $locale), '/');

        $alternates = [];

        foreach (config('app.locales') as $code) {
            $path = $code === $default ? $bare : trim($code.'/'.$bare, '/');
            $alternates[$code] = url($path === '' ? '/' : $path);
        }

        return $alternates;
    }

    /**
     * schema.org Person. `name` is the initials, the only name the profile holds.
     *
     * @return array<string, string>
     */
    private function personSchema(PortfolioProfile $profile, string $locale, Request $request): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => (string) $profile->initials,
            'jobTitle' => $this->translated($profile->role, $locale),
            'description' => $this->translated($profile->summary, $locale),
            'url' => $request->url(),
        ]);
    }

    /** One half of an {en, nl} column, falling back to whichever half exists. */
    private function translated(mixed $value, string $locale): string
    {
        if (! is_array($value)) {
            return is_string($value) ? $value : '';
        }

        return $value[$locale] ?? $value['en'] ?? $value['nl'] ?? '';
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

    /** Excludes `payload`, tens of kilobytes a row; the list shows when and by whom. */
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
