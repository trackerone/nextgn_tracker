# Registration modes and invitations

NextGN's registration policy belongs to the owner/Sysop, **not the deployment environment**. It is stored in `site_settings` as `registration.mode` and takes effect on the next request. The default is `invite`, regardless of whether the app runs locally, on a test server, or in production.

## Sysop control

At **Sysop → Roles & permissions → Invitations** (`/sysop/permissions?section=invitations#invitations`), the owner chooses one of three modes:

| Mode | Guest registration | Invite code |
| --- | --- | --- |
| `closed` | Disabled; signup link hidden and GET /register returns 404, POST /register returns 403 | Not accepted while closed |
| `invite` | Enabled only with a valid code | Required |
| `open` | Enabled without a code | Optional; valid codes still credit the inviter |

Mode changes require authenticated Sysop, CSRF protection, a validated selection, and a nonblank audit reason. The update and audit log are committed in one database transaction (`sysop.registration.mode_updated`). Staff/Admins may still generate invitations using their existing delegated invitation tools, but **only Sysop** may change the overall signup policy.

An invalid or unknown stored mode falls back to `invite`, never `open`. No `APP_ENV=local` exception remains. Closed signup does **not** lock out existing accounts.

## Schema and invite consumption

- `site_settings.registration.mode`: one of `closed`, `invite`, `open`.
- `invites.code`: generated unique token.
- `invites.inviter_user_id`: nullable owner of the invite.
- `invites.max_uses` / `uses`: available capacity.
- `invites.expires_at`: optional expiry.
- `invites.notes`: optional staff context.
- `users.invited_by_id`: inviter link when an invitation was used.

When a code is supplied, the register action locks the invitation for the database transaction, validates existence, expiration and remaining uses, and increments `uses` only after creating the account. Invalid supplied codes are rejected even in open mode. Without a code in open mode, `invited_by_id` remains null.

## Staff workflow

- Staff members (moderator level or above) may create and list invitations under `/admin/invites`.
- Sysop can also create invitations under the Invitations section of the permissions control plane.
- Existing members can review their sent invitations at `/account/invites`.

## Tests

- `tests/Feature/Auth/RegistrationInviteTest.php`: valid, invalid, expired, exhausted and double-consumed invitations.
- `tests/Feature/Auth/RegistrationModesTest.php`: fail-safe default, all three modes, direct POST denial when closed, optional referral codes, permissions and audit trail.
- `tests/Feature/Admin/InviteAdminControllerTest.php`: existing staff delegation and permission boundaries.

No database schema migration is required for this setting: `SiteSettingsRepository` provides the default and saves the new key on first Sysop change.
