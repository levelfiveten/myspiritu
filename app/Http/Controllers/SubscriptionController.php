<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use Carbon\Carbon;
use \GuzzleHttp\Client;
use App\Jobs\UpdateSubscription;

use App\Store;
use App\User;
use App\UserProvider;
use App\BillingSchedule;
use App\BillingProduct;

class SubscriptionController extends Controller
{
    use SubscriptionTraits;

    private $client;

    public function __construct()
    {
        $this->middleware('webhook.recharge', ['except' => ['updateCurrentSubscriptions', 'updateSubscriptionByAddress']]);
        $this->client = new Client([
            'headers' => [
                'content-type'              => 'application/json',
                'x-recharge-access-token'   => env('RECHARGE_API_KEY')
            ]
        ]);
    }

    /*
    function convertToAnnualSubscription()
    {
        //##CODE TO CONVERT ANNUAL SUBSCRIPTIONS THAT WERE CREATED WITH A QUARTERLY RULESET##

        $client = $this->client;

        // PRODUCTION CLIENT 
        // $client = new Client([
        //     'headers' => [
        //         'content-type'              => 'application/json',
        //         'x-recharge-access-token'   => env('RECHARGE_KEY_PROD')
        //     ]
        // ]);
        
        $status = 'ACTIVE';

        $subCount = 1;
        $badAnnualSubs = [];
        $page = 1;
        while($subCount != 0) {
            $subscriptionsReponse = $client->get("https://api.rechargeapps.com/subscriptions?status=$status&limit=250&page=$page");
            $page++;
            $subscriptionsResponseObj = json_decode($subscriptionsReponse->getBody());        
            $subscriptions = $subscriptionsResponseObj->subscriptions;
            $subCount = count($subscriptions);
            foreach ($subscriptions as $subscription) {
                if (strpos($subscription->product_title, 'Annual') !== false && $subscription->charge_interval_frequency < 12) {
                    $badAnnualSubs[] = $subscription;
                }                
            }
        }
        // dd($badAnnualSubs);
        
        $client = $this->client;

        foreach($badAnnualSubs as $badAnnualSub) {
            // dd($badAnnualSub);
            \Log::debug("Converting annual subscription id $badAnnualSub->id from a charge interval of 3 to 12, requires cancelling and recreating");

            \Log::debug("Cancelling subscription id $badAnnualSub->id");
            $cancelResponse = $client->post("https://api.rechargeapps.com/subscriptions/$badAnnualSub->id/cancel", [
                'json' => [
                    'cancellation_reason'  => 'Changing from mistaken quarterly ruleset to new subscription with correct annual ruleset'
                ]
            ]);
            \Log::debug("Status: (Cancel Subscription)". $cancelResponse->getStatusCode());
            \Log::debug("Body: (Cancel Subscription)". $cancelResponse->getBody());

            \Log::debug("Creating new subscription (prior subscription id $badAnnualSub->id) with annual ruleset");

            // $shopify = $this->getShopifyClient();
            // dd($shopify->get('products'));

            $createResponse = $client->post("https://api.rechargeapps.com/subscriptions", [
                'json' => [
                    "address_id"                => $badAnnualSub->address_id,
                    "next_charge_scheduled_at"  => Carbon::now()->addSeconds(20)->toDateTimeString(),      
                    "product_title"             => $badAnnualSub->product_title,
                    "price"                     => 0,
                    "quantity"                  => $badAnnualSub->quantity,
                    "shopify_variant_id"        => $badAnnualSub->shopify_variant_id,   
                    'order_interval_frequency'  => '3',
                    'order_interval_unit'       => 'month',
                    'charge_interval_frequency' => '12',
                    'properties'                => $badAnnualSub->properties
                ]
            ]);

            \Log::debug("Status: (Create Subscription)". $createResponse->getStatusCode());
            \Log::debug("Body: (Create Subscription)". $createResponse->getBody());

            // break;
        }
    }
    */

