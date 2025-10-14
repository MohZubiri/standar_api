<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGatewaySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $sharedSecret = (string) config('biller.shared_secret');
        $allowedSkewSeconds = (int) config('biller.allowed_clock_skew', 300);

        if ($sharedSecret === '') {
            return response()->json(['message' => 'Gateway security misconfigured'], 500);
        }

        $timestamp = $request->header('X-Signature-Timestamp');
        $nonce = $request->header('X-Signature-Nonce');
        $signature = $request->header('X-Signature');

        if (!$timestamp || !$nonce || !$signature) {
            return response()->json(['message' => 'Missing signature headers'], 401);
        }

        // Replay protection: timestamp skew and nonce cache
        $now = time();
        if (abs($now - (int) $timestamp) > $allowedSkewSeconds) {
            return response()->json(['message' => 'Signature timestamp out of range'], 401);
        }

        $cacheKey = 'gw_nonce:' . $nonce;
        if (\Cache::has($cacheKey)) {
            return response()->json(['message' => 'Replay detected'], 401);
        }

        // Build canonical string
        $method = strtoupper($request->getMethod());
        $path = '/' . ltrim($request->getPathInfo(), '/');
        $body = $request->getContent() ?: '';
        $contentHash = base64_encode(hash('sha256', $body, true));
        $canonical = implode("\n", [
            $method,
            $path,
            $contentHash,
            (string) $timestamp,
            (string) $nonce,
        ]);

        $computed = base64_encode(hash_hmac('sha256', $canonical, $sharedSecret, true));

        if (!hash_equals($computed, (string) $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Store nonce for the skew window
        \Cache::put($cacheKey, 1, $allowedSkewSeconds);

        return $next($request);
    }
}


