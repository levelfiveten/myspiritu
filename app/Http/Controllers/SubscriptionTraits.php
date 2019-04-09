<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Store;
use Carbon\Carbon;
use App\User;
use App\UserProvider;
use App\BillingSchedule;
use \GuzzleHttp\Client;

trait SubscriptionTraits {

    function updateSubscriptionNextCharge($subscription, $lastChargedOrder)
    {
        $subType = ($subscription->charge_interval_frequency < 12) ? 'Quarterly' : 'Annual';
        $nextChargeDt = $this->getNextChargeDt($lastChargedOrder, $subscription->charge_interval_frequency, $subscription->id);
             
        //if (Carbon::parse($subscription->next_charge_scheduled_at)->toDateString() != Carbon::parse($nextChargeDt)->toDateString())
        $this->setSubscriptionNextChargeDt($subscription->id, $nextChargeDt, $subType);
        //else
            //\Log::debug("SKIPPING: setSubscriptionNextChargeDt (subscription id: $subscription->id). Subscription next charge scheduled_at $subscription->next_charge_scheduled_at same as $nextChargeDt (date, not datetime)");
    }

    function shiftQueuedOrdersToSeasonShipDates($subscription, $lastChargedOrder)
    {
        $shopify = $this->getShopifyClient();
        $subscriptionId = $subscription->id;
        $subType = ($subscription->charge_interval_frequency < 12) ? 'Quarterly' : 'Annual';
        //get the last successfully charged order
        $lastChargedOrder = $this->getOrdersBySubscription($subscriptionId, 'SUCCESS')->first(); 
        //current season is the context of the an Active subscription's last successful charge
        $currentBillingSeason = $this->getBillingSeasonFromDateInclusive($lastChargedOrder->scheduled_at); 

        if ($subType == 'Annual') { //shift all queued orders to their next respective season                
            $orders = $this->getOrdersBySubscription($subscriptionId, 'QUEUED'); //get all queued orders for the annual subscription
            if ($orders->count() > 0) {
                $orders = $orders->reverse(); //orders are listed in DESC order by default
                $nextChargeSeason = $currentBillingSeason;
                foreach ($orders as $order) {
                    $nextChargeSeason = $nextChargeSeason->nextSchedule; 
                    $variantInStock = null;
                    $i = 0;
                    while (is_null($variantInStock)) {
                        $variantInStock = $this->getVariantInStock($shopify, $nextChargeSeason, $subType);
                        //if the next season is entirely sold out, bump to the following season
                        if (is_null($variantInStock))
                            $nextChargeSeason = $nextChargeSeason->nextSchedule;

                        $i++;
                        if ($i > 4)
                            break;
                    }

                    if (!is_null($variantInStock)) {
                        $this->updateQueuedOrderVariant($order, $variantInStock['id'], $subType);
                        //generally this pushes it out one quarter futher since the first order in the sequence is charged immediately
                        //if (Carbon::parse($order->scheduled_at) != Carbon::parse($nextChargeSeason->ship_dt)) 
                        $this->setOrderToSeasonShipDt($order->id, $nextChargeSeason->ship_dt);  
                        //else
                        //\Log::debug("SKIPPING: Update queued order ship date (order id: $order->id). Seasonal ship date: $nextChargeSeason->ship_dt same as order scheduled_at: $order->scheduled_at");
                    }              
                }
            }
        }
        else {
            $nextChargeSeason = $currentBillingSeason;
            $nextChargeSeason = $nextChargeSeason->nextSchedule; 
            $variantInStock = null;
            $i = 0;
            while (is_null($variantInStock)) {
                $variantInStock = $this->getVariantInStock($shopify, $nextChargeSeason, $subType);
                //if the next season is entirely sold out, bump to the following season
                if (is_null($variantInStock))
                    $nextChargeSeason = $nextChargeSeason->nextSchedule;

                $i++;
                if ($i > 4)
                    break;
            }
            if (!is_null($variantInStock)) {
                if ($subscription->shopify_variant_id != $variantInStock['id'] || 
                    $subscription->variant_title != $variantInStock['title'] ||
                    $subscription->sku != $variantInStock['sku'])
                    $this->updateSubscriptionDetails($subscriptionId, $variantInStock); 
                else
                \Log::debug("SKIPPING: updateSubscriptionDetails (subscription id: $subscriptionId). Variant details unchanged.");

                //if (Carbon::parse($subscription->next_charge_scheduled_at)->toDateString() != Carbon::parse($nextChargeSeason->charge_dt)->toDateString())
                $this->setSubscriptionNextChargeDt($subscriptionId, $nextChargeSeason->charge_dt);
                //else
                    //\Log::debug("SKIPPING: setSubscriptionNextChargeDt (subscription id: $subscriptionId). Subscription next charge scheduled_at $subscription->next_charge_scheduled_at same as $nextChargeSeason->charge_dt (date, not datetime)");
            }
        }
    }

