<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class UserMapper
{
    public static function fromRegister(array $data): array
    {
        $first = $data['firstName'] ?? $data['first_name'] ?? '';
        $last = $data['lastName'] ?? $data['last_name'] ?? '';

        return [
            'name' => trim($first.' '.$last) ?: ($data['username'] ?? $data['email']),
            'first_name' => $first ?: null,
            'last_name' => $last ?: null,
            'username' => ($data['username'] ?? null) ?: null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'date_of_birth' => $data['dateOfBirth'] ?? $data['date_of_birth'] ?? null,
            'password' => $data['password'],
            'role' => 'patient',
            'is_verified' => false,
        ];
    }

    public static function staffPayload(array $data): array
    {
        $first = $data['firstName'] ?? $data['first_name'] ?? '';
        $last = $data['lastName'] ?? $data['last_name'] ?? '';

        return [
            'name' => trim($first.' '.$last) ?: ($data['username'] ?? $data['email']),
            'first_name' => $first ?: null,
            'last_name' => $last ?: null,
            'username' => $data['username'] ?? 'staff_'.Str::random(6),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'] ?? 'password',
            'role' => $data['role'] ?? 'receptionist',
            'clinic_id' => $data['clinicId'] ?? $data['clinic_id'] ?? null,
            'is_verified' => true,
            'email_verified_at' => now(),
        ];
    }
}
