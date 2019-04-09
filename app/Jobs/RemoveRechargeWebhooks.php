<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use \GuzzleHttp\Client;

class RemoveRechargeWebhooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
    * @var string
    */
    public $token;

    /**
    * Create a new job instance.
    *
    * @return void
    */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
    * Execute the job.
    *
    * @return void
    */
    public function handle()
    {
        $client = new Client([
            'headers' => [
                'content-type'              => 'application/json',
                'x-recharge-access-token'   => $this->token
            ]
        ]);
        $webhooksResponse = $client->get(env('RECHARGE_WEBHOOKS_URL'));
        $webhookResponseObj = json_decode($webhooksResponse->getBody());
        $webhookCollection = collect($webhookResponseObj->webhooks);

        //remove webhooks found in the webhooks list
        if (!$webhookCollection->isEmpty()) 
        {
            foreach ($webhookCollection as $webhook)
            {
                \Log::info("Removing webhook for topic '$webhook->topic' with address (callback url) of '$webhook->address'");
                $deleteResponse = $client->delete(env('RECHARGE_WEBHOOKS_URL') . '/' . $webhook->id);
                \Log::info($deleteResponse->getBody());
            }
        }
    }
}
