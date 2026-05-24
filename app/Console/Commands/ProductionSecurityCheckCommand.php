<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Security\ProductionSecurityReadinessService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

final class ProductionSecurityCheckCommand extends Command
{
    protected $signature = 'nextgn:production-check';

    protected $description = 'Validate production security hardening readiness for NextGN.';

    public function handle(ProductionSecurityReadinessService $readinessService): int
    {
        $result = $readinessService->evaluate();

        $rows = array_map(
            static fn (array $check): array => [
                $check['passed'] ? 'PASS' : 'FAIL',
                $check['label'],
                $check['passed'] ? 'OK' : $check['details'],
            ],
            $result['checks']
        );

        $this->table(['Status', 'Check', 'Details'], $rows);

        if ($result['passed']) {
            $this->info('Production security readiness check passed.');

            return SymfonyCommand::SUCCESS;
        }

        $this->error('Production security readiness check failed. Resolve all failed checks before public exposure.');

        return SymfonyCommand::FAILURE;
    }
}
