<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class GeneratePasskeysCommand extends Command
{
    protected $signature = 'tracker:generate-passkeys';

    protected $description = 'Generate tracker passkeys for users missing them.';

    public function handle(): int
    {
        $count = 0;

        User::query()
            ->whereNull('passkey')
            ->chunkById(200, function (Collection $users) use (&$count): void {
                foreach ($users as $user) {
                    $user->ensurePasskey();
                    $count++;
                }
            });

        $this->info(sprintf('Generated passkeys for %d user(s).', $count));

        return self::SUCCESS;
    }
}
