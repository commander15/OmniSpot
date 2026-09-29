<?php

namespace App\Console\Commands\Lab;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[Signature('lab:init')]
#[Description('Init App for Lab Testing')]
class InitLab extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('migrate:fresh');
        $this->call('db:seed', ['--class' => 'LabSeeder']);
        $this->call('voucher:generate', ['--class' => 'LabSeeder']);
    }
}
