<?php

namespace SheavesCapital\RingCentral\Commands;

use Illuminate\Console\Command;
use SheavesCapital\RingCentral\Facades\RingCentral;

class RecordingWebhook extends Command {
    protected $signature = 'ring-central:recording-webhook  {--route=webhooks.ringcentral}';
    protected $description = 'register recording webhook';

    public function handle(): void {
        $route = $this->option('route');
        RingCentral::createWebhook(['/restapi/v1.0/account/~/telephony/sessions?withRecordings=true&statusCode=Disconnected'], 2592000, route($route));
    }
}
