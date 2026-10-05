<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpChallenge extends Model
{
    public const FIRST_LOGIN = 'first_login';
    public const PASSWORD_RESET = 'password_reset';
    public const PHONE_VERIFICATION = 'phone_verification';

    protected $fillable = [
        'user_id', 'purpose', 'challenge_token', 'code_hash', 'attempts', 'resend_count',
        'ip_address', 'last_sent_at', 'expires_at', 'verified_at',
        'reset_token_hash', 'reset_expires_at', 'consumed_at',
    ];

    protected $hidden = ['code_hash', 'reset_token_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'resend_count' => 'integer',
            'last_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'reset_expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ces parcours se terminent par le choix d'un nouveau mot de passe. */
    public function requiresPassword(): bool
    {
        return in_array($this->purpose, [self::FIRST_LOGIN, self::PASSWORD_RESET], true);
    }
}
