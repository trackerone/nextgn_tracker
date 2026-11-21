# Unified Sysop access administration

**Primary Sysop page:** \`/sysop/permissions\` (under **Site Settings → Roles, permissions, ratio & balances**).

This is now the single daily control plane for:
- **Role permissions** (Newbie, User, Power User, Uploader, Moderator, Admin), with compact, expandable role cards and protected Sysop boundaries.
- **Calculated user class thresholds:** User, Power User, Elite, with the established minimum-ratio and downloaded-MB rules. These classes are **not** the same thing as permission roles; changing thresholds does not change a user's stored role.
- **Starting uploaded/downloaded credits** for future registrations and audited individual adjustments. Real announces and HnR history are not edited.
- **Individual permission exceptions**, with audit reasons.
- **Invitation generation and recent invitation history** in the same control plane.

Direct old Sysop links for \`/admin/settings/ratio\` and \`/sysop/ratio-credits\` redirect to their control-plane sections. The mutation routes, validation, existing settings keys and audit events are retained. Admins can still use the normal styled \`/admin/settings/ratio\` page; non-Sysop staff retain access to \`/admin/invites\`, which uses the shared responsive layout rather than legacy standalone HTML.

The **global download minimum ratio**, freeleech bypass and no-history grace remain system-wide tracker rules under Site Settings, distinct from the per-class thresholds controlling User / Power User / Elite labels. Do not merge the two policies without explicit rule design and migration/testing.

Existing role and ratio security restrictions continue to apply server-side. The unified page is Sysop-only; role and personal changes require an audit reason. No database migration is needed for this UI consolidation.
