# Roles and permissions — Sysop control plane

NextGN uses the existing role hierarchy: newbie (0), user (1), power user (2–4), uploader (5–7), moderator (8–9), admin (10–11), and sysop (12). The UI groups legacy slugs into these classes without migrating users or changing their role levels.

## Editable capabilities

Sysop can configure three feature permissions at /sysop/permissions:
- torrent.upload — the upload form, analyse endpoint, web/API submit, and uploader navigation
- forum.access — forum pages and writes, including moderation operations on forum content
- messages.access — private message pages, writes, and their personal menu entry

The server enforces access, not just the navigation. This permission matrix is **not** a substitute for existing download policy, moderator checks, staff access, authentication, security controls, account restrictions, or sysop-only settings.

## Defaults and precedence

1. Banned/disabled accounts are denied.
2. Sysop always retains these three features; cannot be locked out from this page.
3. An individual permission override, if present, wins over the class setting.
4. The class setting is stored in site_settings under access.roles.<class> as JSON.
5. Without a stored value, config/security.php role_permissions supplies defaults.

An individual override can allow, deny, or inherit. Inherit removes the row from user_permission_overrides. Neither role changes nor direct edits of administrative powers are possible on this page.

## Operations

- All mutations require authenticated sysop level 12, CSRF protection, validation, a nonblank reason and an audit record.
- The role matrix is persisted in site_settings; individual overrides in user_permission_overrides.
- Run php artisan migrate as part of deployment before making requests to the upgraded app.
- Changes apply on the next request with no server restart.
- The audit log records the actor, previous and new values and the reason.

To reverse a role override, restore its checkboxes and save; to reverse a personal override, select **Inherit** for that member and feature.

## Protected boundaries

Staff role, admin pages, moderation, sysop powers, security enforcement, HnR, ratio rules and torrent visibility are still governed by their existing policies. Future capabilities should only be exposed as editable after every related web/API entry point and background action is wired to the central permission decision and tested.
