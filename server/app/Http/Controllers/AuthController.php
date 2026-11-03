<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ProfileRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AuthSessionService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function __construct(private readonly AccessService $access, private readonly AuthSessionService $sessions) {}

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $user = User::create(['name' => $data['name'], 'first_name' => $data['firstName'] ?? null, 'last_name' => $data['lastName'] ?? null, 'email' => $data['email'], 'password' => $data['password']]);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        event(new \Illuminate\Auth\Events\Registered($user));
        return response()->json(['data' => $this->sessionData($user, $request)], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }
        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        return response()->json(['data' => $this->sessionData($user, $request)]);
    }

    public function session(Request $request)
    {
        return response()->json(['data' => $this->sessionData($request->user(), $request)]);
    }

    public function logout(Request $request) { $this->sessions->logout($request); return response()->noContent(); }
    public function logoutAll(Request $request) { $this->sessions->logout($request, true); return response()->noContent(); }
    public function profile(Request $request) { return new UserResource($request->user()); }

    public function updateProfile(ProfileRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        if (isset($data['email']) && $data['email'] !== $user->email && ! Hash::check($data['currentPassword'] ?? '', $user->password)) throw ValidationException::withMessages(['currentPassword' => 'Your current password is required to change email.']);
        $attributes = [];
        foreach (['name' => 'name', 'firstName' => 'first_name', 'lastName' => 'last_name', 'email' => 'email'] as $key => $column) if (array_key_exists($key, $data)) $attributes[$column] = $data[$key];
        if (! isset($attributes['name']) && (array_key_exists('first_name', $attributes) || array_key_exists('last_name', $attributes))) {
            $name = trim(($attributes['first_name'] ?? $user->first_name ?? '').' '.($attributes['last_name'] ?? $user->last_name ?? ''));
            if ($name !== '') $attributes['name'] = $name;
        }
        if (isset($attributes['email']) && $attributes['email'] !== $user->email) $attributes['email_verified_at'] = null;
        $user->forceFill($attributes)->save();
        return new UserResource($user);
    }

    public function password(Request $request)
    {
        if ($request->has('passwordConfirmation')) $request->merge(['password_confirmation' => $request->input('passwordConfirmation')]);
        $data = $request->validate(['currentPassword' => ['required', 'string', 'max:128'], 'password' => ['required', 'confirmed', 'string', Password::min(10)->letters()->numbers(), new \App\Rules\PasswordByteLength]]);
        if (! Hash::check($data['currentPassword'], $request->user()->password)) throw ValidationException::withMessages(['currentPassword' => 'The current password is incorrect.']);
        $request->user()->forceFill(['password' => $data['password']])->save();
        $this->sessions->logout($request, true);
        return response()->noContent();
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        PasswordBroker::sendResetLink(['email' => strtolower(trim($data['email']))]);
        // Do not expose whether an account exists.
        return response()->json(['message' => 'If the account exists, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        if ($request->has('passwordConfirmation')) $request->merge(['password_confirmation' => $request->input('passwordConfirmation')]);
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'token' => ['required', 'string'], 'password' => ['required', 'confirmed', 'string', Password::min(10)->letters()->numbers(), new \App\Rules\PasswordByteLength]]);
        $data['email'] = strtolower(trim($data['email']));
        $status = PasswordBroker::reset($data + ['password_confirmation' => $request->input('password_confirmation')], function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $this->sessions->revokeRuntime($user);
            event(new PasswordReset($user));
        });
        if ($status !== PasswordBroker::PASSWORD_RESET) throw ValidationException::withMessages(['email' => __($status)]);
        return response()->json(['message' => 'Password reset. You may sign in.']);
    }

    public function avatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $old = $request->user()->avatar_key;
        $key = $request->file('avatar')->store('avatars', 'local');
        try { $request->user()->forceFill(['avatar_key' => $key])->save(); }
        catch (\Throwable $error) { Storage::disk('local')->delete($key); throw $error; }
        if ($old) Storage::disk('local')->delete($old);
        return new UserResource($request->user());
    }

    public function deleteAvatar(Request $request)
    {
        $old = $request->user()->avatar_key;
        $request->user()->forceFill(['avatar_key' => null])->save();
        if ($old) Storage::disk('local')->delete($old);
        return response()->noContent();
    }

    public function showAvatar(User $user)
    {
        abort_unless($user->avatar_key && Storage::disk('local')->exists($user->avatar_key), 404);
        return response()->file(Storage::disk('local')->path($user->avatar_key), ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function sessions(Request $request)
    {
        $current = $request->hasSession() ? $request->session()->getId() : null;
        return response()->json(['data' => DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])->map(fn ($session) => ['id' => $session->id, 'ipAddress' => $session->ip_address, 'userAgent' => $session->user_agent, 'lastActivity' => $session->last_activity, 'current' => $session->id === $current])]);
    }

    public function verifyEmail(\Illuminate\Foundation\Auth\EmailVerificationRequest $request)
    {
        $request->fulfill();
        return response()->json(['message' => 'Email verified.']);
    }

    public function resendVerification(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'If verification is needed, a verification link has been sent.']);
    }

    public function tokens(Request $request)
    {
        return response()->json(['data' => $request->user()->tokens()->orderByDesc('created_at')->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])->map(fn ($token) => ['id' => $token->id, 'name' => $token->name, 'lastUsedAt' => $token->last_used_at?->toISOString(), 'expiresAt' => $token->expires_at?->toISOString(), 'createdAt' => $token->created_at?->toISOString()])]);
    }

    public function createToken(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'currentPassword' => ['required', 'string', 'max:128'], 'expiresAt' => ['sometimes', 'date', 'after:now', 'before:+1 year']]);
        if (! Hash::check($data['currentPassword'], $request->user()->password)) throw ValidationException::withMessages(['currentPassword' => 'The current password is incorrect.']);
        $token = $request->user()->createToken($data['name'], ['*'], isset($data['expiresAt']) ? \Illuminate\Support\Carbon::parse($data['expiresAt']) : now()->addDays(30));
        return response()->json(['data' => ['id' => $token->accessToken->id, 'token' => $token->plainTextToken, 'expiresAt' => $token->accessToken->expires_at->toISOString()]], 201);
    }

    public function deleteToken(Request $request, string $token)
    {
        $record = $request->user()->tokens()->findOrFail($token);
        $record->delete();
        // Runtime sessions launched with PATs are revoked together because they have no browser-session id.
        if (\Illuminate\Support\Facades\Schema::hasTable('tool_runtime_sessions')) DB::table('tool_runtime_sessions')->where('user_id', $request->user()->id)->whereNull('login_session_id')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        return response()->noContent();
    }

    public function revokeSession(Request $request, string $session)
    {
        abort_unless(DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $session)->exists(), 404);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $session)->delete();
        $this->sessions->revokeRuntime($request->user(), $session);
        if ($request->hasSession() && $request->session()->getId() === $session) $this->sessions->logout($request);
        return response()->noContent();
    }

    private function sessionData(User $user, Request $request): array
    {
        return ['user' => (new UserResource($user))->resolve($request), 'contexts' => $this->access->contexts($user), 'isAdmin' => $this->access->isAdmin($user)];
    }
}
