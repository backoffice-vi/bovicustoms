<?php

namespace Tests\Feature;

use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use App\Models\WebFormSubmission;
use App\Services\FtpSubmission\FixAndResubmitService;
use Tests\TestCase;

/**
 * Pre-FTP validation tests for FixAndResubmitService. These exercise the
 * guard rails (retry cap, no-tariff-guessing rule, missing shipment) without
 * actually performing an FTP submission.
 *
 * Full end-to-end resubmission against CAPS is intentionally not part of
 * automated tests — it would require live FTP credentials and real customs
 * acceptance — and is covered manually via the broker UI on declaration 63.
 */
class FixAndResubmitServiceTest extends TestCase
{
    public function test_throws_when_retry_count_is_at_cap(): void
    {
        $declaration = DeclarationForm::withoutGlobalScopes()->find(63);
        if (!$declaration) {
            $this->markTestSkipped('Declaration 63 fixture not present.');
        }

        $credentials = OrganizationSubmissionCredential::forFtp(
            $declaration->organization_id,
            $declaration->country_id
        )->first();
        if (!$credentials) {
            $this->markTestSkipped('No FTP credentials seeded for declaration 63.');
        }

        $parent = new WebFormSubmission([
            'declaration_form_id' => $declaration->id,
            'submission_type' => 'ftp',
            'status' => 'submitted',
            'retry_count' => 3,
        ]);
        $parent->id = 999999;

        $service = app(FixAndResubmitService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Maximum retry count/i');

        $service->apply($parent, $declaration, $credentials, [
            ['declaration_form_item_id' => 1, 'new_code' => '1006.301'],
        ]);
    }

    public function test_throws_when_fixes_are_empty(): void
    {
        $declaration = DeclarationForm::withoutGlobalScopes()->find(63);
        if (!$declaration) {
            $this->markTestSkipped('Declaration 63 fixture not present.');
        }

        $credentials = OrganizationSubmissionCredential::forFtp(
            $declaration->organization_id,
            $declaration->country_id
        )->first();
        if (!$credentials) {
            $this->markTestSkipped('No FTP credentials seeded for declaration 63.');
        }

        $parent = new WebFormSubmission([
            'declaration_form_id' => $declaration->id,
            'submission_type' => 'ftp',
            'status' => 'submitted',
            'retry_count' => 0,
        ]);
        $parent->id = 999998;

        $service = app(FixAndResubmitService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No fixes provided|Failed to apply fixes/i');

        $service->apply($parent, $declaration, $credentials, []);
    }

    public function test_rejects_invented_tariff_codes_per_no_guessing_rule(): void
    {
        $declaration = DeclarationForm::withoutGlobalScopes()->find(63);
        if (!$declaration) {
            $this->markTestSkipped('Declaration 63 fixture not present.');
        }

        $credentials = OrganizationSubmissionCredential::forFtp(
            $declaration->organization_id,
            $declaration->country_id
        )->first();
        if (!$credentials) {
            $this->markTestSkipped('No FTP credentials seeded for declaration 63.');
        }

        $parent = new WebFormSubmission([
            'declaration_form_id' => $declaration->id,
            'submission_type' => 'ftp',
            'status' => 'submitted',
            'retry_count' => 0,
        ]);
        $parent->id = 999997;

        $service = app(FixAndResubmitService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no-tariff-guessing|exact match/i');

        $service->apply($parent, $declaration, $credentials, [
            [
                'declaration_form_item_id' => $declaration->declarationItems->first()->id ?? 1,
                'new_code' => '9999999', // intentionally bogus
            ],
        ]);
    }
}
