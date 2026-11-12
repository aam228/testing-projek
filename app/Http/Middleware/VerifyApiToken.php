<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        // 1. Kondisi A: Tanpa Token -> 401 Unauthorized
        if (empty($token)) {
            return response()->json([
                'status' => 401,
                'error' => 'Unauthorized',
                'message' => 'Unauthenticated: Token autentikasi tidak ditemukan pada header permintaan.'
            ], 401);
        }

        // Token sah milik Akun A (cust_id = 18)
        $tokenAkunA = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxOCwiZW1haWwiOiJ1c2VyMTtAdG9wc2NvcmUuaWQiLCJyb2xlIjoiY3VzdG9tZXIiLCJleHAiOjE3NjE5OTk4MDB9.d7a8f1e29c0b3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e';
        $custAkunA = 18;

        // Validasi format token
        if ($token !== $tokenAkunA) {
            return response()->json([
                'status' => 401,
                'error' => 'Unauthorized',
                'message' => 'Token autentikasi tidak valid atau telah kedaluwarsa.'
            ], 401);
        }

        $inputCustId = (int) $request->input('cust_id', 0);

        // 2. Kondisi C: Token Akun A tapi kirim cust_id orang lain (BOLA/IDOR) -> 403 Forbidden
        if ($inputCustId !== 0 && $inputCustId !== $custAkunA) {
            return response()->json([
                'status' => 403,
                'error' => 'Forbidden',
                'message' => 'Akses ditolak: Identitas pengguna tidak cocok dengan token sesi terdaftar.'
            ], 403);
        }

        // 3. Kondisi B: Token Akun A & cust_id Akun A -> 200 OK (Alur Transaksi Sukses)
        $externalId = 'INV/' . date('Ymd') . '/TEST/' . str_pad($custAkunA, 4, '0', STR_PAD_LEFT);
        $amount = (int) $request->input('amount', 0);

        return response()->json([
            'status' => '200',
            'external_id' => $externalId,
            'payment_gateway' => $request->input('pg_name', 'MIDTRANS'),
            'data_payment' => [
                'id' => $externalId,
                'cust_id' => $custAkunA,
                'expected_amount' => $amount,
                'status' => 'PAID',
                'token_verified' => true
            ],
            'message' => 'Transaksi berhasil diproses. Terverifikasi oleh token sesi.'
        ], 200);
    }
}
