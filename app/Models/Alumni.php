<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Alumni extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $guarded = ['id'];
    protected $hidden = ['password'];


    // -------------------------------------
    //          MODEL METHODS
    // -------------------------------------


    // -------------------------------------
    //          OVERRIDE METHODS
    // -------------------------------------
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }


    // -------------------------------------
    //          RELATION METHODS
    // -------------------------------------
    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class);
    }
}
