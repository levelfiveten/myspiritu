<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBillingSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('billing_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('product_id');
            $table->integer('next_schedule_id')->nullable();
            $table->string('name');
            $table->string('season_title');
            $table->datetime('start_dt');
            $table->datetime('end_dt');
            $table->datetime('charge_dt');
            $table->datetime('ship_dt');
            $table->datetime('notify_dt');
            $table->datetime('churn_dt');
            $table->datetime('rollover_dt')->nullable();            
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
        Schema::dropIfExists('billing_schedules');
    }
}
