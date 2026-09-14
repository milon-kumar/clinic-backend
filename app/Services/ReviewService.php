<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Review;
use App\Models\User;
use App\Support\BranchScope;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function publicList(?int $serviceId = null, int $limit = 12): Collection
    {
        $query = Review::query()
            ->published()
            ->with(['user', 'clinic', 'service'])
            ->orderByDesc('created_at');

        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        return $query->limit(max(1, min(50, $limit)))
            ->get()
            ->map(fn (Review $review) => $review->toApi('public'))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forCustomer(User $user): Collection
    {
        return Review::query()
            ->where('user_id', $user->id)
            ->with(['user', 'clinic', 'service'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Review $review) => $review->toApi('owner'))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForCustomer(User $user, array $data): Review
    {
        $appointment = null;
        if (! empty($data['appointmentId'])) {
            $appointment = Appointment::query()
                ->where('customer_id', $user->id)
                ->find($data['appointmentId']);

            if (! $appointment) {
                throw ValidationException::withMessages([
                    'appointmentId' => ['Appointment not found.'],
                ]);
            }
            if ($appointment->status !== 'completed') {
                throw ValidationException::withMessages([
                    'appointmentId' => ['You can review a visit after it is completed.'],
                ]);
            }
            if (Review::query()->where('appointment_id', $appointment->id)->exists()) {
                throw ValidationException::withMessages([
                    'appointmentId' => ['This visit already has a review.'],
                ]);
            }
        }

        $review = Review::create([
            'user_id' => $user->id,
            'clinic_id' => $appointment?->clinic_id ?? ($data['clinicId'] ?? $user->selected_clinic_id),
            'service_id' => $appointment?->service_id ?? ($data['serviceId'] ?? null),
            'appointment_id' => $appointment?->id,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'status' => Review::STATUS_PENDING,
        ]);

        $this->notifications->newReview($review->load(['user', 'clinic', 'service']));

        return $review;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForCustomer(User $user, Review $review, array $data): Review
    {
        if ((int) $review->user_id !== (int) $user->id) {
            abort(403, 'You can only edit your own review.');
        }
        if ($review->status !== Review::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Published reviews cannot be edited. Contact the clinic if you need a change.'],
            ]);
        }

        $review->update([
            'rating' => $data['rating'] ?? $review->rating,
            'title' => array_key_exists('title', $data) ? $data['title'] : $review->title,
            'body' => $data['body'] ?? $review->body,
        ]);

        return $review->fresh(['user', 'clinic', 'service']);
    }

    public function deleteForCustomer(User $user, Review $review): void
    {
        if ((int) $review->user_id !== (int) $user->id) {
            abort(403, 'You can only delete your own review.');
        }
        if ($review->status !== Review::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Published reviews cannot be deleted. Contact the clinic if you need a change.'],
            ]);
        }

        $review->delete();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function adminList(User $staff, ?string $search = null, ?string $status = null, ?int $clinicId = null): Collection
    {
        $query = Review::query()
            ->with(['user', 'clinic', 'service'])
            ->orderByDesc('created_at');

        BranchScope::apply($query, $staff);

        if ($clinicId) {
            BranchScope::assert($staff, $clinicId);
            $query->where('clinic_id', $clinicId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('admin_reply', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($user) use ($search) {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('service', fn ($service) => $service->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->limit(500)
            ->get()
            ->map(fn (Review $review) => $review->toApi('staff'))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForAdmin(Review $review, array $data): Review
    {
        $attrs = [];
        if (! empty($data['status'])) {
            $attrs['status'] = $data['status'];
        }
        if (array_key_exists('adminReply', $data)) {
            $attrs['admin_reply'] = $data['adminReply'] ?: null;
            $attrs['replied_at'] = $data['adminReply'] ? now() : null;
        }
        if ($attrs) {
            $review->update($attrs);
        }

        return $review->fresh(['user', 'clinic', 'service']);
    }
}
