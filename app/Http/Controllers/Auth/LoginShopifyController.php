<?php

namespace App\Http\Controllers\Auth;

use Socialite;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use App\UserProvider;
use App\Store;
use Carbon\Carbon;

class LoginShopifyController extends Controller
{
    public function index() 
    {
        return view('auth.login_shopify');
    }

    /**
     * Redirect the user to Shopify for authentication.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider(Request $request)
    {
        if (is_null($request->get('shop')))
        {
            $this->validate($request, [
                'domain' => 'string|required'
            ]);
            $domain = $request->get('domain');
        }
        else
            $domain = rtrim($request->get('shop'), '.myshopify.com');

        if (env('SINGLE_STORE', false))
        {
            if ($domain != env('AUTHORIZED_STORE'))
                abort(401, 'Unauthorized store');
        }

        $config = new \SocialiteProviders\Manager\Config(
            env('SHOPIFY_KEY'),
            env('SHOPIFY_SECRET'),
            env('SHOPIFY_REDIRECT_URI'),
            ['subdomain' => $domain]
        );

        return Socialite::with('shopify')
            ->setConfig($config)
            ->scopes(['read_orders', 'write_orders', 'read_customers', 'write_customers', 'read_products', 'write_products'])
            ->redirect();
    }

    /**
     * Handle the callback from Shopify with user and store information.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback(Request $request)
    {
        // \Log::debug('##START LoginShopifyController handleProviderCallback##');
        $shopifyUser = Socialite::driver('shopify')->user();
        // Create user
        $user = User::firstOrCreate([
            'name' => $shopifyUser->name,
            'email' => $shopifyUser->email,
            'password' => '',
        ]);
        
        if (!$user->hasRole('admin'))
            $user->attachRole('admin');

        //check for existing UserProvider, update token if exists
        $userProvider = UserProvider::where('user_id', $user->id)
            ->where('provider', 'shopify')
            ->where('provider_user_id', $shopifyUser->id)
            ->first();

        if ($userProvider != null) 
        {
            //refresh token here?
            if ($userProvider->provider_token != $shopifyUser->token)
            {
                $userProvider->provider_token = $shopifyUser->token;
                $userProvider->save();
            }
        }
        else 
        {
            // Store the OAuth Identity
            UserProvider::create([
                'user_id' => $user->id,
                'provider' => 'shopify',
                'provider_user_id' => $shopifyUser->id,
                'provider_token' => $shopifyUser->token,
            ]);
        }
        
        // Create store
        $store = Store::firstOrCreate([
            'name' => $shopifyUser->name,
            'domain' => $shopifyUser->nickname,
        ]);

        if ($store->uninstalled)
        {
            $store->reinstall_dt = Carbon::now();
            $store->uninstalled = false;
            $store->uninstall_dt = null;
            $store->save();
        }

        // Attach store to user
        $store->users()->syncWithoutDetaching([$user->id]);

        \Auth::login($user, true);
        // Setup app uninstall webhook
        dispatch(new \App\Jobs\RegisterUninstallShopifyWebhook($store->domain, $shopifyUser->token, $store));
        // Setup order paid webhook
        //dispatch(new \App\Jobs\RegisterOrderPaidShopifyWebhook($store->domain, $shopifyUser->token, $store));
        // Setup ReCharge webhooks
        // dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'subscription/created', env('APP_URL') . 'webhook/recharge/subscription_created'));
        // dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'subscription/updated', env('APP_URL') . 'webhook/recharge/subscription_updated'));
        // dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'order/created', env('APP_URL') . 'webhook/recharge/order_created'));
        // dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'order/processed', env('APP_URL') . 'webhook/recharge/order_proceseed'));

        return redirect()->route('shopify.store', ['storeId' => $store->id]);
    }
}