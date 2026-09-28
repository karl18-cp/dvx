<?php

namespace App\Jobs;

use App\Services\WorkplaceEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWorkplaceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 40;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WorkplaceEmailService $service): void
    {
        $service->deliver($this->deliveryId);
    }
}
