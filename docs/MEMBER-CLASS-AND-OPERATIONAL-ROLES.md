# Earned member classes vs assigned operational roles

Hermes audit: class-versus-role coupling. User accounts contain two different concepts:

| Concept | Source of truth | Changes automatically? | Grants backend permissions? |
| --- | --- | --- | --- |
| **Earned member class** | `User::userClass()`, via effective ratio/traffic, optional membership tenure and disabled-account state | Yes, when the calculation changes | **No** |
| **Assigned permission role** | `users.role` (normalized), with the recognized `roles.slug` relation for legacy/default-user accounts | No. Explicit audited administration or authorized CLI/repository assignment only | Yes, according to the existing Sysop role/override matrix |
| **Staff or Sysop authority** | Recognized operational role identifiers or explicitly assigned staff flag | No automatic promotion | Yes, gated by staff policies, level authorization, and protected Sysop routes |

**Earned classes:** Leech, Newbie, User, Power User and Elite. Class thresholds are configured separately from download ratio enforcement. Adjusting class ratio or account-age thresholds changes a label, not an assigned permission role or role ID.

**Operational roles:** user, explicitly assigned power_user, uploader, moderator, admin and sysop; legacy `user1`–`user4`, `uploader1`–`uploader3`, `mod1`/`mod2`, `admin1`/`admin2`, and `sysop` still resolve through recognized identifiers.

## Compatibility and hardening

- The existing **power_user** operational role remains supported if assigned explicitly. However, legacy `user2`, `user3`, `user4` are member-class-like legacy labels and now use the ordinary **user** permission matrix rather than inheriting the separate **power_user** permission matrix. Existing individual overrides continue to apply.
- The numeric `roles.level` field is no longer a fallback authorization grant when a related role slug is unknown. Numeric 10/12 on an arbitrary role cannot create admin/Sysop authority. Recognized slugs retain their historic levels.
- An explicitly designated legacy `staff` role retains the moderator member-feature defaults, including invitations, without receiving Admin or Sysop powers. This requires actual staff designation, not a numeric role level.
- The staff-only middleware and `User::staff()` use the same explicit designation semantics. A deliberately marked `roles.is_staff` or `users.is_staff` remains a staff grant (to preserve existing supported staff administration). A high numeric class level *alone* is not a staff grant.
- A stale `role_id` pointing at an old staff role cannot override a newer explicit non-default non-staff `users.role`. Demotion by the existing admin UI synchronizes `users.role`, `role_id` and `is_staff`, as before.
- `User::userClass()` reports the earned class even for staff; their header keeps the operational label (Moderator, Admin, Sysop) to avoid changing the meaning of privileged badges. The account overview displays **Assigned permission role** and **Earned member class** in separate fields.
- Sysop's protected role and administrative capabilities remain uneditable in the member permission matrix. Automatic class changes never write the role column.

## Release and verification

No database migrations, new role columns, scheduler or asset build are required. Keep an eye on any imported legacy `user2`–`user4` accounts that relied on the separate Power User **role** permissions: they now inherit the User permission row; use an explicitly assigned `power_user` role or an audited individual override only when elevated member feature access is genuinely intended.

Verify with `tests/Feature/MemberClassAuthoritySeparationTest.php` and the existing role/demotion/tenure tests. HP deploy:

```bash
cd ~/nextgn_tracker
git pull --ff-only origin main
php artisan optimize:clear
```
