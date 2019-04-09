<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BillingSchedule extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id', 'name', 'start_dt', 'end_dt', 'charge_dt', 'ship_dt'
    ];

    /**
     * Get the next billing schedule in the sequence
     */
    public function nextSchedule()
    {
        return $this->belongsTo(
            'App\BillingSchedule', 'next_schedule_id'
        );
    }

}