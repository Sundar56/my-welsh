<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Admin\Modules\Resources\Models\Resources;
use App\Api\Teacher\Modules\Signup\Models\BillingEmail;
use App\Api\Teacher\Modules\Signup\Models\BillingInvoiceUsers;
use App\Mail\SendInvoiceMail;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class InvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ApiResponse;

    protected $invoiceId;

    protected $lang;

    /**
     * Create a new job instance.
     */
    public function __construct($invoiceId, $lang)
    {
        $this->invoiceId = $invoiceId;
        $this->lang = $lang;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $billingInvoiceId = $this->invoiceId;
        $invoicePayload = $this->billingInvoice($billingInvoiceId);
        $invoiceEmail = $invoicePayload['invoice_email'];
        $invoiceData = $invoicePayload['data'];
        $invoiceNumber = 'INV-' . str_pad(strval($billingInvoiceId), 6, '0', STR_PAD_LEFT);
        $issueDate = now()->format('d M Y');
        $data = $this->buildEmailData($invoiceEmail, $invoiceData, $invoiceNumber, $issueDate);
        $lang = $this->lang ?? 'en';
        $invoicePdf = $this->generateInvoicePdf($data, $lang);

        $email = new SendInvoiceMail($data, $invoicePdf, $lang);
        // $this->logInvoiceMailDebug($invoiceEmail, (array) $data, $invoicePdf, $lang, $email);
        Mail::to($invoiceEmail)->send($email);
    }
    /**
     * Generate and return the file path of the created invoice PDF.
     *
     * @param array $data The data used to populate the invoice (e.g., email, items, total).
     * @param string $lang languages en or cy.
     *
     * @return string The full path or name of the generated PDF file.
     */
    public function generateInvoicePdf(array $data, string $lang): string
    {
        $logoPath = env('FFALALA_LOGO');
        $pdflogo = $this->getBase64Image($logoPath);
        $data['pdflogo'] = $pdflogo;
        $viewName = $lang === 'cy' ? 'emails.invoicepdf_cy' : 'emails.invoicepdf';

        $html = View::make($viewName, ['data' => $data])->render();

        try {
            $mpdf = new \Mpdf\Mpdf();
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } catch (\Mpdf\MpdfException $e) {
            throw $e;
        }
    }
    /**
     * Get billing invoice user details with their first subscribed resource.
     *
     * @param int $billingInvoiceId
     *
     * @return array
     */
    private function billingInvoice($billingInvoiceId)
    {
        $data = [];

        $billingUsers = BillingInvoiceUsers::where('billing_invoice_id', $billingInvoiceId)->get();
        $billingInvoice = BillingEmail::find($billingInvoiceId);

        foreach ($billingUsers as $billingUser) {
            $user = $this->getUserById($billingUser->user_id);
            $subscription = $this->getLatestSubscriptionForUser($billingUser->user_id);
            $resource = $this->getResourceFromSubscription($subscription);

            $data[] = [
                'user_email' => $user?->email,
                'resource_name' => $resource?->resource_name ?? null,
                'amount' => $resource?->annual_fee ?? 0,
            ];
        }
        return [
            'invoice_email' => $billingInvoice?->invoice_email,
            'data' => $data,
        ];
    }
    /**
     * Get the latest subscription entry for a given user.
     *
     * @param int $userId
     *
     * @return UserSubscription|null
     */
    private function getLatestSubscriptionForUser(int $userId): ?UserSubscription
    {
        return UserSubscription::where('user_id', $userId)
            ->where('latest_subscription', UserSubscription::STATUS_ONE)
            ->orderByDesc('id')
            ->first();
    }
    /**
     * Get the learning resource associated with a subscription.
     *
     * @param UserSubscription|null $subscription
     *
     * @return Resources|null
     */
    private function getResourceFromSubscription(?UserSubscription $subscription): ?Resources
    {
        return $subscription ? Resources::find($subscription->resource_id) : null;
    }
    /**
     * Build and return the structured email data array for invoice generation.
     *
     * @param string $invoiceEmail   The recipient's invoice email address.
     * @param array  $invoiceData    The detailed invoice data including plans and amounts.
     * @param string $invoiceNumber  The unique invoice number.
     * @param string $issueDate      The date the invoice was issued.
     *
     * @return array The formatted data array to be used in the email.
     */
    private function buildEmailData($invoiceEmail, $invoiceData, $invoiceNumber, $issueDate): array
    {
        $logo = $this->getLogoPath();
        $redirectLogin = $this->getRedirectUrl('/login');

        return [
            'invoiceEmail' => $invoiceEmail,
            'invoiceData' => $invoiceData,
            'invoiceNumber' => $invoiceNumber,
            'issueDate' => $issueDate,
            'logo' => $logo ?? null,
            'redirectLogin' => $redirectLogin ?? env('FRONT_END_URL'),
        ];
    }
}
