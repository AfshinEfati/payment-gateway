<?php

namespace PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallPackage extends Command
{
    protected $signature = 'payment:install';
    protected $description = 'Install the Payment Gateway package and publish assets';

    public function handle()
    {
        $this->info('Installing Payment Gateway Package...');

        // Publish Config
        $this->call('vendor:publish', ['--tag' => 'efati-payment-config']);

        // Publish Migrations
        $this->call('vendor:publish', ['--tag' => 'efati-payment-migrations']);

        // Publish Seeders
        $this->call('vendor:publish', ['--tag' => 'efati-payment-seeders']);

        // Publish Models
        $this->call('vendor:publish', ['--tag' => 'efati-payment-models']);

        // Publish Core Logic (Stubs)
        $this->publishStubs();

        $this->info('Payment Gateway Package installed successfully.');
    }

    protected function publishStubs()
    {
        $stubPath = __DIR__ . '/../../../stubs/Payment';
        $targetPath = app_path('Payment');

        if (!File::exists($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
        }

        File::copyDirectory($stubPath, $targetPath);

        $this->info('Core logic published to app/Payment.');
    }
}
