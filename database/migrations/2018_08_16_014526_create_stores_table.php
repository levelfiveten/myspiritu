<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('domain');        
            $table->integer('subscription_plan_id')->nullable();
            $table->datetime('first_subscribe_dt')->nullable();
            $table->datetime('plan_modified_dt')->nullable();
            $table->boolean('uninstalled')->default(0);
            $table->datetime('uninstall_dt')->nullable();
            $table->datetime('reinstall_dt')->nullable();
            $table->timestamps();
            $table->unique(['domain']);
        });
    
        Schema::create('store_users', function (Blueprint $table) {
            $table->integer('store_id');
            $table->integer('user_id');
            $table->unique(['store_id', 'user_id']);
        });

        Schema::create('user_providers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->string('provider_token')->nullable();
            $table->timestamps();
        });
     }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stores');
        Schema::dropIfExists('store_users');
        Schema::dropIfExists('store_charges');
        Schema::dropIfExists('user_providers');
    }
}
