<?php

use App\Models\Business;
use App\Models\BusinessTemplate;
use App\Models\User;
use App\Services\TemplateService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::factory()->create(['user_id' => $this->user->id]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);
    $this->user->update(['current_business_id' => $this->business->id]);
});

describe('Template Index', function () {
    it('shows templates page for authenticated user', function () {
        $response = $this->actingAs($this->user)->get('/templates');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('templates/index')
            ->has('templates')
            ->has('presets')
        );
    });

    it('shows all template types', function () {
        $response = $this->actingAs($this->user)->get('/templates');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('templates.email_payment_success')
            ->has('templates.email_payment_failed')
            ->has('templates.email_payroll_success')
            ->has('templates.email_payslip')
        );
    });

    it('redirects unauthenticated users', function () {
        $response = $this->get('/templates');

        $response->assertRedirect('/login');
    });
});

describe('Template Editor', function () {
    it('shows template editor for valid type', function () {
        $response = $this->actingAs($this->user)->get('/templates/email_payment_success');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('templates/editor')
            ->where('type', 'email_payment_success')
            ->has('content.blocks')
            ->has('presets')
        );
    });

    it('redirects for invalid template type', function () {
        $response = $this->actingAs($this->user)->get('/templates/invalid_type');

        $response->assertRedirect('/templates');
    });

    it('shows default content for uncustomized template', function () {
        $response = $this->actingAs($this->user)->get('/templates/email_payment_success');

        $response->assertInertia(fn ($page) => $page
            ->where('isCustomized', false)
            ->where('preset', 'default')
        );
    });

    it('shows customized content for saved template', function () {
        BusinessTemplate::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'email_payment_success',
            'preset' => 'modern',
        ]);

        $response = $this->actingAs($this->user)->get('/templates/email_payment_success');

        $response->assertInertia(fn ($page) => $page
            ->where('isCustomized', true)
            ->where('preset', 'modern')
        );
    });
});

describe('Template Update', function () {
    it('saves template customization', function () {
        $content = [
            'blocks' => [
                [
                    'id' => 'header-1',
                    'type' => 'header',
                    'properties' => [
                        'businessName' => '{{business_name}}',
                        'backgroundColor' => '#000000',
                        'textColor' => '#ffffff',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put('/templates/email_payment_success', [
            'content' => $content,
            'preset' => 'modern',
        ]);

        $response->assertRedirect('/templates/email_payment_success');

        $this->assertDatabaseHas('business_templates', [
            'business_id' => $this->business->id,
            'type' => 'email_payment_success',
            'preset' => 'modern',
        ]);
    });

    it('validates content is required', function () {
        $response = $this->actingAs($this->user)->put('/templates/email_payment_success', [
            'preset' => 'modern',
        ]);

        $response->assertSessionHasErrors('content');
    });

    it('validates preset is valid', function () {
        $response = $this->actingAs($this->user)->put('/templates/email_payment_success', [
            'content' => ['blocks' => []],
            'preset' => 'invalid_preset',
        ]);

        $response->assertSessionHasErrors('preset');
    });

    it('updates existing template', function () {
        $template = BusinessTemplate::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'email_payment_success',
            'preset' => 'default',
        ]);

        $newContent = [
            'blocks' => [
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'properties' => [
                        'content' => 'Updated content',
                        'fontSize' => '16px',
                        'color' => '#333333',
                        'alignment' => 'left',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put('/templates/email_payment_success', [
            'content' => $newContent,
            'preset' => 'minimal',
        ]);

        $response->assertRedirect();

        $template->refresh();
        expect($template->preset)->toBe('minimal');
        expect($template->content['blocks'][0]['properties']['content'])->toBe('Updated content');
    });
});

describe('Template Reset', function () {
    it('resets template to default', function () {
        BusinessTemplate::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'email_payment_success',
        ]);

        $response = $this->actingAs($this->user)->post('/templates/email_payment_success/reset');

        $response->assertRedirect('/templates/email_payment_success');

        $this->assertDatabaseMissing('business_templates', [
            'business_id' => $this->business->id,
            'type' => 'email_payment_success',
        ]);
    });
});

describe('Template Preview', function () {
    it('returns HTML preview', function () {
        $response = $this->actingAs($this->user)->get('/templates/email_payment_success/preview');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    });
});

describe('Template Service', function () {
    it('returns templates for business', function () {
        $service = app(TemplateService::class);
        $templates = $service->getTemplatesForBusiness($this->business);

        expect($templates)->toHaveKey('email_payment_success');
        expect($templates['email_payment_success'])->toHaveKeys(['type', 'name', 'preset', 'is_customized']);
    });

    it('returns default content for template type', function () {
        $service = app(TemplateService::class);
        $content = $service->getDefaultContent('email_payment_success');

        expect($content)->toHaveKey('blocks');
        expect($content['blocks'])->toBeArray();
    });

    it('compiles template to HTML', function () {
        $service = app(TemplateService::class);
        $content = $service->getDefaultContent('email_payment_success');
        $html = $service->compileTemplate($content);

        expect($html)->toContain('<!DOCTYPE html>');
        expect($html)->toContain('</html>');
    });

    it('renders template with data', function () {
        $service = app(TemplateService::class);
        $html = '<p>Hello {{name}}, from {{company}}</p>';
        $rendered = $service->renderTemplate($html, [
            'name' => 'John',
            'company' => 'Acme Corp',
        ]);

        expect($rendered)->toBe('<p>Hello John, from Acme Corp</p>');
    });

    it('saves template for business', function () {
        $service = app(TemplateService::class);
        $content = ['blocks' => []];

        $template = $service->saveTemplate(
            $this->business,
            'email_payment_success',
            'Payment Success',
            $content,
            'modern'
        );

        expect($template)->toBeInstanceOf(BusinessTemplate::class);
        expect($template->business_id)->toBe($this->business->id);
        expect($template->preset)->toBe('modern');
    });

    it('gets business template', function () {
        $service = app(TemplateService::class);

        // No template initially
        $template = $service->getBusinessTemplate($this->business->id, 'email_payment_success');
        expect($template)->toBeNull();

        // Create template
        BusinessTemplate::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'email_payment_success',
        ]);

        $template = $service->getBusinessTemplate($this->business->id, 'email_payment_success');
        expect($template)->toBeInstanceOf(BusinessTemplate::class);
    });
});

describe('Business Template Model', function () {
    it('has valid template types', function () {
        $types = BusinessTemplate::getTemplateTypes();

        expect($types)->toHaveKey(BusinessTemplate::TYPE_EMAIL_PAYMENT_SUCCESS);
        expect($types)->toHaveKey(BusinessTemplate::TYPE_EMAIL_PAYSLIP);
        expect($types)->toHaveKey(BusinessTemplate::TYPE_PAYSLIP_PDF);
    });

    it('validates template type', function () {
        expect(BusinessTemplate::isValidType('email_payment_success'))->toBeTrue();
        expect(BusinessTemplate::isValidType('invalid_type'))->toBeFalse();
    });

    it('validates preset', function () {
        expect(BusinessTemplate::isValidPreset('default'))->toBeTrue();
        expect(BusinessTemplate::isValidPreset('modern'))->toBeTrue();
        expect(BusinessTemplate::isValidPreset('invalid'))->toBeFalse();
    });

    it('belongs to business', function () {
        $template = BusinessTemplate::factory()->create([
            'business_id' => $this->business->id,
        ]);

        expect($template->business)->toBeInstanceOf(Business::class);
        expect($template->business->id)->toBe($this->business->id);
    });
});
