<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'slug',
        'is_active',
    ];

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}