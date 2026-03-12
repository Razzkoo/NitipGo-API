<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class Traveler extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'travelers';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'city',
        'province',
        'address',
        'birth_date',
        'gender',
        'ktp_number',
        'ktp_photo',
        'selfie_with_ktp',
        'pass_photo',
        'sim_card_photo',
        'status',
        'email_verified',
        'phone_verified',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'email_verified_at' => 'datetime'
    ];

    // create access token
    public function createAccessToken(string $device = 'api', array $abilities = ['*'])
    {
        return $this->tokens()->create([
            'name' => $device,
            'token' => hash('sha256', Str::random(40)),
            'abilities' => $abilities,
            'expires_at' => now()->addMinutes(15),
            'is_refresh' => false,
        ]);
    }

    // create refresh token
    public function createRefreshToken(string $device = 'api')
    {
        return $this->tokens()->create([
            'name' => $device . '_refresh',
            'token' => hash('sha256', Str::random(64)),
            'abilities' => ['refresh'],
            'expires_at' => now()->addDays(7),
            'is_refresh' => true,
        ]);
    }

    //relation
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'traveler_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'traveler_id');
    }

    public function payoutAccounts()
    {
        return $this->hasMany(PayoutAccount::class, 'traveler_id');
    }

    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class, 'traveler_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'traveler_id');
    }

    public function travelerRequests()
    {
        return $this->hasMany(TravelerRequest::class, 'traveler_id');
    }

    public function travelerBoosters()
    {
        return $this->hasMany(TravelerBooster::class, 'traveler_id');
    }

    public function paymentBoosters()
    {
        return $this->hasMany(PaymentBooster::class, 'traveler_id');
    }

    //settings
    public function setting()
    {
        return $this->hasOne(UserSetting::class, 'traveler_id');
    }

    //rating & report
    public function ratings()
    {
        return $this->hasMany(Rating::class, 'traveler_id');
    }
}