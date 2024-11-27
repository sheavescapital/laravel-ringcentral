<?php

namespace SheavesCapital\RingCentral\Commands;

use Illuminate\Console\Command;
use SheavesCapital\RingCentral\Facades\RingCentral;

class RecordingWebhook extends Command {
    protected $signature = 'ring-central:recording-webhook';
    protected $description = 'register recording webhook';

    public function handle(): void {
        RingCentral::createWebhook(['/restapi/v1.0/account/~/telephony/sessions?withRecordings=true&statusCode=Disconnected'], 2592000, route('webhooks.ringcentral'));
    }
}
