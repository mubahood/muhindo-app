<?php

namespace App\Console\Commands;

use App\Models\PracticeWorkspace;
use Illuminate\Console\Command;

class PrunePracticeWorkspaces extends Command
{
    protected $signature = 'practice-workspaces:prune';

    protected $description = 'Delete expired temporary code practice workspaces';

    public function handle(): int
    {
        $deleted = PracticeWorkspace::where('expires_at', '<=', now())->delete();
        $this->info("Deleted {$deleted} expired practice workspace(s).");

        return self::SUCCESS;
    }
}
