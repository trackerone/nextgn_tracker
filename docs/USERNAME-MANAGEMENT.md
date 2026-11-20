# Username changes — admin-controlled

Account holders cannot rename themselves. The **Account settings** form shows their current username as read-only. \`PATCH /account/settings\` also rejects a submitted \`name\` that differs from the current username, including attempts by Admin and Sysop through their own profile form. Account holders can still change email/password using the existing current-password requirement.

A member requests the change from staff. Admin or Sysop opens **User administration** under the staff account menu (Sysop also has a link under **Site Settings**) and finds the member by email address or numeric account ID. Changes require a non-empty reason, and the new username cannot duplicate an existing username.

Only Admin/Sysop can submit \`PATCH /admin/users/{user}/name\`. Admin cannot rename Admin/Sysop targets; Sysop may rename administrators. Neither Admin nor Sysop can rename their own account. A successful rename writes a \`user.username_changed\` audit event with actor, target, previous name, new name and reason, and preserves the stable public profile slug, email, role and credentials.

This is an administrative rename feature, not a self-service username change or a role permission that ordinary members can obtain.
