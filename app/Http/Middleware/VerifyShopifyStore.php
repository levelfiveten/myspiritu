<?php

namespace App\Http\Middleware;

use Closure;
use App\Store;

class VerifyShopifyStore
{
    /**
     * Verify the Shopify webhook request by calculating the digital signature
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {    
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST']))
            $fwdHost = $_SERVER['HTTP_X_FORWARDED_HOST'];
        else
        {
            if (!isset($_SERVER['HTTP_REFERER']))
                abort(401, 'Unauthorized');
            else
                $fwdHost = \App\Helpers\Helper::getBaseUrl($_SERVER['HTTP_REFERER']);
        }

        if (is_null($fwdHost))
            abort(401, 'Unauthorized');

        $store = Store::where('domain', $fwdHost)->orWhere('domain_alias', $fwdHost)->first();
        if (!$store)
            abort(401, 'Unauthorized shopify store');

        if ($store->domain != env('AUTHORIZED_STORE'))
            abort(401, 'Unauthorized. Shopify store is not whitelisted');

        return $next($request);
    }
}
