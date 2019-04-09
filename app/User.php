<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laratrust\Traits\LaratrustUserTrait;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class User extends Authenticatable implements MustVerifyEmail
{
    use LaratrustUserTrait;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'name', 'email', 'password', 'old_user_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
    * Get the stores for the user
    */
    public function stores()
    {
        return $this->belongsToMany('App\Store', 'store_users');
    }

    public function passes()
    {
        return $this->hasMany('App\PassPurchase');
    }

    /**
    * Get the providers for the user
    */
    public function providers()
    {
        return $this->hasMany('App\UserProvider');
    }

    public function sendPasswordResetNotification($token)
    {
        if ($this->reset_required)
            $this->notify(new NewOrderResetRequired($token));
        else
            $this->notify(new ResetPassword($token));
    }
}

class NewOrderResetRequired extends ResetPassword
{
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line('Thank you for your purchase from Tri-Fun! We have created an account for you to use when redeeming your passes. To get started, please follow the link below')
            ->action('Sign In', url(config('app.url') . route('password.reset', $this->token, false)))
            ->line('You are receiving this email because of a recent purchase from Tri-Fun. Please contact us if you did not make a purchase from Tri-Fun using this email address.');
    }
}