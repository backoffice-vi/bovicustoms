<?php

namespace App\Console\Commands;

use App\Models\DeclarationForm;
use App\Models\WebFormTarget;
use App\Services\WebFormSubmission\CapsPreValidationService;
use App\Services\WebFormSubmission\WebFormDataMapper;
use Illuminate\Console\Command;

class ValidateCapsPayload extends Command
{
    protected $signature = 'caps:validate-payload
        {declaration_id : Declaration form ID}
        {--target= : CAPS web form target ID}
        {--no-ai : Build payload without AI assistance}
        {--json : Output JSON}
        {--show-payload : Include the redacted payload in text output}';

    protected $description = 'Build and validate the CAPS web payload without launching Playwright';

    public function handle(WebFormDataMapper $mapper, CapsPreValidationService $validator): int
    {
        $declaration = DeclarationForm::find($this->argument('declaration_id'));

        if (!$declaration) {
            $this->error('Declaration not found.');
            return self::FAILURE;
        }

        $target = $this->option('target')
            ? WebFormTarget::find($this->option('target'))
            : WebFormTarget::active()
                ->where('country_id', $declaration->country_id)
                ->where(function ($query) {
                    $query->where('base_url', 'like', '%caps.gov.vg%')
                        ->orWhere('name', 'like', '%caps%');
                })
                ->first();

        if (!$target) {
            $this->error('CAPS web form target not found. Pass --target=<id> if needed.');
            return self::FAILURE;
        }

        $useAI = !$this->option('no-ai');
        $payload = $mapper->buildCapsSubmissionData($declaration, $target, $useAI);
        $payload['country_id'] = $target->country_id;
        $validation = $validator->validateWebPayload($payload, $target->country_id);
        $redactedPayload = $validator->redactPayload($payload);

        if ($this->option('json')) {
            $this->line(json_encode([
                'declaration_id' => $declaration->id,
                'target_id' => $target->id,
                'ai_enabled' => $useAI,
                'validation' => $validation,
                'payload' => $redactedPayload,
            ], JSON_PRETTY_PRINT));

            return $validation['valid'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Declaration: {$declaration->id} ({$declaration->form_number})");
        $this->info("Target: {$target->id} {$target->name}");
        $this->info('AI mapping: ' . ($useAI ? 'enabled' : 'disabled'));
        $this->line('Items: ' . ($validation['summary']['items'] ?? 0));
        $this->line('Errors: ' . count($validation['errors']) . ' | Warnings: ' . count($validation['warnings']));

        if (!empty($validation['errors'])) {
            $this->newLine();
            $this->error('Errors');
            foreach ($validation['errors'] as $error) {
                $this->line(" - {$error}");
            }
        }

        if (!empty($validation['warnings'])) {
            $this->newLine();
            $this->warn('Warnings');
            foreach ($validation['warnings'] as $warning) {
                $this->line(" - {$warning}");
            }
        }

        if ($this->option('show-payload')) {
            $this->newLine();
            $this->line(json_encode($redactedPayload, JSON_PRETTY_PRINT));
        }

        return $validation['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