    function updateSubscriptionDetails($subscriptionId, $variantInStock)
    {
        \Log::debug("Updating subscription details (id: $subscriptionId) with new variant id " . $variantInStock['id']);
        $variantInStock['title'] = str_replace(' 2019', '', $variantInStock['title']);
        $variantInStock['title'] = str_replace(' 2020', '', $variantInStock['title']);
        $variantInStock['title'] = str_replace(' 2021', '', $variantInStock['title']);
        $response = $this->client->put("https://api.rechargeapps.com/subscriptions/$subscriptionId", [
            'json' => [
                'product_title'         => $variantInStock['title'],
                'variant_title'         => '',
                'shopify_variant_id'    => $variantInStock['id'],
                'sku'                   => $variantInStock['sku'],
                'sku_override'          => false
            ]
        ]);
        \Log::debug("Status (Order Variant Update Response): ". $response->getStatusCode());
        \Log::debug("Body (Order Variant Update Response): ". $response->getBody());
    }

    function updateAnnualSubscriptionDetails($subscriptionId, $nextChargeDt)
    {
        $shopify = $this->getShopifyClient();
        $nextChargeSeason = BillingSchedule::where('charge_dt', $nextChargeDt)->first();
        //orders won't be placed for the far future next annual charge, so this is just a placeholder detail value
        $variantInStock = $this->getVariantInStock($shopify, $nextChargeSeason);
        $this->updateSubscriptionDetails($subscriptionId, $variantInStock); 
    }

    function getVariantInStock($shopify, $billingSeason, $subType = 'Annual')
    {
        /* GET COUNT OF ACTIVE RECHARGE SUBSCRIPTIONS */
        // $subCount = $this->getActiveSubscriptionCount();

        /* GET PRODUCT VARIANT IN STOCK */
        $productVariantResponse = $shopify->get("products/$billingSeason->product_id/variants");
        $product = $shopify->get("products/$billingSeason->product_id");
        $variants = $productVariantResponse['variants'];

        $variantInStock = null;
        $arrayKeys = array_keys($variants);
        $lastVariantKey = end($arrayKeys);
        $i = 0;
        foreach($variants as $variant) {
            // if ($subCount <= $variant['inventory_quantity']) {
                $variantInStock = $variant;
                $variantInStock['title'] = $subType . ' ' .$product['product']['title'];
                break;
            // }
            /* ##Updating the shopify product based on inventory## --Reinstate this once we can test it more
            else if ($variant['inventory_quantity'] == $subCount) {
                $variantInStock = $variant;

                //when the count of subscriptions reaches the variant inventory quantity, do one of the following:
                // A) update shopify product to the next variant of the season's product
                // B) update shopify product to the first variant of the following season's product

                if ($i == $lastVariantKey) { 
                    //this is the last variant of the product, switch Shopify product to next season's product and set rollover for current season
                    $billingSeason->rollover_dt = Carbon::now();
                    $billingSeason->save();
                    $billingSeason = $billingSeason->nextSchedule;
                    $productVariantResponse = $shopify->get("products/$billingSeason->product_id/variants");
                    $updateVariant = $productVariantResponse['variants'][0];                    
                }
                else { // switch the Shopify product variant to the next available variant for this season
                    $updateVariant = $variants[$i + 1];
                }
                $this->updateShopifyProductWhenVariantChanges($updateVariant, $billingSeason->season_title);
                break;
            }
            $i++;
            */
        }

        return $variantInStock;
    }

