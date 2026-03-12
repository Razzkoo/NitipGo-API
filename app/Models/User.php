<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';


    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'status',
        'profile_photo'
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // create access token (short lived)
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

    // create refresh token (long lived)
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
        return $this->hasMany(Transaction::class, 'customer_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'customer_id');
    }

    public function userRequests()
    {
        return $this->hasMany(UserRequest::class);
    }

    public function approvedTravelerRequests()
    {
        return $this->hasMany(TravelerRequest::class, 'approved_by');
    }

    public function systemSettingsUpdated()
    {
        return $this->hasMany(SystemSetting::class, 'updated_by');
    }

    //settings
    public function setting()
    {
        return $this->hasOne(UserSetting::class);
    }

    //ratings
    public function ratings()
    {
        return $this->hasMany(Rating::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'user_id');
    }
}
