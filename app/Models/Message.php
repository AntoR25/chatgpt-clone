<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Conversation;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'parent_message_id',
        'role',
        'content',
        'model',
        'token_count',
        'cost',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_message_id');
    }

    public function children()
    {
        return $this->hasMany(Message::class, 'parent_message_id');
    }
}