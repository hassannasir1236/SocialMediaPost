<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'access_token', 'avatar', 'phone_number', 'address', 'refresh_token', 'expires_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
