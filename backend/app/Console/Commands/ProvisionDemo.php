<?php

namespace App\Console\Commands;

use App\Services\DemoProvisioner;
use Illuminate\Console\Command;
use Throwable;

class ProvisionDemo extends Command
{
    protected $signature = 'partshelf:demo:provision {--credentials= : Private JSON file; never a command-line password}';

    protected $description = 'Provision accounts and idempotent sample data only in the isolated local demo';

    public function handle(DemoProvisioner $provisioner): int
    {
        try {
            $accounts = json_decode(file_get_contents($this->option('credentials')), true, 512, JSON_THROW_ON_ERROR);
            $result = $provisioner->provision($accounts);
            $this->info(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Demo provisioning failed closed. Check isolated target and private credential ownership; values are redacted.');

            return self::FAILURE;
        }
    }
}
