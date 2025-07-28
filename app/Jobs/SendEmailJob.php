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

    /**
     * Create a new job instance.
     */
    public function __construct($email, $password, $emailType)
    {
        $this->email = $email;
        $this->password = $password;
        $this->emailType = $emailType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $logo = $this->getLogoPath();
        $subject = $this->resolveEmailSubject();
        $data = $this->buildEmailData($subject, $logo);

        $email = new SendNotificationMail($data);
        Mail::to($this->email)->send($email);
    }
    private function resolveEmailSubject(): string
    {
        return match ($this->emailType) {
            'newuser' => 'Your account has been successfully created',
            'forgot' => 'Reset Your Account Password',
            default => 'Notification',
        };
    }
    private function buildEmailData(string $subject, $logo): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'emailType' => $this->emailType,
            'logo' => $logo ?? null,
            'subject' => $subject,
        ];
    }
    private function getLoginUrl()
    {
        $baseUrl = env('FRONT_END_URL');
        // $adminLogin = env('ADMIN_LOGIN_URL');
        $userLogin = env('USER_LOGIN_URL');
        // $adminLoginUrl = $baseUrl . '/' . $adminLogin;
        return $baseUrl . '/' . $userLogin;
    }
}
