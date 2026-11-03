<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Signer extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'name', 'email', 'order', 'token', 'status', 'decline_reason',
        'notified_at', 'viewed_at', 'signed_at', 'ip_address', 'user_agent',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Signer $signer) {
            $signer->token ??= Str::random(64);
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class);
    }

    public function hasFinished(): bool
    {
        return in_array($this->status, ['signed', 'declined'], true);
    }

    public function signingUrl(): string
    {
        return route('sign.show', $this->token);
    }
}
