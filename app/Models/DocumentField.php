<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentField extends Model
{
    public const TYPES = ['signature', 'initials', 'date', 'text', 'name'];

    protected $fillable = ['document_id', 'signer_id', 'type', 'page', 'x', 'y', 'width', 'height', 'required', 'value'];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'required' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(Signer::class);
    }

    public function isImage(): bool
    {
        return in_array($this->type, ['signature', 'initials'], true);
    }
}
