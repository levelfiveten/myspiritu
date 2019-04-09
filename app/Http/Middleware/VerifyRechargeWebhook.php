<?php

namespace App\Http\Middleware;

use Closure;

class VerifyRechargeWebhook
{
    /**
     * Verify the ReCharge webhook request by calculating the digital signature 
     * https://developer.rechargepayments.com/?php#webhook-validation
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function handle($request, Closure $next)
    {   
        $client_secret = env('RECHARGE_CLIENT_SECRET');
        $request_body = request()->getContent();
        $calculated_digest = hash('sha256', $client_secret . $request_body);
        $received_digest = request()->header('X-Recharge-Hmac-Sha256');

        // \Log::info("\n".$received_digest. "   RECEIVED\n");
        // \Log::info($calculated_digest. "   CALCULATED\n");

        if (!hash_equals($received_digest,$calculated_digest))
        {
            \Log::error("ReCharge webhook verification failed!");
            abort(401, 'Invalid ReCharge webhook signature');
        }

        return $next($request);
    }
}