    function updateShopifyProductWhenVariantChanges($variant, $seasonTitle)
    {
        $variantSku = $variant['sku'];
        $shopifyProduct = BillingProduct::first();
        $shopify = $this->getShopifyClient();

        $quarterlyProduct = $shopify->get('products/'.$shopifyProduct->quarterly_product_id);
        $quarterlyProduct = $quarterlyProduct['product'];
        $quarterlyVariant = $quarterlyProduct['variants'][0];
        if ($quarterlyVariant['sku'] == $variantSku) //early return if variant already matches
            return;

        $annualProduct = $shopify->get('products/'.$shopifyProduct->annual_product_id);
        $annualProduct = $annualProduct['product'];
        $quarterlyVariant['sku'] = $variantSku;        
        $annualVariant = $annualProduct['variants'][0];
        $annualVariant['sku'] = $variantSku;

        $shopify->modifyProducts($shopifyProduct->quarterly_product_id, [
            'product' => [
                'title' => 'Quarterly Subscription - ' . $seasonTitle,
                'variants' => [$quarterlyVariant]
            ]
        ]);

        $shopify->modifyProducts($shopifyProduct->annual_product_id, [
            'product' => [
                'title' => 'Annual Subscription - ' . $seasonTitle,
                'variants' => [$annualVariant]
            ]
        ]);
    }

