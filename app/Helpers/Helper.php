<?php

namespace App\Helpers;

use Carbon\Carbon;

class Helper {

    public static function convertDateToDB($date_input) 
    {
        $date = Carbon::createFromFormat('m/d/Y', $date_input);

        return $date->format('Y-m-d');
    }

    public static function convertDateToApp($date_input) 
    {
        $date = Carbon::createFromFormat('Y-m-d', $date_input);

        return $date->format('m/d/Y');
    }

    public static function convertDateTimeToApp($date_input)
    {
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $date_input);

        return $date->format('m/d/y h:i A');
    }

    public static function convertDateTimeToAppDt($date_input)
    {
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $date_input);

        return $date->format('m/d/y');
    }

    public static function convertTimezoneToApp($date_input)
    {
        $date = new Carbon($date_input);

        $date->setTimezone(config('app.timezone'));
        
        return $date;
    }

    public static function getBaseUrl($url)
    {
        $url = preg_replace('#^https?://#', '', $url);
        $url = substr($url, 0, strpos($url, "/"));

        return $url;
    }
    
}