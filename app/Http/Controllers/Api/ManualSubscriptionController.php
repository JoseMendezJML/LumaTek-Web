<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionQuote;
use App\Services\SubscriptionContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualSubscriptionController extends Controller
{
    public function quote(Request $request, SubscriptionContractService $service): JsonResponse
    {
        $data = $request->validate([
            'billing_period' => ['required', 'in:monthly,yearly'],
            'request_key' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(['quote' => $service->quote($request->user(), $data['billing_period'], $data['request_key'])]);
    }

    public function confirm(Request $request, string $quote, SubscriptionContractService $service): JsonResponse
    {
        // Resolve only after platform.admin has authorized the request, so binding
        // cannot reveal quote existence through different 403/404 responses.
        $quote = SubscriptionQuote::findOrFail($quote);

        $data = $request->validate([
            'medium' => ['required', 'string', 'max:100'],
            'reference' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(['confirmation' => $service->confirm($request->user(), $quote, $data['medium'], $data['reference'])]);
    }
}
