<?php

namespace App\Jobs;

use App\Services\FtpSubmission\CapsAttachmentUploader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled every 15 minutes by App\Console\Kernel.
 *
 * Walks every FTP submission that has attachments waiting for CAPS confirmation
 * and reads the corresponding ETD<filename>_ATT_REP.TXT response file from the
 * CAPS FTP server. Updates each FtpSubmissionAttachment row to confirmed or
 * rejected as appropriate.
 */
class PollFtpAttachmentResponses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public ?int $countryId = null)
    {
    }

    public function handle(CapsAttachmentUploader $uploader): void
    {
        $started = microtime(true);
        $result = $uploader->pollResponses($this->countryId);

        Log::info('PollFtpAttachmentResponses run', [
            'country_id' => $this->countryId,
            'checked' => $result['checked'],
            'updated' => $result['updated'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }
}
