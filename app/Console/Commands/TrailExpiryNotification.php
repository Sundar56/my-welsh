<?php

namespace App\Console\Commands;

use App\Models\TrailHistory;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TrailExpiryNotification extends Command
{
    use ApiResponse;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:trail-expiry-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send expiry notification email to users whose trial has expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredTrials = $this->getExpiredTrials();

        foreach ($expiredTrials as $trial) {
            DB::beginTransaction();
            try {
                $this->sendExpiryEmailIfApplicable($trial);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed for trial ID {$trial->id}: " . $e->getMessage());
            }
        }

        $this->info('Trial expiry email process completed.');
    }
    /**
     * Get all trial records where the expiry email hasn't been sent
     * and the trial has expired based on trail_end_date or trail_expired_at.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getExpiredTrials()
    {
        $now = Carbon::now();

        return TrailHistory::where('expiry_mail', 0)
            ->where(function ($query) use ($now) {
                $query->where('trail_end_date', '<', $now)
                    ->orWhere('trail_expired_at', '<', $now);
            })
            ->get();
    }
    /**
     * Sends the expiry email to the user if applicable,
     * and updates the TrailHistory to mark the email as sent.
     *
     * @param \App\Models\TrailHistory $trial
     *
     * @return void
     */
    private function sendExpiryEmailIfApplicable(TrailHistory $trial)
    {
        $user = $trial->user;

        if ($user && $user->email) {
            $this->sendUserEmail($user->email, null, 'trail', 'en', null);

            $trial->update(['expiry_mail' => 1]);

            $this->info("Expiry mail sent to: {$user->email}");
        }
    }
}
