<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HnrAccount;
use App\Models\HnrObligation;
use App\Models\User;
use App\Services\Hnr\HnrService;
use App\Services\Hnr\HnrSettings;
use Illuminate\Console\Command;

final class EvaluateHnrCommand extends Command
{
    protected $signature = 'hnr:evaluate';

    protected $description = 'Review HnR obligations, warning expiry and reversible download restrictions.';

    public function handle(HnrService $hnr, HnrSettings $settings): int
    {
        if (! $settings->enabled()) {
            $this->info('HnR is disabled. No accounts changed.');

            return self::SUCCESS;
        }
        User::query()->whereIn('id', HnrObligation::query()->select('user_id'))
            ->orWhereIn('id', HnrAccount::query()->select('user_id'))
            ->eachById(fn (User $user) => $hnr->evaluate($user));
        $this->info('HnR review completed.');

        return self::SUCCESS;
    }
}
