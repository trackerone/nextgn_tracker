<?php

declare(strict_types=1);

namespace App\Support\Security;

final class AdminTwoFactorSession
{
    public const PENDING_USER_ID = 'auth.admin_two_factor.pending_user_id';

    public const SETUP_AUTHORIZED_AT = 'auth.admin_two_factor.setup_authorized_at';

    public const VERIFIED_AT = 'auth.admin_two_factor.verified_at';
}
