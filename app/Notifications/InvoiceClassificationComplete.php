<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class InvoiceClassificationComplete extends Notification
{
    use Queueable;

    protected Invoice $invoice;
    protected int $itemCount;
    protected bool $success;
    protected ?string $errorMessage;

    /**
     * Create a new notification instance.
     */
    public function __construct(Invoice $invoice, int $itemCount, bool $success = true, ?string $errorMessage = null)
    {
        $this->invoice = $invoice;
        $this->itemCount = $itemCount;
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        if ($this->success) {
            return [
                'type' => 'invoice_classification_complete',
                'invoice_id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number ?? 'Draft',
                'item_count' => $this->itemCount,
                'title' => 'Invoice Classification Complete',
                'message' => "Classification complete for invoice {$this->invoice->invoice_number}. {$this->itemCount} items classified and ready for review.",
                'action_url' => route('invoices.assign_codes_results', ['invoice' => $this->invoice->id]),
                'icon' => 'fa-check-circle',
                'color' => 'success',
            ];
        }

        return [
            'type' => 'invoice_classification_failed',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number ?? 'Draft',
            'title' => 'Invoice Classification Failed',
            'message' => "Classification failed for invoice {$this->invoice->invoice_number}. Error: {$this->errorMessage}",
            'action_url' => route('invoices.create'),
            'icon' => 'fa-exclamation-circle',
            'color' => 'danger',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
