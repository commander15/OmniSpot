<?php

namespace App\Console\Commands\Voucher;

use App\Models\InternetVoucher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('voucher:show {id : Voucher ID}')]
#[Description('Command description')]
class ShowVoucher extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $voucher = InternetVoucher::find($this->argument('id'));
        if ($voucher) {
            GenerateVoucher::printVoucher($voucher, $voucher->zone, $voucher->bundle, [
                ['ZONE ID', $voucher->zone->id],
            ]);
        } else {
            $this->error('Error: voucher not found');
        }
    }
}
