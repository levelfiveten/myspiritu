<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // $request->session()->keep(['status-error', 'status-success']);

        if (Auth::guard($guard)->check()) {
            $user = auth()->user();
            if ($user->hasRole('Admin'))
            {
                $domain = $request->get('shop');
                if (!empty($domain))
                {
                    $store = $user->stores()->first();
                    if ($store->domain != $domain)
                    {
                        Auth::logout();
                        return redirect()->route('login.shopify', ['shop' => $domain]);
                    }
                }
                
                return redirect('/store');
            }
            else
                return redirect('/');
        }
        

        return $next($request);
    }
}
