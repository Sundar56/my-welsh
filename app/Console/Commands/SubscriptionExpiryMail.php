<?php

namespace App\Console\Commands;

use App\Models\SubscriptionHistory;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubscriptionExpiryMail extends Command
{
    use ApiResponse;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:subscription-expiry-mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send expiry notification email to users whose subscription has expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $subscriptionDetails = $this->getExpiredTrials();

        foreach ($subscriptionDetails as $subscription) {
            DB::beginTransaction();
            try {
                $this->sendExpiryEmailIfApplicable($subscription);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed for subscription ID {$subscription->id}: " . $e->getMessage());
            }
        }

        $this->info('Trial expiry email process completed.');
    }
    /**
     * Get all subscription records where the expiry email hasn't been sent
     * and the subscription has expired based on subscription_end_date
     *
     * @return \Illuminate\Support\Collection
     */
    private function getExpiredSubscription()
    {
        $now = Carbon::now();

        return SubscriptionHistory::where('expiry_mail', 0)
            ->where(function ($query) use ($now) {
                $query->where('subscription_end_date', '<', $now);
            })
            ->get();
    }
    /**
     * Sends the expiry email to the user if applicable,
     * and updates the TrailHistory to mark the email as sent.
     *
     * @param \App\Models\SubscriptionHistory $subscription
     *
     * @return void
     */
    private function sendExpiryEmailIfApplicable(SubscriptionHistory $subscription)
    {
        $user = $subscription->user;

        if ($user && $user->email) {
            $this->sendUserEmail($user->email, null, 'subscription', 'en', $user->id);

            $subscription->update(['expiry_mail' => 1]);

            $this->info("Expiry mail sent to: {$user->email}");
        }
    }
}
