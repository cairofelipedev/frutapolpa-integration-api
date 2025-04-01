<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    use HasFactory;

    protected $table = 'participants';

    protected $fillable = [
        'cpf',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'email',
        'cep',
        'state',
        'city',
        'neighborhood',
        'address',
        'number',
        'complement',
        'conversation_step'
    ];
}
