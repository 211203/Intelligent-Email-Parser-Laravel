<?php

namespace App\Jobs;

use App\AI\Agents\BookingAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessHotelEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    public function __construct(
        public readonly string $clientName,
    ) {}

    /**
     * Execute the job using the Laravel AI SDK agent.
     */
    public function handle(): void
    {
        Log::info('ProcessHotelEmailJob.start', ['client_name' => $this->clientName]);

        try {
            $agent = new BookingAgent();
            $result = $agent->processEmail(
                "Process the latest email for client: {$this->clientName}. "
                . "Fetch the email, determine its type, parse the content, and handle it accordingly."
            );

            Log::info('ProcessHotelEmailJob.completed', [
                'client_name' => $this->clientName,
                'email_type' => $result['email_type'] ?? 'unknown',
                'success' => $result['success'] ?? false,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessHotelEmailJob.failed', [
                'client_name' => $this->clientName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
