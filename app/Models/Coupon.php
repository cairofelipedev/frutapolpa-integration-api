<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['participant_id', 'image'];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
