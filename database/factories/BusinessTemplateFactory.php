<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessTemplate;
use App\Models\TemplateBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessTemplate>
 */
class BusinessTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = array_keys(BusinessTemplate::getTemplateTypes());

        return [
            'business_id' => Business::factory(),
            'type' => fake()->randomElement($types),
            'name' => fake()->words(3, true),
            'preset' => BusinessTemplate::PRESET_DEFAULT,
            'compiled_html' => null,
            'is_active' => true,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (BusinessTemplate $template) {
            // Create default blocks
            $this->createDefaultBlocks($template);
        });
    }

    /**
     * Create default blocks for a template.
     */
    private function createDefaultBlocks(BusinessTemplate $template): void
    {
        // Header block
        $headerBlock = $template->blocks()->create([
            'block_id' => fake()->uuid(),
            'type' => TemplateBlock::TYPE_HEADER,
            'sort_order' => 0,
        ]);
        $headerBlock->properties()->createMany([
            ['key' => 'logoUrl', 'value' => ''],
            ['key' => 'businessName', 'value' => '{{business_name}}'],
            ['key' => 'backgroundColor', 'value' => '#1a1a1a'],
            ['key' => 'textColor', 'value' => '#ffffff'],
        ]);

        // Text block
        $textBlock = $template->blocks()->create([
            'block_id' => fake()->uuid(),
            'type' => TemplateBlock::TYPE_TEXT,
            'sort_order' => 1,
        ]);
        $textBlock->properties()->createMany([
            ['key' => 'content', 'value' => 'Hello {{recipient_name}},'],
            ['key' => 'fontSize', 'value' => '16px'],
            ['key' => 'color', 'value' => '#4a4a4a'],
            ['key' => 'alignment', 'value' => 'left'],
        ]);

        // Footer block
        $footerBlock = $template->blocks()->create([
            'block_id' => fake()->uuid(),
            'type' => TemplateBlock::TYPE_FOOTER,
            'sort_order' => 2,
        ]);
        $footerBlock->properties()->createMany([
            ['key' => 'text', 'value' => '© {{year}} {{business_name}}. All rights reserved.'],
            ['key' => 'color', 'value' => '#6b7280'],
        ]);
    }

    /**
     * Indicate that the template uses modern preset.
     */
    public function modern(): static
    {
        return $this->state(fn (array $attributes) => [
            'preset' => BusinessTemplate::PRESET_MODERN,
        ]);
    }

    /**
     * Indicate that the template uses minimal preset.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'preset' => BusinessTemplate::PRESET_MINIMAL,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create template without blocks.
     */
    public function withoutBlocks(): static
    {
        return $this->afterCreating(function (BusinessTemplate $template) {
            // Don't create blocks - override the configure() method
        })->configure();
    }
}
