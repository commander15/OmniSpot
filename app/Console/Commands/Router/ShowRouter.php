<?php

namespace App\Console\Commands\Router;

use App\Models\ZoneRouter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use function Laravel\Prompts\table;

#[Signature('router:show {id? : Router ID} {--zone= : Zone ID}')]
#[Description('Show details for a specific router or list routers within a zone.')]
class ShowRouter extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $routerId = $this->argument('id');
        $zoneId = $this->option('zone');

        if ($routerId) {
            $this->show($routerId);
            return;
        }

        if ($zoneId) {
            $this->list($zoneId);
            return;
        }

        $this->error('Please provide a Router ID or a --zone= ID.');
    }

    private function show(string $routerId)
    {
        $router = ZoneRouter::with('zone')->find($routerId);

        if (!$router) {
            $this->error("Router with ID {$routerId} not found.");
            return;
        }

        // Transforms single router model into vertical key-value rows for the table
        $data = [
            ['Attribute' => 'ID', 'Details' => $router->id],
            ['Attribute' => 'Name', 'Details' => $router->name],
            ['Attribute' => 'Net Address', 'Details' => $router->net_address],
            ['Attribute' => 'MAC Address', 'Details' => $router->mac_address],
            ['Attribute' => 'Zone ID', 'Details' => $router->zone_id],
            ['Attribute' => 'Zone Name', 'Details' => $router->zone?->name ?? 'N/A'],
        ];

        table(['Attribute', 'Details'], $data);
    }

    private function list(string $zoneId)
    {
        // Select all required fields since your table rendering expects them
        $routers = ZoneRouter::where('zone_id', $zoneId)
            ->get(['id', 'name', 'net_address', 'mac_address']);

        if ($routers->isEmpty()) {
            $this->info("No routers found for Zone ID {$zoneId}.");
            return;
        }

        table(['ID', 'NAME', 'NET_ADDRESS', 'MAC_ADDRESS'], $routers->toArray());
    }
}
