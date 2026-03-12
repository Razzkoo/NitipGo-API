<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\TravelerResource;
use App\Models\User;
use App\Models\Traveler;
use App\Models\SystemSetting;
use App\Models\LoginActivity;
use Illuminate\Http\Request;
use App\Models\UserRequest;
use App\Models\TravelerRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Notifications\NewDeviceLoginNotification;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // Google redirect login
    public function googleRedirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    // Google callback (response google)
    public function googleCallback(Request $request)
    {
        try {
            if (!$request->has('code')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login cancelled by user'
                ], 400);
            }

            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            $user = User::where('email', $googleUser->email)->first();

            if ($user && $user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is ' . $user->status
                ], 403);
            }

            if (!$user) {
                $user = User::create([
                    'name'     => $googleUser->name,
                    'email'    => $googleUser->email,
                    'password' => Hash::make(\Illuminate\Support\Str::random(16)),
                    'role'     => 'customer',
                    'status'   => 'active'
                ]);
            }

            $user->tokens()->delete();

            $accessToken  = $user->createAccessToken('google_login', ['*']);
            $refreshToken = $user->createRefreshToken('google_login');

            return response()->json([
                'success'       => true,
                'access_token'  => $accessToken->token,
                'refresh_token' => $refreshToken->token,
                'user'          => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google login failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Google token (untuk mobile/SPA)
    public function googleTokenLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token
        ]);

        if ($response->failed() || $response['aud'] !== env('GOOGLE_CLIENT_ID')) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid'
            ], 401);
        }

        $googleData = $response->json();

        $user = User::where('email', $googleData['email'])->first();

        if ($user && $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is ' . $user->status
            ], 403);
        }

        if (!$user) {
            $user = User::create([
                'name'     => $googleData['name'] ?? $googleData['email'],
                'email'    => $googleData['email'],
                'password' => Hash::make(\Illuminate\Support\Str::random(16)),
                'role'     => 'customer',
                'status'   => 'active'
            ]);
        }

        $user->tokens()->delete();

        $accessToken  = $user->createAccessToken('google_login', ['*']);
        $refreshToken = $user->createRefreshToken('google_login');

        return response()->json([
            'success'       => true,
            'access_token'  => $accessToken->token,
            'refresh_token' => $refreshToken->token,
            'user'          => new UserResource($user)
        ]);
    }

    // AUTH CUSTOMER (REGISTER & LOGIN)
    public function registerCustomer(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => [
                    'required', 'email',
                    Rule::unique('users', 'email'),
                    Rule::unique('user_requests', 'email'),
                ],
                'phone'    => [
                    'nullable',
                    Rule::unique('users', 'phone'),
                    Rule::unique('user_requests', 'phone'),
                ],
                'password' => 'required|string|min:8|confirmed',
            ]);

            $existingRequest = UserRequest::where('email', $validated['email'])->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah pernah mendaftar dan sedang menunggu persetujuan admin.',
                ], 403);
            }

            UserRequest::create([
                'name'             => $validated['name'],
                'email'            => $validated['email'],
                'phone'            => $validated['phone'] ?? null,
                'password'         => Hash::make($validated['password']),
                'requested_role'   => 'customer',
                'status_requested' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registrasi customer berhasil, menunggu persetujuan admin',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function loginCustomer(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }

        $key = 'login:' . $request->ip() . ':' . $request->email;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 900);
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        RateLimiter::clear($key);

        if (!in_array($user->role, ['customer', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak memiliki akses',
            ], 403);
        }

        $maintenance = SystemSetting::where('key', 'maintenance_mode')->value('value');

        if ($maintenance == '1' && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Sistem sedang maintenance. Anda tidak bisa login.',
            ], 503);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif',
            ], 403);
        }

        $user->tokens()->delete();

        $accessToken  = $user->createAccessToken('customer_app', ['*']);
        $refreshToken = $user->createRefreshToken('customer_app');

        $ip        = $request->ip();
        $userAgent = $request->userAgent();

        $isNewDevice = !LoginActivity::where('user_id', $user->id)
            ->where('ip_address', $ip)
            ->where('status', 'success')
            ->exists();

        LoginActivity::create([
            'user_id'    => $user->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'location'   => $this->getLocationFromIp($ip),
            'status'     => 'success',
        ]);

        if ($isNewDevice) {
            $user->notify(new NewDeviceLoginNotification($ip, $userAgent));
        }

        return response()->json([
            'success' => true,
            'message' => 'Login success',
            'data'    => [
                'user'          => new UserResource($user),
                'access_token'  => $accessToken->token,
                'refresh_token' => $refreshToken->token,
            ],
        ]);
    }

    // AUTH TRAVELER (REGISTER & LOGIN)
    public function registerTraveler(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'            => 'required|string|max:255',
                'email'           => [
                    'required', 'email',
                    Rule::unique('travelers', 'email'),
                    Rule::unique('traveler_requests', 'email'),
                ],
                'phone'           => [
                    'nullable',
                    Rule::unique('travelers', 'phone'),
                    Rule::unique('traveler_requests', 'phone'),
                ],
                'password'        => 'required|string|min:8|confirmed',
                'city'            => 'nullable|string|max:255',
                'province'        => 'nullable|string|max:255',
                'address'         => 'nullable|string',
                'birth_date'      => 'nullable|date',
                'gender'          => 'nullable|in:male,female',
                'ktp_number'      => 'nullable|string|max:20',
                'ktp_photo'       => 'nullable|file|image|max:2048',
                'selfie_with_ktp' => 'nullable|file|image|max:2048',
                'pass_photo'      => 'nullable|file|image|max:2048',
                'sim_card_photo'  => 'nullable|file|image|max:2048',
            ]);

            $existingRequest = TravelerRequest::where('email', $validated['email'])->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah pernah mendaftar dan sedang menunggu persetujuan admin.',
                ], 403);
            }

            $photos = ['ktp_photo', 'selfie_with_ktp', 'pass_photo', 'sim_card_photo'];
            foreach ($photos as $photo) {
                if ($request->hasFile($photo)) {
                    $validated[$photo] = $request->file($photo)->store("traveler/{$photo}", 'public');
                }
            }

            TravelerRequest::create([
                'name'             => $validated['name'],
                'email'            => $validated['email'],
                'phone'            => $validated['phone'] ?? null,
                'password'         => Hash::make($validated['password']),
                'city'             => $validated['city'] ?? null,
                'province'         => $validated['province'] ?? null,
                'address'          => $validated['address'] ?? null,
                'birth_date'       => $validated['birth_date'] ?? null,
                'gender'           => $validated['gender'] ?? null,
                'ktp_number'       => $validated['ktp_number'] ?? null,
                'ktp_photo'        => $validated['ktp_photo'] ?? null,
                'selfie_with_ktp'  => $validated['selfie_with_ktp'] ?? null,
                'pass_photo'       => $validated['pass_photo'] ?? null,
                'sim_card_photo'   => $validated['sim_card_photo'] ?? null,
                'status_requested' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registrasi traveler berhasil, menunggu persetujuan admin',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function loginTraveler(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }

        $key = 'login_traveler:' . $request->ip() . ':' . $request->email;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $traveler = Traveler::where('email', $request->email)->first();

        if (!$traveler || !Hash::check($request->password, $traveler->password)) {
            RateLimiter::hit($key, 900);
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        RateLimiter::clear($key);

        $maintenance = SystemSetting::where('key', 'maintenance_mode')->value('value');

        if ($maintenance == '1') {
            return response()->json([
                'success' => false,
                'message' => 'Sistem sedang maintenance. Anda tidak bisa login.',
            ], 503);
        }

        if ($traveler->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif',
            ], 403);
        }

        $traveler->tokens()->delete();

        $accessToken  = $traveler->createAccessToken('traveler_app', ['*']);
        $refreshToken = $traveler->createRefreshToken('traveler_app');

        return response()->json([
            'success' => true,
            'message' => 'Login success',
            'data'    => [
                'traveler'      => new TravelerResource($traveler),
                'access_token'  => $accessToken->token,
                'refresh_token' => $refreshToken->token,
            ],
        ]);
    }

    // Forgot Password
    public function forgotPassword(Request $request)
    {
        $key = 'forgot_password:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        RateLimiter::hit($key, 300);

        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Jika email terdaftar, link reset akan dikirim.'
            ]);
        }

        $token = \Illuminate\Support\Str::random(64);
        Cache::put('reset_password_' . $token, $user->id, now()->addMinutes(30));
        Mail::to($user->email)->send(new ResetPasswordMail($token));

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, link reset akan dikirim.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'password' => 'required|min:8|confirmed'
        ]);

        $userId = Cache::get('reset_password_' . $request->token);

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau expired'
            ], 400);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        Cache::forget('reset_password_' . $request->token);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset'
        ]);
    }

    // LOGOUT
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->currentAccessToken()) {
            return response()->json([
                'success' => false,
                'message' => 'User belum login',
            ], 401);
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout success',
        ]);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout dari semua device berhasil',
        ]);
    }

    // PROFILE
    public function me(Request $request)
    {
        $user = $request->user();

        $resource = $user instanceof Traveler
            ? new TravelerResource($user)
            : new UserResource($user);

        return response()->json([
            'success' => true,
            'data'    => $resource,
        ]);
    }

    // REFRESH TOKEN
    public function refreshToken(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $hashed = hash('sha256', $request->refresh_token);

        $token = PersonalAccessToken::where('token', $hashed)
            ->where('is_refresh', true)
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token tidak valid',
            ], 401);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();
            return response()->json([
                'success' => false,
                'message' => 'Refresh token expired',
            ], 401);
        }

        $user = $token->tokenable;

        $token->delete();

        $accessToken     = $user->createAccessToken('api', ['*']);
        $newRefreshToken = $user->createRefreshToken('api');

        return response()->json([
            'success'       => true,
            'access_token'  => $accessToken->token,
            'refresh_token' => $newRefreshToken->token,
        ]);
    }

    // HELPER
    private function getLocationFromIp(string $ip): string
    {
        try {
            $location = \Stevebauman\Location\Facades\Location::get($ip);
            return $location
                ? "{$location->cityName}, {$location->regionName}, {$location->countryName}"
                : 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}