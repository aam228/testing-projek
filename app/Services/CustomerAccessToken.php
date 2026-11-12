<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CustomerAccessToken
{
    public const LIFETIME = 900;
    private const ALGORITHM = 'HS256';
    private const AUDIENCE = 'topscore-orders';

    public function issue(int $customerId): string
    {
        $now = time();

        return JWT::encode([
            'iss' => config('app.url'),
            'aud' => self::AUDIENCE,
            'user_id' => $customerId,
            'iat' => $now,
            'exp' => $now + self::LIFETIME,
        ], $this->secret(), self::ALGORITHM);
    }

    public function customerId(string $token): ?int
    {
        $key = new Key($this->secret(), self::ALGORITHM);

        try {
            $claims = JWT::decode($token, $key);
        } catch (\UnexpectedValueException | \DomainException | \TypeError $e) {
            return null;
        }

        $customerId = $claims->user_id ?? null;
        if (($claims->iss ?? null) !== config('app.url')
            || ($claims->aud ?? null) !== self::AUDIENCE
            || !is_int($claims->exp ?? null)
            || !is_int($customerId)
            || $customerId < 1) {
            return null;
        }

        return $customerId;
    }

    private function secret(): string
    {
        $encoded = config('jwt.secret');
        $secret = is_string($encoded) ? base64_decode($encoded, true) : false;

        if ($secret === false || strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must contain at least 32 random bytes encoded in base64.');
        }

        return $secret;
    }
}
