<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClinicStaff;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Roles;
use App\Support\UserMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
        ]);

        if (! empty($data['dateOfBirth']) && now()->parse($data['dateOfBirth'])->age < 18) {
            throw ValidationException::withMessages([
                'dateOfBirth' => ['You must be 18 or older to register.'],
            ]);
        }

        $user = User::create(UserMapper::fromRegister($data));
        $this->otpService->generate($user);

        return response()->json([
            'message' => 'Account created. Please verify your email.',
            'user' => $user->toApi(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            'user' => $user->toApi(),
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        if (! $this->otpService->verify($user, $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired verification code.'],
            ]);
        }

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $user->fresh()->toApi(),
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();
        $this->otpService->resend($user);

        return response()->json([
            'message' => 'A new verification code has been sent.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $this->otpService->generate($user, OtpService::TYPE_PASSWORD_RESET);
        }

        return response()->json([
            'message' => 'If that email is registered, we have sent a reset code.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:6'],
            'passwordConfirmation' => ['required', 'same:password'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $this->otpService->verify($user, $data['otp'], OtpService::TYPE_PASSWORD_RESET)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid or expired reset code.'],
            ]);
        }

        $user->update([
            'password' => $data['password'],
        ]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password updated. You can sign in with your new password.',
        ]);
    }

    public function createStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(Roles::staff())],
            'clinicId' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        if (Roles::isSuperAdmin($data['role']) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only a superadmin can assign organisation admin roles.');
        }

        $user = User::create(UserMapper::staffPayload($data));

        if (! empty($data['clinicId'])) {
            ClinicStaff::updateOrCreate(
                ['clinic_id' => $data['clinicId'], 'user_id' => $user->id],
                ['is_active' => true]
            );
        }

        return response()->json([
            'data' => $user->load('clinic')->toApi(),
        ], 201);
    }
}
