<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command('notifications:expire')->hourly();
        $schedule->command('notifications:cleanup')->daily();
    }

    protected $commands = [
        Commands\ExpireNotificationCampaignsCommand::class,
        Commands\CleanNotificationHistoryCommand::class,
        \Laravel\Passport\Console\KeysCommand::class,
        Commands\BsInstallCommand::class,
        Commands\OptionsCacheCommand::class,
        Commands\PluginDisableCommand::class,
        Commands\PluginEnableCommand::class,
        Commands\SaltRandomCommand::class,
        Commands\UpdateCommand::class,
    ];
}
