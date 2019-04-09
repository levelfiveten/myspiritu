<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
\URL::forceScheme('https');

Route::get('/', 'HomeController@index')->name('home');

Auth::routes(['register' => false]);
Route::get('/logout', 'Auth\LoginController@logout');

/* Shopify login routes */
Route::get('login/shopify/admin', ['uses' => 'Auth\LoginShopifyController@index'])->name('login.admin');
Route::get('login/shopify', ['uses' => 'Auth\LoginShopifyController@redirectToProvider'])->name('login.shopify');
Route::get('login/shopify/callback', ['uses' => 'Auth\LoginShopifyController@handleProviderCallback']);

/* Store admin routes */
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/store', ['uses' => 'StoreController@index'])->name('store');
    Route::get('/store/{storeId}', 'StoreController@shopifyStore')->name('shopify.store'); //dashboard
    Route::get('webhook/recharge/register', 'StoreController@registerRechargeWebhooks')->name('webhooks.recharge.register');
    Route::get('webhook/recharge/remove', 'StoreController@removeRechargeWebhooks')->name('webhooks.recharge.remove');

    Route::get('recharge/subscriptions/update', 'SubscriptionController@updateCurrentSubscriptions');
    Route::get('recharge/subscriptions/address/update', 'SubscriptionController@updateSubscriptionByAddress');
});

/* Shopify store webhook routes --no auth, but digital signature is calculated and verified in the webhook middleware */
Route::post('webhook/shopify/uninstall', 'StoreController@uninstall')->middleware('webhook');
Route::post('webhook/shopify/order_paid', 'StoreController@orderPaid')->middleware('webhook');
// Route::get('webhook/shopify/uninstall', 'StoreController@uninstall')->middleware('auth');
Route::post('webhook/shopify/gdpr/customer-redact', 'StoreController@customerRedact')->middleware('webhook'); //make sure that they are not an active client
Route::post('webhook/shopify/gdpr/shop-redact', 'StoreController@storeRedact')->middleware('webhook'); //make sure that they are not an active client
Route::post('webhook/shopify/gdpr/customer-data', 'StoreController@customerData')->middleware('webhook');

/* ReCharge webhook routes --no auth, but digital signature is calculated and verified in the webhook.recharge middleware */
//SubscriptionController constructor middleware('webhook.recharge')
Route::post('webhook/recharge/subscription_created', 'SubscriptionController@created');
Route::post('webhook/recharge/subscription_updated', 'SubscriptionController@updated');
Route::post('webhook/recharge/charge_paid', 'SubscriptionController@chargePaid');
Route::post('webhook/recharge/order_processed', 'SubscriptionController@orderProcessed');

Route::get('webhook/recharge/subscription', 'SubscriptionController@getSubscription');

Route::get('webhook/recharge/orders', 'SubscriptionController@getOrders');
Route::get('webhook/recharge/shift', 'SubscriptionController@shiftQueuedOrdersToNextSeason');