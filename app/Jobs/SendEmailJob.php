<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SendNotificationMail;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ApiResponse;

    protected $email;

    protected $password;

    protected $emailType;

    protected $lang;

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($email, $password, $emailType, $lang, $userId)
    {
        $this->email = $email;
        $this->password = $password;
        $this->emailType = $emailType;
        $this->lang = $lang;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $logo = $this->getLogoPath();
        $subject = $this->resolveEmailSubject();
        $data = $this->buildEmailData($subject, $logo);
        if ((($this->emailType === 'cancelled') || ($this->emailType === 'subscription')) && $this->userId !== null) {
            $subscriptionData = $this->getLatestTeacherSubscription($this->userId);
            $data = $this->buildCancelData($subject, $logo, $subscriptionData);
        }

        $email = new SendNotificationMail($data);
        Mail::to($this->email)->send($email);
    }
    /**
     * Resolves and returns the subject line for the email.
     *
     * @return string The email subject.
     */
    private function resolveEmailSubject(): string
    {
        if ($this->lang === 'cy') {
            return $this->getWelshEmailSubject();
        }

        return $this->getEnglishEmailSubject();
    }
    /**
     * Builds the base email data array (excluding subscription-specific details).
     *
     * @param string $subject The subject of the email.
     * @param mixed $logo The logo to be included in the email.
     *
     * @return array The basic email content data.
     */
    private function buildEmailData(string $subject, $logo): array
    {
        $redirectLogin = $this->getRedirectUrl('/login');

        return [
            'email' => $this->email,
            'password' => $this->password,
            'emailType' => $this->emailType,
            'logo' => $logo ?? null,
            'subject' => $subject,
            'lang' => $this->lang,
            'redirectLogin' => $redirectLogin ?? env('FRONT_END_URL'),
        ];
    }
    /**
     * Builds the email data array for a subscription cancellation email.
     *
     * @param string $subject The email subject.
     * @param mixed $logo The logo to be included in the email (e.g., URL or image path).
     * @param mixed $subscriptionData Data related to the canceled subscription.
     *
     * @return array The assembled email data.
     */
    private function buildCancelData(string $subject, $logo, $subscriptionData): array
    {
        return [
            'email' => $this->email,
            'emailType' => $this->emailType,
            'logo' => $logo ?? null,
            'subject' => $subject,
            'lang' => $this->lang,
            'resourceName' => $subscriptionData->resourceName,
            'endDate' => $subscriptionData->endDate,
        ];
    }
    /**
     * Resolves and returns the subject with 'en' line for the email.
     *
     * @return string The email subject.
     */
    private function getEnglishEmailSubject(): string
    {
        return match ($this->emailType) {
            'newuser' => 'Your account has been successfully created',
            'forgot' => 'Reset Your Account Password',
            'activate' => 'Your Account Has Been Activated',
            'cancelled' => 'Your Subscription Has Been Cancelled',
            'trail' => 'Your Trial Has Been Expired',
            'subscription' => 'Your Subscription Has Been Expired',
            default => 'Notification',
        };
    }
    /**
     * Resolves and returns the subject with 'cy' line for the email.
     *
     * @return string The email subject.
     */
    private function getWelshEmailSubject(): string
    {
        return match ($this->emailType) {
            'newuser' => 'Mae eich cyfrif wedi’i greu’n llwyddiannus',
            'forgot' => 'Ail-chwiliwch eich cyfrinair cyfrif',
            'activate' => 'Mae eich cyfrif wedi’i actifadu',
            'cancelled' => 'Mae eich tanysgrifiad wedi’i ganslo',
            'trail' => 'Mae eich prawf wedi dod i ben',
            'subscription' => 'Mae eich tanysgrifiad wedi dod i ben',
            default => 'Hysbysiad',
        };
    }
}
