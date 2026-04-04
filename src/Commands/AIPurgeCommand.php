<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Commands;

use Illuminate\Console\Command;
use Edfavieljr\LaravelAIBridge\Models\AICompletion;

final class AIPurgeCommand extends Command
{
    protected $signature = 'ai:purge {--days= : Override configured purge_after_days}';

    protected $description = 'Purge old AI completion records from the database';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('ai.storage.purge_after_days', 30));

        $deleted = AICompletion::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Purged {$deleted} AI completion records older than {$days} days.");

        return self::SUCCESS;
    }
}
