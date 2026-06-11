<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreelancerProfile extends Model
{
    protected $table      = 'freelancer_profiles';
    protected $primaryKey = 'id_profile';

    protected $fillable = [
        'id_user',
        'professional_status',
        'universitas',
        'jurusan',
        'bio',
        'no_rekening',
        'bank_name',
        'total_earned',
        'rating_avg',
        'total_rating',
        'total_order',
        'is_verified',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}