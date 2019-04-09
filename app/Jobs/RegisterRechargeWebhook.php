<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use \GuzzleHttp\Client;

class RegisterRechargeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
    * @var string
    */
    public $token;

    /**
    * @var string
    */
    public $topic;

    /**
    * @var string
    */
    public $callbackURL;

    /**
    * Create a new job instance.
    *
    * @return void
    */
    public function __construct($token, $topic, $callbackURL)
    {
        $this->token = $token;
        $this->topic = $topic;
        $this->callbackURL = $callbackURL;
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

        //create the webhook if not found in the webhooks list
        if ($webhookCollection->isEmpty() || !$webhookCollection->contains('topic', $this->topic)) 
        {
            $createResponse = $client->post(env('RECHARGE_WEBHOOKS_URL'), [
                'json' => [
                    'address' => $this->callbackURL,
                    'topic'   => $this->topic
                ]
            ]);
            \Log::info("Creating webhook for topic '$this->topic' with a callback url of '$this->callbackURL'");
            \Log::info($createResponse->getBody());
        }
    }
}
