<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchScope
{
    public static function apply(Builder $query, User $user, string $column = 'clinic_id'): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $ids = $user->accessibleClinicIds();

        return $query->whereIn($column, $ids ?: [0]);
    }

    public static function assert(User $user, int $clinicId): void
    {
        if (! $user->canAccessClinic($clinicId)) {
            abort(403, 'This clinic is outside your branch.');
        }
    }
}
