<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Password;

use App\Store;
use Carbon\Carbon;
use App\User;
use App\UserProvider;
use App\BillingSchedule;
use \GuzzleHttp\Client;

class StoreController extends Controller
{
    use StoreTraits;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $stores = auth()->user()->stores;

        return redirect()->route('shopify.store', ['storeId' => $stores->first()->id]);
    }

    public function shopifyStore(Request $request, $storeId)
    {
        /* use this in ReCharge webhook callbacks where digital signature is verified but no store context exists */
        // $store = Store::where('domain', env('AUTHORIZED_STORE_FULL'))->first();
        // $user = User::whereHas('stores', function($q) use ($store) {
        //     $q->where('store_id', $store->id);
        // })->first();
        // $userProvider = $user->providers->where('provider', 'shopify')->first();
        // try {
        //     $shopify = \Shopify::retrieve($store->domain, $userProvider->provider_token);
        // }
        // catch (\Exception $e) {
        //     \Log::error('An error was encountered while communicating with Shopify for the given domain and provider_token:' . $e->getMessage());
        // }
        
        $user = auth()->user();
        $store = $user->stores->where('id', $storeId)->first();
        $userProvider = auth()->user()->providers->where('provider', 'shopify')->first();
        
        try {
            $shopify = \Shopify::retrieve($store->domain, $userProvider->provider_token);
        }
        catch (\Exception $e) {
            \Log::error('An error was encountered while communicating with Shopify for the given domain and provider_token:' . $e->getMessage());

            Auth::logout();

            return redirect()->route('login.shopify');
        }

        $schedules = BillingSchedule::all();
        $rechargeWebhooks = $this->listWebhooks();
        $shopifyProducts = $shopify->get("products");
        $products = $shopifyProducts['products'];
        // $shopify->get('themes/'.$currentThemeId.'/assets', ['asset[key]' => 'sections/blog-template.liquid']);

        /*
        dd($shopify->get("products"));

        $productId = 1082847363119;
        $productVariantResponse = $shopify->get("products/$productId/variants");
        $variants = $productVariantResponse['variants'];
        
        $variantsTotalInventory = 0;
        foreach($variants as $variant)
            $variantsTotalInventory += $variant['inventory_quantity'];

        $variantInStock = null;
        foreach($variants as $variant) {
            if ($variant['inventory_quantity'] > 0) {
                $variantInStock = $variant;
                break;
            }
        }
        dd($variantInStock);
        */

        return view('store.admin', compact('user', 'store', 'schedules', 'rechargeWebhooks', 'products'));
    }

    public function registerRechargeWebhooks()
    {
        dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'subscription/created', env('APP_URL') . 'webhook/recharge/subscription_created'));
        // dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'subscription/updated', env('APP_URL') . 'webhook/recharge/subscription_updated'));
        dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'charge/paid', env('APP_URL') . 'webhook/recharge/charge_paid'));
        dispatch(new \App\Jobs\RegisterRechargeWebhook(env('RECHARGE_API_KEY'), 'order/processed', env('APP_URL') . 'webhook/recharge/order_processed'));

        return redirect()->back()->with('status-success', 'ReCharge webhook registration is in progress.');
    }

    public function removeRechargeWebhooks()
    {
        dispatch(new \App\Jobs\RemoveRechargeWebhooks(env('RECHARGE_API_KEY')));

        return redirect()->back()->with('status-success', 'ReCharge webhooks are being removed.');
    }

    function listWebhooks()
    {
        $client = new Client([
            'headers' => [
                'content-type'              => 'application/json',
                'x-recharge-access-token'   => env('RECHARGE_API_KEY')
            ]
        ]);
        $webhooksResponse = $client->get(env('RECHARGE_WEBHOOKS_URL'));
        $webhookResponseObj = json_decode($webhooksResponse->getBody());
        $webhookCollection = collect($webhookResponseObj->webhooks);

        return $webhookCollection;
    }

    /**
     * Handle order paid webhook from shopify store.
     *
     * @return \Illuminate\Http\Response
     */
    public function orderPaid(Request $request)
    {
        \Log::info('BEGIN Order Paid shopify callback');
        $orderNumber = $request->get('order_number');
        $customer = $request->get('customer');
        \Log::info("Order #$orderNumber");
        \Log::info($customer);

        \Log::info('END Order Paid shopify callback');
        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    /**
     * Uninstall the app from the user's shopify store.
     *
     * @return \Illuminate\Http\Response
     */
    public function uninstall(Request $request)
    {
        \Log::info('BEGIN Uninstall shopify store callback');        
        $store = Store::where('domain', $request->get('domain'))->first();
        if (!$store->uninstalled && !is_null($store->reinstall_dt))
        {
            //store was reinstalled before uninstall callback fired, or uninstall callback retried from failure after store reinstalled
            if (is_null($store->uninstall_dt))
                return (new \Illuminate\Http\Response)->setStatusCode(200);
        }

        \Log::info('Uninstalling shopify store id ' . $store->id);
        $this->removeStoreData($store);

        /* For apps that use trial subscriptions */
        if (!is_null($store->subscription_plan_id))
            $this->handleTrialSubscriptionEnd($store);

        $this->markUserForLogout($store); 

        if (!empty(env('RECHARGE_API_KEY', null)))
            dispatch(new \App\Jobs\RemoveRechargeWebhooks(env('RECHARGE_API_KEY')));

        \Log::info('END Uninstall shopify store callback');
        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    public function customerRedact(Request $request)
    {
        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    public function storeRedact(Request $request)
    {
        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    public function customerData(Request $request)
    {
        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

}
