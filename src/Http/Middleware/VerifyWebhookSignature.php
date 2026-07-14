<?php

namespace Veekthoven\CashierBachs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyWebhookSignature
{
    /**
     * Verify the incoming request was signed by Bachs.
     *
     * Bachs signs every delivery with an HMAC-SHA256 hex digest of
     * "{timestamp}.{raw_body}" using the endpoint's signing secret.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next)
    {
        $secret = config('cashier.webhook.secret');

        if (empty($secret)) {
            throw new AccessDeniedHttpException('The Bachs webhook signing secret has not been configured.');
        }

        $timestamp = $request->header('X-Bachs-Timestamp');
        $signature = $request->header('X-Bachs-Signature');

        if (empty($timestamp) || empty($signature)) {
            throw new AccessDeniedHttpException('Missing Bachs webhook signature headers.');
        }

        $tolerance = (int) config('cashier.webhook.tolerance', 300);

        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            throw new AccessDeniedHttpException('Bachs webhook delivery is outside of the allowed tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid Bachs webhook signature.');
        }

        return $next($request);
    }
}
