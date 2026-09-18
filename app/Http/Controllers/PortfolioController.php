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
        // Not activeProfile(), which throws: every page must still render on
        // a fresh install before anything is seeded.
        $profile = PortfolioProfile::query()->where('is_active', true)->first();
        $inWorkspace = $this->insideWorkspace($request);
        $default = $profile->default_language ?? 'en';

        // One page, one URL. The default language lives at the unprefixed
        // path, so /en and / would otherwise be the same page twice and split
        // whatever ranking either of them earned.
        if ($locale !== null && $locale === $default) {
            return redirect($this->barePath($request, $locale), 301);
        }

        $active = $locale ?? $default;

        $role = $profile ? $this->translated($profile->role, $active) : '';
        $title = $profile ? trim($profile->initials.($role ? " | {$role}" : '')) : 'Digital Visit Card';

        return view('app', [
            'siteTitle' => $title,
            // Every page renders this same shell, so emitting the prefix
            // always would put the private URL in the public page's source.
            // Only requests already inside the workspace get it — reaching
            // one of those URLs means you already knew the prefix.
            'adminPath' => $inWorkspace ? config('admin.path') : null,
            // Which of the two bundles the shell loads. Separate from
            // adminPath rather than derived from it: they happen to share a
            // condition today, and a change to what the meta tag is for
            // should not silently change which JavaScript is served.
            'inWorkspace' => $inWorkspace,
            // Null inside the workspace: those pages are private, so they get
            // the noindex below instead of a description to share.
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
     * What a crawler is told about the public visit card.
     *
     * The page body is rendered by Vue in the browser, and the crawlers behind
     * link previews — LinkedIn, WhatsApp, Slack, iMessage — do not run
     * JavaScript. Whatever is not in this response does not exist to them, so
     * the title and summary are read out of the database here rather than left
     * to the bundle that paints them a moment later.
     *
     * @return array<string, mixed>
     */
    private function publicMeta(?PortfolioProfile $profile, Request $request, string $title, string $locale, string $default): array
    {
        return [
            'title' => $title,
            'description' => $this->translated($profile?->summary, $locale),
            'locale' => $locale,
            // The URL as asked for, so /hi-developer is canonical to itself
            // rather than pointing every route at the root.
            'url' => $request->url(),
            // Every language this page exists in, for hreflang. Without them a
            // crawler has no way to know /nl is the same page in Dutch rather
            // than an unrelated one, and may treat them as duplicates.
            'alternates' => $this->alternates($request, $locale, $default),
            'defaultLocale' => $default,
            // The picture a link preview shows. Null keeps the small card:
            // the large one renders as a blank slab without an image.
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
        // The path with no language on it, which every alternate is built from.
        $bare = trim($locale === $default ? $request->path() : Str::after($request->path(), $locale), '/');

        $alternates = [];

        foreach (config('app.locales') as $code) {
            $path = $code === $default ? $bare : trim($code.'/'.$bare, '/');
            $alternates[$code] = url($path === '' ? '/' : $path);
        }

        return $alternates;
    }

    /**
     * schema.org Person, the structured half of the same facts.
     *
     * `name` is the initials because that is the only name the profile holds —
     * it is what the page itself shows, so claiming anything richer here would
     * be describing a page that does not exist.
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
