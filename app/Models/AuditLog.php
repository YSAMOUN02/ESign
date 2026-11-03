<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'signer_id', 'user_id', 'action', 'description', 'ip_address', 'user_agent', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(Signer::class);
    }

    public static function record(Document $document, string $action, string $description, ?Signer $signer = null, array $meta = []): self
    {
        $request = request();

        return static::create([
            'document_id' => $document->id,
            'signer_id' => $signer?->id,
            'user_id' => $signer ? null : auth()->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            'meta' => $meta ?: null,
        ]);
    }
}
