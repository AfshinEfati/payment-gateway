<?php

namespace PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallPackage extends Command
{
    protected $signature = 'payment:install {--force : Overwrite existing files}';
    protected $description = 'Install the Payment Gateway package and publish assets';

    public function handle()
    {
        $this->info('Installing Payment Gateway Package...');

        $force = $this->option('force');
        $flags = $force ? ['--force' => true] : [];

        // Publish Config
        $this->call('vendor:publish', array_merge(['--tag' => 'efati-payment-config'], $flags));

        // Publish Migrations
        $this->call('vendor:publish', array_merge(['--tag' => 'efati-payment-migrations'], $flags));

        // Publish Seeders
        $this->call('vendor:publish', array_merge(['--tag' => 'efati-payment-seeders'], $flags));

        // Publish Models
        $this->call('vendor:publish', array_merge(['--tag' => 'efati-payment-models'], $flags));

        // Publish Core Logic (Stubs)
        $this->publishStubs($force);

        $this->info('Payment Gateway Package installed successfully.');
    }

    protected function publishStubs($force = false)
    {
        $stubPath = __DIR__ . '/../../../stubs/Payment';
        $targetPath = app_path('Payment');

        if (!File::exists($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
        }

        if ($force) {
            File::copyDirectory($stubPath, $targetPath);
            $this->info('Core logic published to app/Payment (Overwritten).');
        } else {
            // Manual check to avoid overwriting if not forced
            // File::copyDirectory overwrites by default, so we need to be careful if we want to skip.
            // However, copyDirectory doesn't have a "skip if exists" option easily for recursive.
            // But typically "install" implies setting it up.
            // If the user didn't ask for force, maybe we should warn?
            // The user said: "if user later hit install command with force flag then files should be copied otherwise ignore and skip"

            if (File::isEmptyDirectory($targetPath)) {
                File::copyDirectory($stubPath, $targetPath);
                $this->info('Core logic published to app/Payment.');
            } else {
                $this->warn('Core logic (app/Payment) already exists. Use --force to overwrite.');
            }
        }
    }
}
