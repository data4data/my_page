<?php

namespace App\Http\Controllers;

use App\Events\InquiryReceived;
use App\Models\DeveloperInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperInquiryController extends Controller
{
    /** A field no human sees, named like something a bot expects to find. */
    private const HONEYPOT = 'website';

    public function store(Request $request): JsonResponse
    {
        // Answered exactly like a success, so probing cannot tell them apart.
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

        // The event mails it on; the row is what Insights reads.
        InquiryReceived::dispatch(DeveloperInquiry::create($data));

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['message' => 'Thanks — your message has been sent.']);
    }

    // View-only on purpose: there is no update or destroy.
    public const PER_PAGE = 25;

    public function index(Request $request): JsonResponse
    {
        // A "load more" feed needs no total, so no COUNT query.
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
