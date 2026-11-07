<?php

declare(strict_types=1);

namespace Blockpc\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;

final class DumpAutoloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockpc:dump-autoload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate framework autoload files';

    public function __construct(private readonly Composer $composer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->composer->dumpAutoloads();
        $this->info('Autoload files regenerated.');

        return self::SUCCESS;
    }
}
