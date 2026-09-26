<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AnonymiseExpiredLeads;
use Illuminate\Console\Command;

/**
 * Run the lead retention policy immediately instead of waiting for the
 * scheduler.
 */
class PurgeLeadsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'safari:purge-leads {--months= : Override the configured retention window}';

    /**
     * @var string
     */
    protected $description = 'Anonymise closed leads older than the retention window';

    public function handle(AnonymiseExpiredLeads $job): int
    {
        $months = $this->option('months');

        $count = (new AnonymiseExpiredLeads(is_numeric($months) ? (int) $months : null))->handle();

        $this->info(sprintf('Anonymised %d lead(s).', $count));

        return self::SUCCESS;
    }
}
