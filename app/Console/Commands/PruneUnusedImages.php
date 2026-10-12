<?php

namespace App\Console\Commands;

use App\Services\Media\UnusedImagePruner;
use Illuminate\Console\Command;

class PruneUnusedImages extends Command
{
    protected $signature = 'media:prune-unused';

    protected $description = 'Delete expired image uploads that are no longer referenced';

    public function handle(UnusedImagePruner $pruner): int
    {
        $deleted = $pruner->prune();
        $label = $deleted === 1 ? 'image' : 'images';
        $this->info("Deleted {$deleted} unused expired {$label}.");

        return self::SUCCESS;
    }
}
