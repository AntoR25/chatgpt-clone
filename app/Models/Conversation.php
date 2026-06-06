<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\AiModel;
use App\Models\CustomInstruction;
use App\Models\Message;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'ai_model_id',
        'custom_instruction_id',
        'title',
    ];

    /**
     * Owner of conversation
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * AI model used (optional)
     */
    public function aiModel()
    {
        return $this->belongsTo(AiModel::class);
    }

    /**
     * Custom instructions (optional)
     */
    public function customInstruction()
    {
        return $this->belongsTo(CustomInstruction::class);
    }

    /**
     * Messages in conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class)
            ->orderBy('created_at', 'asc');
    }
}