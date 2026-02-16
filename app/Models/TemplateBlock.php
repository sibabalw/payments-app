<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateBlock extends Model
{
    /**
     * Available block types.
     */
    public const TYPE_HEADER = 'header';

    public const TYPE_TEXT = 'text';

    public const TYPE_BUTTON = 'button';

    public const TYPE_DIVIDER = 'divider';

    public const TYPE_TABLE = 'table';

    public const TYPE_FOOTER = 'footer';

    protected $fillable = [
        'business_template_id',
        'block_id',
        'type',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Get the template that owns this block.
     */
    public function businessTemplate(): BelongsTo
    {
        return $this->belongsTo(BusinessTemplate::class);
    }

    /**
     * Get the properties for this block.
     */
    public function properties(): HasMany
    {
        return $this->hasMany(TemplateBlockProperty::class);
    }

    /**
     * Get the table rows for this block (only for table type).
     */
    public function tableRows(): HasMany
    {
        return $this->hasMany(TemplateTableRow::class)->orderBy('sort_order');
    }

    /**
     * Get all valid block types.
     *
     * @return array<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_HEADER,
            self::TYPE_TEXT,
            self::TYPE_BUTTON,
            self::TYPE_DIVIDER,
            self::TYPE_TABLE,
            self::TYPE_FOOTER,
        ];
    }

    /**
     * Check if a type is valid.
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::getTypes());
    }

    /**
     * Get a property value by key.
     */
    public function getProperty(string $key, mixed $default = null): mixed
    {
        $property = $this->properties->firstWhere('key', $key);

        return $property?->value ?? $default;
    }

    /**
     * Set a property value.
     */
    public function setProperty(string $key, ?string $value): TemplateBlockProperty
    {
        return $this->properties()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Convert block to array format (for frontend).
     *
     * @return array<string, mixed>
     */
    public function toContentArray(): array
    {
        $properties = $this->properties->pluck('value', 'key')->toArray();

        // For table blocks, include rows
        if ($this->type === self::TYPE_TABLE) {
            $properties['rows'] = $this->tableRows->map(fn ($row) => [
                'label' => $row->label,
                'value' => $row->value,
            ])->toArray();
        }

        return [
            'id' => $this->block_id,
            'type' => $this->type,
            'properties' => $properties,
        ];
    }
}
