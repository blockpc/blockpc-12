<?php

declare(strict_types=1);

use Blockpc\App\Console\Commands\DumpAutoloadCommand;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;

uses()->group('sistema');

it('blockpc:dump-autoload devuelve éxito cuando composer regenera el autoload', function () {
    $composer = Mockery::mock(Composer::class);
    $composer->shouldReceive('dumpAutoloads')->once()->andReturn(0);
    Artisan::registerCommand(new DumpAutoloadCommand($composer));

    $this->artisan('blockpc:dump-autoload')
        ->expectsOutputToContain('Autoload files regenerated.')
        ->assertExitCode(0);
});

it('blockpc:dump-autoload devuelve FAILURE y no informa éxito si composer falla', function () {
    $composer = Mockery::mock(Composer::class);
    $composer->shouldReceive('dumpAutoloads')->once()->andReturn(1);
    Artisan::registerCommand(new DumpAutoloadCommand($composer));

    $this->artisan('blockpc:dump-autoload')
        ->doesntExpectOutputToContain('Autoload files regenerated.')
        ->expectsOutputToContain('could not be regenerated')
        ->assertExitCode(1);
});
