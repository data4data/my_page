<?php

namespace App\Http\Controllers;

use App\Events\InquiryReceived;
use App\Models\DeveloperInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperInquiryController extends Controller
{
    /**
     * A field no human ever sees, so anything in it was filled by a bot
     * working through every input on the page. Named like something a bot
     * expects to find rather than like a trap.
     */
    private const HONEYPOT = 'website';

    // Public: submitted from the "For developers" connect form (/hi-developer).
    public function store(Request $request): JsonResponse
    {
        // Answered exactly like a success. Telling the bot it was caught only
        // helps whoever is tuning it.
        if (filled($request->input(self::HONEYPOT))) {
            return $this->accepted();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'message' => ['required', 'string', 'max:4000'],
            'company' => ['nullable', 'string', 'max:120'],
            // Both render as links in Insights, so pin them to http(s).
            'portfolio_url' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'url:http,https', 'max:255'],
        ]);

        // The event is what mails it on; the row is what Insights reads.
        InquiryReceived::dispatch(DeveloperInquiry::create($data));

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['message' => 'Thanks — your message has been sent.']);
    }

    // View-only on purpose: there is no update or destroy. Paginated because
    // the public form filling this table is throttled per minute, not in total.
    public const PER_PAGE = 25;

    public function index(Request $request): JsonResponse
    {
        // simplePaginate: a "load more" feed needs no total, so no COUNT query.
        $page = DeveloperInquiry::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE);

        return response()->json([
            'inquiries' => $page->items(),
            'page' => $page->currentPage(),
            'has_more' => $page->hasMorePages(),
        ]);
    }
}
