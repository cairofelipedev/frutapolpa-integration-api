<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    use HasFactory;

    protected $table = 'participants';

    protected $fillable = [
        'first_name',
        'cpf',
        'phone',
        'cep',
        'state',
        'city',
        'step_register',
        'full_name',
        'last_message_at',
        'is_active'
    ];

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function codes()
    {
        return $this->hasMany(CouponCode::class);
    }
}
