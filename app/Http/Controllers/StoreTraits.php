<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\User;
use App\Store;
use App\Charge;
use App\SubscriptionPlan;
use Carbon\Carbon;

trait StoreTraits {

    function removeStoreData($store)
    {
        $user = $store->users->first();
        $user->providers()->delete();

        $store->uninstalled = true;
        $store->uninstall_dt = Carbon::now();
        $store->reinstall_dt = null;
        $store->save();
    }

    function markUserForLogout($store)
    {
        //webhook callback does not contain the state of a specific user, so mark the user for logout and process via middleware on next web request
        $user = User::whereHas('stores', function($q) use ($store) {
            $q->where('store_id', $store->id);
        })->first();
        $user->logout = true;
        $user->save();
    }

}