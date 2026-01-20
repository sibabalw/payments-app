<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateBlockProperty extends Model
{
    protected $fillable = [
        'template_block_id',
        'key',
        'value',
    ];

    /**
     * Get the block that owns this property.
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(TemplateBlock::class, 'template_block_id');
    }
}
