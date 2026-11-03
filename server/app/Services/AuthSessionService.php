<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthSessionService
{
    public function revokeRuntime(User $user, ?string $sessionId = null): void
    {
        if (! Schema::hasTable('tool_runtime_sessions')) return;
        DB::table('tool_runtime_sessions')->where('user_id', $user->id)
            ->when($sessionId !== null, fn ($q) => $q->where('login_session_id', $sessionId))
            ->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function logout(Request $request, bool $all = false): void
    {
        $user = $request->user();
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        if ($all) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $this->revokeRuntime($user);
            $user->forceFill(['remember_token' => null])->save();
        } else {
            if ($sessionId) $this->revokeRuntime($user, $sessionId);
            $token = $user->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
                if (Schema::hasTable('tool_runtime_sessions')) DB::table('tool_runtime_sessions')->where('user_id', $user->id)->whereNull('login_session_id')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
        }
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
