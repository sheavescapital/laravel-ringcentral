<?php

namespace SheavesCapital\RingCentral\Commands;

use Illuminate\Console\Command;
use SheavesCapital\RingCentral\Facades\RingCentral;

class RenewWebhooks extends Command {
    protected $signature = 'ring-central:renew-webhooks';
    protected $description = 'Renew Expiring RingCentral Webhooks';

    public function handle(): void {
        RingCentral::renewExpiringWebhooks(604800);
    }
}
