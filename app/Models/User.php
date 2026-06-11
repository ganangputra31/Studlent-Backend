<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama',
        'email',
        'no_hp',
        'password',
        'product_interest', // ← FIX: typo 'product interest' (spasi) → underscore
        'role',
        'foto',
        'joined_at',
    ];

    protected $hidden = [
        'password',
    ];

    public function chats()
    {
        return $this->hasMany(Chat::class, 'sender_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'id_user', 'id_user');
    }

    public function freelancerProfile()
    {
        return $this->hasOne(FreelancerProfile::class, 'id_user', 'id_user');
    }
}