<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class TwoFactorToken
{
    public static function generate(int $userId): string
    {
        $payload = json_encode([
            'user_id' => $userId,
            'exp' => now()->addMinutes(5)->timestamp,
            'nonce' => bin2hex(random_bytes(16)),
        ]);

        return '2fa_' . bin2hex(Crypt::encryptString($payload));
    }

    public static function validate(string $token): ?int
    {
        if (!str_starts_with($token, '2fa_')) {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString(hex2bin(substr($token, 4)));
            $payload = json_decode($decrypted, true);

            if (!$payload || !isset($payload['user_id']) || !isset($payload['exp'])) {
                return null;
            }

            if ($payload['exp'] < now()->timestamp) {
                return null;
            }

            $tokenHash = sha1($token);
            if (Cache::has('2fa_token_used_' . $tokenHash)) {
                return null;
            }

            $remainingSeconds = max(1, $payload['exp'] - now()->timestamp);
            Cache::put('2fa_token_used_' . $tokenHash, true, $remainingSeconds);

            return (int) $payload['user_id'];
        } catch (\Exception $e) {
            return null;
        }
    }
}
