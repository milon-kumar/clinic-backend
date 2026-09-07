<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class ApiController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->role,
            'isVerified' => $user->is_verified,
            'selectedClinicId' => $user->selected_clinic_id,
            'dateOfBirth' => $user->date_of_birth?->format('Y-m-d'),
        ];
    }

    protected function resolveCart(Request $request, string $type = 'buy'): Cart
    {
        $user = $request->user();

        if ($user) {
            return Cart::query()->firstOrCreate(
                ['customer_id' => $user->id, 'cart_type' => $type],
                ['expires_at' => now()->addDays(7)]
            );
        }

        $token = $request->header('X-Guest-Token')
            ?? $request->cookie('guest_token')
            ?? Str::uuid()->toString();

        return Cart::query()->firstOrCreate(
            ['guest_token' => $token, 'cart_type' => $type, 'customer_id' => null],
            ['expires_at' => now()->addDay()]
        );
    }
}
