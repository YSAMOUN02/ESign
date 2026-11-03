<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'message', 'original_name', 'original_path', 'signed_path',
        'original_hash', 'signed_hash', 'page_count', 'sequential', 'status', 'sent_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequential' => 'boolean',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class)->orderBy('order')->orderBy('id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->orderBy('created_at')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Signers who may sign right now (respecting sequential order). */
    public function activeSigners()
    {
        $pending = $this->signers->whereIn('status', ['pending', 'viewed']);

        if (! $this->sequential || $pending->isEmpty()) {
            return $pending->values();
        }

        $minOrder = $pending->min('order');

        return $pending->where('order', $minOrder)->values();
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'completed' => 'green',
            'declined', 'voided' => 'red',
            default => 'gray',
        };
    }
}
