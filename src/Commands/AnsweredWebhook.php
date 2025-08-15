<?php

namespace SheavesCapital\RingCentral\Commands;

use Illuminate\Console\Command;
use SheavesCapital\RingCentral\Facades\RingCentral;

class AnsweredWebhook extends Command {
    protected $signature = 'ring-central:answered-webhook  {--route=webhooks.ringcentral-answered}';
    protected $description = 'register answered webhook';

    public function handle(): void {
        $route = $this->option('route');
        RingCentral::createWebhook(['/restapi/v1.0/account/~/telephony/sessions?direction=Outbound&statusCode=Answered'], 2592000, route($route));
    }
}
