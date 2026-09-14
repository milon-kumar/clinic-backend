<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serviceId' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json([
            'data' => $this->reviews->publicList(
                isset($data['serviceId']) ? (int) $data['serviceId'] : null,
                (int) ($data['limit'] ?? 12),
            ),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reviews->forCustomer($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'serviceId' => ['nullable', 'integer', 'exists:services,id'],
            'clinicId' => ['nullable', 'integer', 'exists:clinics,id'],
            'appointmentId' => ['nullable', 'integer', 'exists:appointments,id'],
        ]);

        $review = $this->reviews->createForCustomer($request->user(), $data);

        return response()->json([
            'message' => 'Thanks — your review is waiting for approval.',
            'data' => $review->toApi('owner'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['sometimes', 'string', 'max:2000'],
        ]);

        $review = $this->reviews->updateForCustomer($request->user(), $review, $data);

        return response()->json(['data' => $review->toApi('owner')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $this->reviews->deleteForCustomer($request->user(), $review);

        return response()->json(['message' => 'Review deleted']);
    }
}
