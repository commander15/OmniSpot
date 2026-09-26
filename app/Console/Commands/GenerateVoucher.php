<?php

namespace App\Console\Commands;

use App\Models\InternetBundle;
use App\Models\InternetPackage;
use App\Models\Zone;
use App\Services\InternetVoucherService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

#[Signature('voucher:generate {phone? : Phone number} {zone? : Zone ID} {bundle? : Bundle ID}')]
#[Description('Generate an internet voucher for a given customer, zone, and bundle')]
class GenerateVoucher extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InternetVoucherService $service): int
    {
        // 1. Gather inputs via argument fallback or interactive prompts
        $phoneNumber = $this->argument('phone') ?? text(
            label: 'Customer Phone Number',
            placeholder: 'e.g., +1234567890',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 8 => 'The phone number must be at least 8 characters.',
                default => null,
            }
        );

        $zoneId = $this->argument('zone') ?? select(
            label: 'Select Target Zone',
            options: Zone::pluck('name', 'id')->all(),
            scroll: 10
        );

        $zone = Zone::find($zoneId);

        $packageId = null;
        if (!$this->argument('bundle')) {
            $packageId = select(
                label: 'Select Internet Package',
                options: $zone->packages()
                    ->pluck('internet_packages.name', 'internet_packages.id')
                    ->all(),
                scroll: 10
            );
        }

        $bundleId = $this->argument('bundle') ?? select(
            label: 'Select Internet Bundle',
            options: InternetBundle::where('package_id', $packageId)
                ->pluck('name', 'id')
                ->all(),
            scroll: 10
        );

        // 2. Fetch & validate models
        $bundle = InternetBundle::find($bundleId);

        if (!$zone || !$bundle) {
            $this->error('Invalid Zone or Internet Bundle selection.');
            return self::FAILURE;
        }

        // 3. Generate voucher with a loading spinner
        $voucher = spin(
            fn () => $service->generateVoucher($phoneNumber, $zone, $bundle),
            'Generating voucher...'
        );

        // 4. Output structured result
        $this->newLine();
        $this->info('Voucher generated successfully!');

        table(
            headers: ['Attribute', 'Details'],
            rows: [
                ['User Name', $voucher->username ?? $voucher['username'] ?? 'N/A'],
                ['User Name', $voucher->password ?? $voucher['password'] ?? 'N/A'],
                ['Phone Number', $phoneNumber],
                ['Zone', $zone->name],
                ['Bundle', $bundle->name],
                ['Expires At', $voucher->expires_at ?? $voucher['expires_at'] ?? 'N/A'],
            ]
        );

        return self::SUCCESS;
    }
}

