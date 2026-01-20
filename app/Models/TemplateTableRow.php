<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateTableRow extends Model
{
    protected $fillable = [
        'template_block_id',
        'label',
        'value',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Get the block that owns this row.
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(TemplateBlock::class, 'template_block_id');
    }
}
