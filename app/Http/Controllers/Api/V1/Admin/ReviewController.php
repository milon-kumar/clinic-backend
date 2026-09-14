<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $clinicId = $request->query('clinicId');

        return response()->json([
            'data' => $this->reviews->adminList(
                $request->user(),
                $request->query('search', $request->query('q')),
                $request->query('status'),
                $clinicId ? (int) $clinicId : null,
            ),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $review = Review::query()->with(['user', 'clinic', 'service'])->findOrFail($id);
        if ($review->clinic_id) {
            BranchScope::assert($request->user(), (int) $review->clinic_id);
        }

        return response()->json(['data' => $review->toApi('staff')]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $review = Review::query()->with(['user', 'clinic', 'service'])->findOrFail($id);
        if ($review->clinic_id) {
            BranchScope::assert($request->user(), (int) $review->clinic_id);
        }

        $data = $request->validate([
            'status' => ['nullable', Rule::in([Review::STATUS_PENDING, Review::STATUS_PUBLISHED, Review::STATUS_HIDDEN])],
            'adminReply' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $this->reviews->updateForAdmin($review, $data);

        return response()->json(['data' => $review->toApi('staff')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        if ($review->clinic_id) {
            BranchScope::assert($request->user(), (int) $review->clinic_id);
        }
        $review->delete();

        return response()->json(['message' => 'Review deleted']);
    }
}
