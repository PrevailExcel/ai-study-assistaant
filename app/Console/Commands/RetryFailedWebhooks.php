<?php
namespace App\Console\Commands;

use App\Models\WebhookLog;
use App\Http\Controllers\PaystackWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class RetryFailedWebhooks extends Command
{
    protected $signature = 'webhooks:retry {--limit=10}';
    protected $description = 'Retry failed webhook processing';

    public function handle()
    {
        $limit = $this->option('limit');

        $failedWebhooks = WebhookLog::where('status', 'failed')
            ->where('created_at', '>', now()->subDays(7)) // Only retry recent webhooks
            ->limit($limit)
            ->get();

        if ($failedWebhooks->isEmpty()) {
            $this->info('No failed webhooks to retry.');
            return;
        }

        $this->info("Retrying {$failedWebhooks->count()} failed webhooks...");

        $controller = app(PaystackWebhookController::class);

        foreach ($failedWebhooks as $webhook) {
            $this->line("Processing webhook {$webhook->id} - Event: {$webhook->event}");

            try {
                $request = Request::create('/webhook/paystack', 'POST', $webhook->payload);
                $controller->handle($request);
                $this->info("✓ Successfully processed webhook {$webhook->id}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to process webhook {$webhook->id}: {$e->getMessage()}");
            }
        }

        $this->info('Webhook retry completed!');
    }
}
