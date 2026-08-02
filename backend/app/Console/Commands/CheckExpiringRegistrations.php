<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notification\Notifications\RegistrationExpiringNotification;
use App\Domain\Professional\Models\ProfessionalProfile;
use Illuminate\Console\Command;

/**
 * Section 5: "Generate automated alerts before license or registration
 * expiry." Meant to run daily via the scheduler -- alerts a professional
 * 30 days before either their NEC registration or their professional
 * license expires, whichever date is sooner.
 */
class CheckExpiringRegistrations extends Command
{
    protected $signature = 'npvams:check-expiring-registrations {--days=30 : Alert this many days before expiry}';

    protected $description = 'Notify professionals whose NEC registration or license is expiring soon.';

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        $threshold = now()->addDays($daysAhead)->toDateString();

        $expiringProfiles = ProfessionalProfile::withoutTenantScope()
            ->where('is_active', true)
            ->where(function ($query) use ($threshold) {
                $query->whereBetween('registration_validity_date', [now()->toDateString(), $threshold])
                    ->orWhereBetween('license_expiry_date', [now()->toDateString(), $threshold]);
            })
            ->with('user')
            ->get();

        $notified = 0;

        foreach ($expiringProfiles as $profile) {
            if ($profile->user === null) {
                continue;
            }

            // Whichever of the two dates is sooner is the one that actually matters to alert about.
            $expiryDate = collect([$profile->registration_validity_date, $profile->license_expiry_date])
                ->filter()
                ->sort()
                ->first();

            $profile->user->notify(new RegistrationExpiringNotification($profile, $expiryDate->toDateString()));
            $notified++;
        }

        $this->info("Checked expiring registrations: {$notified} notified.");

        return self::SUCCESS;
    }
}
