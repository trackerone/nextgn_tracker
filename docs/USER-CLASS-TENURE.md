# Optional membership tenure for automatic user classes

The calculated classes **Newbie**, **User**, **Power User**, and **Elite** can optionally require a minimum account age. This is independent of account/permission roles such as \`uploader\`, \`moderator\`, \`admin\`, and \`sysop\` and does not grant elevated permissions.

**Where:** Sysop → Site Settings → Roles, permissions, ratio & balances → User class thresholds.

One **Require membership time for automatic promotion** checkbox turns on class-age requirements. The thresholds are configurable in **whole months**, defaulting to 3 for User, 12 for Power User, and 36 for Elite. They must satisfy User ≤ Power User ≤ Elite. The checkbox is **off by default**, preserving current ratio-only classification for existing and new accounts until Sysop enables it.

When switched on, an account must satisfy **both** applicable age and existing ratio/download criteria:

- Before User's minimum tenure, qualifying new accounts are classified as **Newbie**, not Elite, even if Sysop has awarded starting credit.
- At User tenure, qualifying accounts can become **User**.
- At Power User tenure, accounts with sufficient ratio and downloaded bytes can become **Power User**.
- At Elite tenure, accounts with sufficient ratio can become **Elite**.
- Below the established minimum User ratio, the existing **Leech** classification still takes precedence.
- No traffic history, after reaching User tenure, keeps the existing **User** baseline rather than granting higher classes.
- Assigned moderator/admin/Sysop roles and the underlying access matrix are unaffected. Staff accounts still have their own earned ratio/tenure class; their operational role remains visible separately in the header and account overview.

Account age is calculated from \`users.created_at\`, checked dynamically when displaying the class. Calendar months use Laravel's month-no-overflow logic (not a fixed 30-day approximation). No scheduler or new table is required. Changes are persisted in the existing \`settings\` table under \`ratio.tenure_*\`, with the existing \`settings.ratio.updated\` audit event.

Existing admin users can still adjust the original ratio thresholds, but only Sysop can change account-age rules, including by direct PATCH. When disabled, previously configured month values are retained.

The completed-torrents detail page displays uploaded and downloaded traffic as formatted **GB**, using the same 1024³-byte conversion as the existing header, instead of raw byte counts. Stored byte values and all ratio calculations remain unchanged.
