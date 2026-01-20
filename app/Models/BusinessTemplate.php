<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessTemplateFactory> */
    use HasFactory;

    /**
     * The available template types.
     */
    public const TYPE_EMAIL_PAYMENT_SUCCESS = 'email_payment_success';

    public const TYPE_EMAIL_PAYMENT_FAILED = 'email_payment_failed';

    public const TYPE_EMAIL_PAYMENT_REMINDER = 'email_payment_reminder';

    public const TYPE_EMAIL_PAYROLL_SUCCESS = 'email_payroll_success';

    public const TYPE_EMAIL_PAYROLL_FAILED = 'email_payroll_failed';

    public const TYPE_EMAIL_PAYSLIP = 'email_payslip';

    public const TYPE_EMAIL_BUSINESS_CREATED = 'email_business_created';

    public const TYPE_PAYSLIP_PDF = 'payslip_pdf';

    /**
     * The available presets.
     */
    public const PRESET_DEFAULT = 'default';

    public const PRESET_MODERN = 'modern';

    public const PRESET_MINIMAL = 'minimal';

    protected $fillable = [
        'business_id',
        'type',
        'name',
        'preset',
        'compiled_html',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the business that owns the template.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the blocks for this template.
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(TemplateBlock::class)->orderBy('sort_order');
    }

    /**
     * Get all available template types.
     *
     * @return array<string, string>
     */
    public static function getTemplateTypes(): array
    {
        return [
            self::TYPE_EMAIL_PAYMENT_SUCCESS => 'Payment Success Email',
            self::TYPE_EMAIL_PAYMENT_FAILED => 'Payment Failed Email',
            self::TYPE_EMAIL_PAYMENT_REMINDER => 'Payment Reminder Email',
            self::TYPE_EMAIL_PAYROLL_SUCCESS => 'Payroll Success Email',
            self::TYPE_EMAIL_PAYROLL_FAILED => 'Payroll Failed Email',
            self::TYPE_EMAIL_PAYSLIP => 'Payslip Email',
            self::TYPE_EMAIL_BUSINESS_CREATED => 'Business Created Email',
            self::TYPE_PAYSLIP_PDF => 'Payslip PDF',
        ];
    }

    /**
     * Get all available presets.
     *
     * @return array<string, string>
     */
    public static function getPresets(): array
    {
        return [
            self::PRESET_DEFAULT => 'Default',
            self::PRESET_MODERN => 'Modern',
            self::PRESET_MINIMAL => 'Minimal',
        ];
    }

    /**
     * Check if a template type is valid.
     */
    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::getTemplateTypes());
    }

    /**
     * Check if a preset is valid.
     */
    public static function isValidPreset(string $preset): bool
    {
        return array_key_exists($preset, self::getPresets());
    }

    /**
     * Get content as array format (for frontend compatibility).
     *
     * @return array<string, mixed>
     */
    public function getContentArray(): array
    {
        $this->loadMissing(['blocks.properties', 'blocks.tableRows']);

        return [
            'blocks' => $this->blocks->map(fn (TemplateBlock $block) => $block->toContentArray())->toArray(),
        ];
    }
}