    public function updateCurrentSubscriptions(Request $request)
    {
        $page = $request->get('page');
        $limit = $request->get('limit');
        if (is_null($page) || is_null($limit))
            return "Parameters 'page' and 'limit' are required";

        \Log::debug('ENTER updateCurrentSubscriptions');
        $subscriptions = $this->getActiveSubscriptions($page, $limit);
        // dd($subscriptions);
        $shopify = $this->getShopifyClient();
        foreach ($subscriptions as $subscription) {
            \Log::debug("Dispatching new UpdateSubscription job for subscription id $subscription->id");
            dispatch(new UpdateSubscription($subscription));
        }

        \Log::debug('EXIT updateCurrentSubscriptions');
        return 'complete';
    }

    public function updateSubscriptionByAddress(Request $request)
    {
        $addressId = $request->get('address_id');
        if (empty($addressId))
            return "Parameter 'address_id' required";

        \Log::debug('ENTER updateSubscriptionByAddress');
        $subscriptions = $this->getActiveSubscriptionsByAddressId($addressId);
        // dd($subscriptions);
        $shopify = $this->getShopifyClient();
        foreach ($subscriptions as $subscription) {
            \Log::debug("Dispatching new UpdateSubscription job for subscription id $subscription->id");
            dispatch(new UpdateSubscription($subscription));
        }

        \Log::debug('EXIT updateSubscriptionByAddress');
        return 'complete';
    }