    function updateQueuedOrderVariant($order, $newVariantId, $subType)
    {
        $oldShopifyVariantId = $order->line_items[0]->shopify_variant_id;
        // if ($oldShopifyVariantId == $newVariantId) {
        //     \Log::debug("SKIPPING: Update queued order variant (id: $oldShopifyVariantId). Same as current variant id $newVariantId");
        //     return;
        // }
        if ($oldShopifyVariantId != $newVariantId) {
            \Log::debug("Updating queued order variant (id: $oldShopifyVariantId) to new variant id $newVariantId");
            $response = $this->client->post("https://api.rechargeapps.com/orders/$order->id/update_shopify_variant/$oldShopifyVariantId", [
                'json' => [
                    'new_shopify_variant_id' => $newVariantId,
                    'shopify_variant_id' => $oldShopifyVariantId
                ]
            ]);
            \Log::debug("Status (Order Variant Update Response): ". $response->getStatusCode());
            \Log::debug("Body (Order Variant Update Response): ". $response->getBody());
        }
        else {
            $response = $this->client->get("https://api.rechargeapps.com/orders/$order->id");
        }

        $updatedVariantResponse = json_decode($response->getBody());
        $updateVariantLineItems = $updatedVariantResponse->order->line_items;
        if (strpos($updateVariantLineItems[0]->title, $subType) === false)
            $updateVariantLineItems[0]->title = $subType . ' ' . $updateVariantLineItems[0]->title;

        if ($subType == 'Annual')
            $updateVariantLineItems[0]->price = "0.00";

        $updateVariantLineItems[0]->variant_id = $updateVariantLineItems[0]->shopify_variant_id;
        $updateVariantLineItems[0]->product_id = $updateVariantLineItems[0]->shopify_product_id;
        $updateVariantLineItems[0]->variant_title = '';
        unset($updateVariantLineItems[0]->shopify_variant_id);
        unset($updateVariantLineItems[0]->shopify_product_id);
        unset($updateVariantLineItems[0]->images);
        $updateVariantLineItems[0]->title = str_replace('Annual Annual', 'Annual', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_replace('Annual Annual Annual', 'Annual', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_replace('Quarterly Quarterly', 'Quarterly', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_replace('Quarterly Quarterly Quarterly', 'Quarterly', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_replace(' 2019', '', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_replace(' 2020', '', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_replace(' 2021', '', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_ireplace(' First Box', '', $updateVariantLineItems[0]->title);
        $updateVariantLineItems[0]->title = str_ireplace(' Second Box', '', $updateVariantLineItems[0]->title);
        
        $updateVariantLineItems[0]->product_title = $updateVariantLineItems[0]->title;

        $response = $this->client->put("https://api.rechargeapps.com/orders/$order->id", [
            'json' => [
                'line_items' => $updateVariantLineItems
            ]
        ]);
        \Log::debug("Status (Order title Update Response): ". $response->getStatusCode());
        \Log::debug("Body (Order title Update Response): ". $response->getBody());
    }

    function setSubscriptionNextChargeDt($subscriptionId, $nextChargeDt, $subType = null)
    {
        \Log::debug("Updating subscription (id: $subscriptionId) to a next charge date of $nextChargeDt");
        $response = $this->client->post("https://api.rechargeapps.com/subscriptions/$subscriptionId/set_next_charge_date", [
            'json' => [
                'date' => $nextChargeDt
            ]
        ]);
        \Log::debug("Status (Subscription Update Response): ". $response->getStatusCode());
        \Log::debug("Body (Subscription Update Response): ". $response->getBody());

        if ($subType == 'Annual') 
                $this->updateAnnualSubscriptionDetails($subscriptionId, $nextChargeDt);
    }

    function setOrderToSeasonShipDt($orderId, $seasonShipDt)
    {
        \Log::debug("Changing order (id: $orderId) to a ship date of $seasonShipDt");
        $response = $this->client->post("https://api.rechargeapps.com/orders/$orderId/change_date", [
            'json' => [
                'scheduled_at' => $seasonShipDt
            ]
        ]);
        \Log::debug("Status (Order Change Ship Date Response): ". $response->getStatusCode());
        \Log::debug("Body (Order Change Ship Date Response) : ". $response->getBody());
    }

    

    function getBillingSeasonFromDateInclusive($date)
    {
        $currentBillingSeason = BillingSchedule::where('start_dt', '<=', $date)
            ->where('end_dt', '>=', $date)
            ->first();
            
        //## Reinstate when implementing the function updateShopifyProductWhenVariantChanges ##
        // if ($currentBillingSeason->rollover_dt != null)
        // {
        //     while ($currentBillingSeason->rollover_dt != null)
        //     {
        //         $currentBillingSeason = $currentBillingSeason->nextSchedule;
        //     }
        // }
        
        return $currentBillingSeason;
    }

    function getInitialChargeDt($currentBillingSeason, $chargeIntervalFreq)
    {
        if ($chargeIntervalFreq < 12) {
            //Quarterly: set next charge date to next season
            $subType = 'Quarterly';
            $nextChargeDt = $currentBillingSeason->nextSchedule->charge_dt;
        }
        else {
            //Annual: set next charge date to the 4th Season's charge date after the current season (intial subscription created)
            $subType = 'Annual';
            $nextChargeDt = $this->getChargeDtForNewAnnualSubscription($currentBillingSeason);
        }

        return $nextChargeDt;
    }

    function getNextChargeDt($order, $chargeIntervalFreq, $subscriptionId)
    {
        //  NOTE: $order passed in this function must be a successfully processed order. 
        //  Annual subscription:
        //      recalculate the annualcharge date based on the number of successful prior orders, and uses the context of the currently processed order
        //      as the 'current' season reference point. So if the currently processed order was Spring 2019 and this was the 3rd box the 
        //      customer was receiveing, then it would set a charge date for Fall 2019, since that would be when they get their 5th box.
        //  Quarterly subscription:
        //      Set the next scheduled charge date to the season following the current order's scheduled date

        if ($chargeIntervalFreq < 12) { //Quarterly Subscription
            $currentBillingSeason = $this->getBillingSeasonFromDateInclusive($order->scheduled_at);
            $nextChargeDt = $currentBillingSeason->nextSchedule->charge_dt;
        }
        else { //Annual Subscription
            $nextChargeDt = $this->getChargeDtBasedOnPriorOrderCount($order, $subscriptionId);
        }

        return $nextChargeDt;
    }

    function getChargeDtForNewAnnualSubscription($currentBillingSeason)
    {
        $nextChargeSeason = $currentBillingSeason;
        for ($i = 0; $i < 4; $i++)
            $nextChargeSeason = $nextChargeSeason->nextSchedule;
            
        $nextChargeDt = $nextChargeSeason->charge_dt;
        
        return $nextChargeDt;
    }

    function getChargeDtBasedOnPriorOrderCount($order, $subscriptionId)
    {
        //$order in this context should be when the order is processed (order/processed webhook callback --when an order goes from status QUEUED to status SUCCESS)
        //this will readjust the scheduled charge by using the currently processed order as a reference point, so that once
        //they have received their 4th box, the annual bill date will be the following season
        $orderBillingSeason = $this->getBillingSeasonFromDateInclusive($order->scheduled_at);
        $status = 'SUCCESS';
        $scheduledMax = Carbon::parse($order->scheduled_at)->subDay(); //exclude this processed order from the order count returned
        $ordersReponse = $this->client->get("https://api.rechargeapps.com/orders?subscription_id=$subscriptionId&status=$status&scheduled_at_max=$scheduledMax");
        $ordersReponseObj = json_decode($ordersReponse->getBody());
        $orders = collect($ordersReponseObj->orders);

        $nextChargeSeason = $orderBillingSeason;
        $nextSchedCount = 4 - ($orders->count() % 4);
        for ($i = 0; $i < $nextSchedCount; $i++)
            $nextChargeSeason = $nextChargeSeason->nextSchedule;

        $nextChargeDt = $nextChargeSeason->charge_dt;

        return $nextChargeDt;
    }

    function UpdateQueuedOrderShipDt($order)
    {
        $billingSeason = $this->getBillingSeasonFromDateInclusive($order->scheduled_at);
        // dd($billingSeason);
        if (Carbon::parse($order->scheduled_at) != Carbon::parse($billingSeason->ship_dt)) 
            $changeOrderShipDtResponse = $this->setOrderToSeasonShipDt($order->id, $billingSeason->ship_dt);
        else {
            \Log::debug("SKIPPING: Update queued order ship date (order id: $order->id). Seasonal ship date: $billingSeason->ship_dt same as order scheduled_at: $order->scheduled_at");
        }
    }

    function getShopifyClient()
    {
        /* use this in ReCharge webhook callbacks where digital signature is verified but no store context exists */
        $store = Store::where('domain', env('AUTHORIZED_STORE_FULL'))->first();
        $user = User::whereHas('stores', function($q) use ($store) {
            $q->where('store_id', $store->id);
        })->first();
        $userProvider = $user->providers->where('provider', 'shopify')->first();
        try {
            $shopify = \Shopify::retrieve($store->domain, $userProvider->provider_token);
            return $shopify;
        }
        catch (\Exception $e) {
            \Log::error('An error was encountered while communicating with Shopify for the given domain and provider_token:' . $e->getMessage());
            //What should happen if the shopify client can't be retrieved? Should we do a call and try to refresh the token here and then 
            //try to retreive the client again?
        }
    }

    function getActiveSubscriptionCount()
    {
        $subCountRequest = $this->client->get('https://api.rechargeapps.com/subscriptions/count');
        $subCountResponse = json_decode($subCountRequest->getBody());

        return $subCountResponse->count;
    }

    function getSubscriptionById($subscriptionId)
    {
        $subscriptionReponse = $this->client->get("https://api.rechargeapps.com/subscriptions/$subscriptionId");
        $subscriptionResponseObj = json_decode($subscriptionReponse->getBody());        
        $subscription = $subscriptionResponseObj->subscription;

        return $subscription;
    }

    function getActiveSubscriptionsByAddressId($addressId)
    {
        $status = 'ACTIVE';
        $subscriptionsReponse = $this->client->get("https://api.rechargeapps.com/subscriptions?address_id=$addressId&status=$status");
        $subscriptionsResponseObj = json_decode($subscriptionsReponse->getBody());        
        $subscriptions = $subscriptionsResponseObj->subscriptions;

        return $subscriptions;
    }

    function getActiveSubscriptions($page, $limit)
    {
        $status = 'ACTIVE';
        $subscriptionsReponse = $this->client->get("https://api.rechargeapps.com/subscriptions?status=$status&limit=$limit&page=$page");
        $subscriptionsResponseObj = json_decode($subscriptionsReponse->getBody());        
        $subscriptions = $subscriptionsResponseObj->subscriptions;

        return $subscriptions;
    }

    function getAllActiveSubscriptions()
    {
        $status = 'ACTIVE';
        $subCount = 1;
        $allActiveSubs = [];
        $page = 1;
        while($subCount != 0) {
            $subscriptionsReponse = $this->client->get("https://api.rechargeapps.com/subscriptions?status=$status&limit=250&page=$page");
            $page++;
            $subscriptionsResponseObj = json_decode($subscriptionsReponse->getBody());        
            $subscriptions = $subscriptionsResponseObj->subscriptions;
            $subCount = count($subscriptions);
            foreach ($subscriptions as $subscription) {
                    $allActiveSubs[] = $subscription;
            }
        }

        return $allActiveSubs;
    }

    function getOrdersBySubscription($subscriptionId, $status)
    {
        $ordersReponse = $this->client->get("https://api.rechargeapps.com/orders?subscription_id=$subscriptionId&status=$status");        
        $ordersReponseObj = json_decode($ordersReponse->getBody());
        $orders = collect($ordersReponseObj->orders);

        return $orders;
    }

    function getQueuedOrdersByAddress()
    {
        $addressId = 28805836;
        $status = 'QUEUED';
        $ordersReponse = $this->client->get("https://api.rechargeapps.com/orders?address_id=$addressId&status=$status");        
        $ordersReponseObj = json_decode($ordersReponse->getBody());
        $orders = collect($ordersReponseObj->orders);

        return $orders;
    }

    function getOrdersByCharge($chargeId, $status)
    {
        $ordersReponse = $this->client->get("https://api.rechargeapps.com/orders?charge_id=$chargeId&status=$status");        
        $ordersReponseObj = json_decode($ordersReponse->getBody());
        $orders = collect($ordersReponseObj->orders);

        return $orders;
    }

}