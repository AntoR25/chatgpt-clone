<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use App\Models\Conversation;
use App\Models\CustomInstruction;

#[Fillable(['name', 'email', 'password', 'preferred_model', 'ai_about', 'ai_behavior'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ai_commands' => 'array',
            'preferred_model' => 'string',
            'ai_about' => 'string',
            'ai_behavior' => 'string',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function customInstructions()
    {
        return $this->hasMany(CustomInstruction::class);
    }
}