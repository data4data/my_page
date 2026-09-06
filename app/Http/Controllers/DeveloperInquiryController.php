<?php

namespace App\Http\Controllers;

use App\Models\DeveloperInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperInquiryController extends Controller
{
    // Public: submitted from the "For developers" connect form (/hi-developer).
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'message' => ['required', 'string', 'max:4000'],
            'company' => ['nullable', 'string', 'max:120'],
            // Protocols pinned: both are rendered as links in the Insights
            // tab, and http(s) is the only thing a portfolio link should be.
            'portfolio_url' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'url:http,https', 'max:255'],
        ]);

        DeveloperInquiry::create($data);

        return response()->json(['message' => 'Thanks — your message has been sent.']);
    }

    // One page of the Insights tab, newest first. No update/destroy endpoints
    // exist — this is intentionally view-only.
    //
    // Paginated because the public form that fills this table is open to
    // anyone and rate-limited per minute, not in total: an unbounded read
    // would eventually load every submission ever made into one response and
    // render them all as cards.
    public const PER_PAGE = 25;

    public function index(Request $request): JsonResponse
    {
        // simplePaginate, not paginate: the list is a "load more" feed, so it
        // never needs a total row count and the extra COUNT query it costs.
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
