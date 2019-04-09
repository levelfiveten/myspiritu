<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use \GuzzleHttp\Client;
use App\Http\Controllers\SubscriptionTraits;
use App\Store;
use App\User;

class UpdateSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SubscriptionTraits;

    /**
    * @var string
    */
    public $subscription;
    public $client;

    /**
    * Create a new job instance.
    *
    * @return void
    */
    public function __construct(Object $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
    * Execute the job.
    *
    * @return void
    */
    public function handle()
    {
        \Log::debug("ENTER processing UpdateSubscription job for subscription id " . $this->subscription->id);
        $this->client = new Client([
            'headers' => [
                'content-type'              => 'application/json',
                'x-recharge-access-token'   => env('RECHARGE_API_KEY')
            ]
        ]);
        // $shopifyClient = $this->getShopifyClient();
        $lastChargedOrder = $this->getOrdersBySubscription($this->subscription->id, 'SUCCESS')->first();
        $this->updateSubscriptionNextCharge($this->subscription, $lastChargedOrder);
        $this->shiftQueuedOrdersToSeasonShipDates($this->subscription, $lastChargedOrder);
        \Log::debug("EXIT processing UpdateSubscription job for subscription id " . $this->subscription->id);
    }
}
