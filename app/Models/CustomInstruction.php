<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomInstruction extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'content',
        'is_default',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}