    public function created(Request $request)
    {
        \Log::debug('ENTER subscription created webhook callback from ReCharge');
        $subscription = (object)$request->get('subscription');
        if (!isset($subscription->status)) {
            \Log::debug('Subscription created webhook callback processed but no status was set. Subscription object:');
            \Log::debug($subscription);
            \Log::debug('EXIT subscription created webhook callback from ReCharge');
            return (new \Illuminate\Http\Response)->setStatusCode(200);
        }

        if ($subscription->status == 'ACTIVE') {
            $subCreated = $subscription->created_at;
            // $subscriptionId = $subscription['id'];
            // $subAddressId = $subscription['address_id'];
            // $chargeIntervalFreq = $subscription['charge_interval_frequency'];
            // $subType = ($chargeIntervalFreq < 12) ? 'Quarterly' : 'Annual';

            $currentBillingSeason = $this->getBillingSeasonFromDateInclusive($subCreated);
            \Log::debug("Based on subscription created date $subCreated, the current billing season is:");
            \Log::debug($currentBillingSeason);

            dispatch(new UpdateSubscription($subscription));

            // $nextChargeDt = $this->getInitialChargeDt($currentBillingSeason, $chargeIntervalFreq);

            // \Log::debug("Setting new $subType subscription (id: $subscriptionId, address_id: $subAddressId) to a next charge date of $nextChargeDt");
            // $this->setSubscriptionNextChargeDt($subscriptionId, $nextChargeDt, $subType);

            // \Log::debug("Shifting queued orders to seasonal ship dates");
            // $this->shiftQueuedOrdersToSeasonShipDates($subscriptionId);
        }

        \Log::debug('EXIT subscription created webhook callback from ReCharge');
        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    public function updated(Request $request)
    {
        // \Log::debug('Subscription updated webhook callback from ReCharge');

        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    public function orderCreated(Request $request)
    {
        // \Log::debug('ENTER order/created webhook callback from ReCharge');
        /*
        $order = request()->get('order');
        \Log::debug('Request order:');
        \Log::debug($order);

        if ($order->charge_status == 'SUCCESS') {
            $shopify = $this->getShopifyClient();
            $billingSeason = $this->getBillingSeasonFromDateInclusive($order->created_at);
            $variantInStock = $this->getVariantInStock($shopify, $billingSeason->product_id);
            // $oldShopifyVariantId = $order->;
            $newShopifyVariantId = $variantInStock['id'];
            $response = $this->client->post("https://api.rechargeapps.com/orders/$order->id/update_shopify_variant/$oldShopifyVariantId", [
                'json' => [
                    'new_shopify_variant_id' => $newShopifyVariantId,
                    'shopify_variant_id' => $oldShopifyVariantId
                ]
            ]);   

            return (new \Illuminate\Http\Response)->setStatusCode(200);
        }

        // $orderCreatedDate = Carbon::parse($order->created_at);
        // $currentBillingSeason = BillingSchedule::where('start_dt', '<=', $orderCreatedDate)
        //     ->where('end_dt', '>=', $orderCreatedDate)
        //     ->first();

        \Log::debug('Found current billing season:');
        \Log::debug($currentBillingSeason);

        //DO WE NEED TO MODIFY THE SHIPPING DATE OF THE ORDER WHEN IT IS CREATED?
        //for instance, if an order comes in that is a 'pre-order', ie before the date Spiritu is actually shipping the box,
        //will we be responsible for handling the update of the order shipping date here?
        if (Carbon::parse($order->scheduled_at) < Carbon::parse($currentBillingSeason->ship_dt)) {
            $updateOrderShipDateResponse = $client->post("https://api.rechargeapps.com/orders/$order->id/change_date", [
                'scheduled_at' => $currentBillingSeason->ship_dt
            ]);
        }

        if ($order->type == 'RECURRING') {
            \Log::debug('Recurring Order');
            $nextSeasonChargeDt = $currentBillingSeason->nextSchedule->charge_dt;
            \Log::debug('Getting subscription for order');
            //update subscription's next charge date to next season's charge date
            $subscriptionReponse = $this->client->get('https://api.rechargeapps.com/subscriptions', [
                'address_id' => $order->address_id,
                'status' => 'ACTIVE'
            ]);
            \Log::debug('Got subscription, decoding response');
            $subscriptionsResponseObj = json_decode($subscriptionReponse->getBody());
            $subscriptions = collect($subscriptionsResponseObj);
            \Log::debug('Setting subscriptions for this address id to have the next charge date set to next season\'s charge date');
            foreach($subscriptions as $subscription) {
                $client->post("https://api.rechargeapps.com/subscriptions/$subscription->id/set_next_charge_date", [
                    'date' => $nextSeasonChargeDt
                ]);
            }
        }

        \Log::debug('EXIT order/created webhook callback from ReCharge');
        */
        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    /**
    * ReCharge webhook response: order/processed
    * This will trigger when the order is processed (when an order goes from status QUEUED to status SUCCESS). This will not trigger on checkout.
    * @return \Illuminate\Http\Response
    */
    public function orderProcessed(Request $request)
    {
        \Log::debug('ENTER order/processed webhook callback from ReCharge');
        $order = $request->get('order');
        
        $subscription = $this->getSubscriptionById($order['line_items'][0]['subscription_id']);
        // if (Carbon::parse($subscription->created_at)->toDateString() != Carbon::parse($order->created_at)->toDateString()) 
        // {
            dispatch(new UpdateSubscription($subscription));
            // $this->updateSubscriptionNextCharge($subscription, $order);
            // $this->shiftQueuedOrdersToSeasonShipDates($subId);
        // }
        
        \Log::debug('EXIT order/processed webhook callback from ReCharge');

        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

    /**
    * ReCharge webhook response: charge/paid
    * This will trigger when a charge is successfully processed, both manually via UI and automatic recurring charge. (but will not trigger on the checkout)
    * @return \Illuminate\Http\Response
    */
    public function chargePaid(Request $request)
    {
        \Log::debug('ENTER charge/paid webhook callback from ReCharge');
        $charge = $request->get('charge');
        // \Log::debug($charge);
        $subscription = $this->getSubscriptionById($charge['line_items'][0]['subscription_id']);
        // $order = $this->getOrdersByCharge($charge['id'], $status);
        // if (Carbon::parse($subscription->created_at)->toDateString() != Carbon::parse($order->created_at)->toDateString()) 
        // {
        dispatch(new UpdateSubscription($subscription));
            // $this->updateSubscriptionNextCharge($subscription, $order);
            // $this->shiftQueuedOrdersToSeasonShipDates($subId);
        // }
        
        \Log::debug('EXIT charge/paid webhook callback from ReCharge');

        return (new \Illuminate\Http\Response)->setStatusCode(200);
    }

}