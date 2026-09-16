<?php

namespace Tests\Unit;

use App\Models\WebFormSubmission;
use App\Services\Documents\DeclarationAttachmentGatherer;
use App\Services\FtpSubmission\CapsAttachmentUploader;
use PHPUnit\Framework\TestCase;

class CapsAttachmentUploaderTest extends TestCase
{
    public function test_amendment_attachments_use_the_original_etd_reference(): void
    {
        $uploader = new CapsAttachmentUploader(
            $this->createMock(DeclarationAttachmentGatherer::class)
        );
        $submission = new WebFormSubmission([
            'request_data' => [
                'filename' => '10018403072026A.006',
                'is_amendment' => true,
                'amends_reference' => '10018403072026.006',
            ],
        ]);
        $submission->external_reference = '10018403072026A.006';

        $base = $uploader->attachmentBaseFilename($submission);

        $this->assertSame('10018403072026.006', $base);
        $this->assertSame(
            '10018403072026.006.A.pdf',
            $uploader->buildRemoteFilename($base, 'a', 'PDF')
        );
    }

    public function test_original_attachments_use_the_submission_reference(): void
    {
        $uploader = new CapsAttachmentUploader(
            $this->createMock(DeclarationAttachmentGatherer::class)
        );
        $submission = new WebFormSubmission([
            'request_data' => [
                'filename' => '10018403072026.006',
                'is_amendment' => false,
            ],
        ]);
        $submission->external_reference = '10018403072026.006';

        $this->assertSame(
            '10018403072026.006',
            $uploader->attachmentBaseFilename($submission)
        );
    }
}
