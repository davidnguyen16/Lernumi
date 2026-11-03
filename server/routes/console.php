<?php

use App\Models\User;
use App\Rules\PasswordByteLength;
use App\Services\AuthSessionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('lernumi:create-admin {email?} {--name=Administrator}', function (): int {
    $email = strtolower(trim($this->argument('email') ?: $this->ask('Administrator email')));
    $password = $this->secret('New password (at least 12 characters, letters and numbers)');
    $data = ['email' => $email, 'password' => $password, 'name' => $this->option('name')];
    $validator = Validator::make($data, ['email' => ['required', 'email:rfc', 'max:254'], 'name' => ['required', 'string', 'max:200'], 'password' => ['required', 'string', Password::min(12)->letters()->numbers(), new PasswordByteLength]]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) $this->error($error);
        return 1;
    }
    DB::transaction(function () use ($email, $password, $data): void {
        $user = User::query()->where('email', $email)->lockForUpdate()->first() ?? new User(['email' => $email]);
        $user->forceFill(['name' => $data['name'], 'password' => $password, 'is_admin' => true, 'is_active' => true, 'email_verified_at' => now(), 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        app(AuthSessionService::class)->revokeRuntime($user);
    });
    $this->info('Administrator created or updated.');
    return 0;
})->purpose('Create the initial administrator without storing a password in shell history');
