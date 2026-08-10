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
            'portfolio_url' => ['nullable', 'string', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'url', 'max:255'],
        ]);

        DeveloperInquiry::create($data);

        return response()->json(['message' => 'Thanks — your message has been sent.']);
    }

    // Admin-only, read-only: listed in the Inquiries tab, newest first. No
    // update/destroy endpoints exist — this is intentionally view-only.
    public function index(): JsonResponse
    {
        $inquiries = DeveloperInquiry::query()
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['inquiries' => $inquiries]);
    }
}
