<?php

namespace App\Services;

use App\Mail\PasswordChanged;
use App\Mail\PasswordRecoveryCode;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class PasswordRecovery
{
    /** @return array{user: User, reenvio: bool}|null */
    public function sendCode(string $email): ?array
    {
        $delivery = DB::transaction(function () use ($email): ?array {
            $user = User::where('email', $email)->where('activo', true)->lockForUpdate()->first();

            if (! $user) {
                return null;
            }

            $existing = DB::table('password_reset_tokens')->where('email', $user->email)->first();

            if ($existing && Carbon::parse($existing->created_at)->gt(now()->subMinute())) {
                return null;
            }

            do {
                $code = sprintf('%06d', random_int(0, 999999));
            } while ($existing && ! $existing->verified && Hash::check($code, $existing->token));
            $hash = Hash::make($code);
            DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], [
                'token' => $hash,
                'created_at' => now(),
                'attempts' => 0,
                'verified' => false,
            ]);

            return [
                'email' => $user->email,
                'code' => $code,
                'hash' => $hash,
                'user' => $user,
                'reenvio' => $existing !== null,
            ];
        });

        if ($delivery === null) {
            return null;
        }

        try {
            Mail::to($delivery['email'])->send(new PasswordRecoveryCode($delivery['code']));
        } catch (Throwable $exception) {
            DB::table('password_reset_tokens')->where('email', $delivery['email'])
                ->where('token', $delivery['hash'])->delete();
            report($exception);

            return null;
        }

        return ['user' => $delivery['user'], 'reenvio' => $delivery['reenvio']];
    }

    public function verifyCode(string $email, string $code): ?string
    {
        return DB::transaction(function () use ($email, $code): ?string {
            $user = User::where('email', $email)->where('activo', true)->lockForUpdate()->first();

            if (! $user) {
                return null;
            }

            $query = DB::table('password_reset_tokens')->where('email', $user->email);
            $record = $query->lockForUpdate()->first();

            if (! $record || $record->verified || $record->attempts >= 5
                || Carbon::parse($record->created_at)->lte(now()->subMinutes(10))) {
                return null;
            }

            if (! Hash::check($code, $record->token)) {
                $query->increment('attempts');

                return null;
            }

            $grant = Str::random(64);
            $query->update([
                'token' => Hash::make($grant),
                'verified' => true,
                'created_at' => now(),
            ]);

            return $grant;
        });
    }

    public function resetPassword(string $email, string $grant, string $password): ?User
    {
        $user = DB::transaction(function () use ($email, $grant, $password): ?User {
            $user = User::where('email', $email)->where('activo', true)->lockForUpdate()->first();

            if (! $user) {
                return null;
            }

            $query = DB::table('password_reset_tokens')->where('email', $user->email);
            $record = $query->lockForUpdate()->first();

            if (! $record || ! $record->verified
                || Carbon::parse($record->created_at)->lte(now()->subMinutes(10))
                || ! Hash::check($grant, $record->token)) {
                return null;
            }

            $user->password = $password;
            $user->setRememberToken(Str::random(60));
            $user->save();
            $query->delete();

            return $user;
        });

        if ($user === null) {
            return null;
        }

        event(new PasswordReset($user));

        try {
            Mail::to($user->email)->send(new PasswordChanged(now()->format('d/m/Y H:i').' ('.config('app.timezone').')'));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $user;
    }
}
