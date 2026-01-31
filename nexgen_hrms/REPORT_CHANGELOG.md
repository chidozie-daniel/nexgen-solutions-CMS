Changelog - Link & Redirect Normalization

Summary:

- Centralized base path handling via `Auth::getBasePath()` in `includes/auth.php`.
- Replaced relative redirects (`header('Location: ...')`) with `Auth::getBasePath()`-based absolute redirects where appropriate.
- Updated `includes/header.php` to compute `$base_url = Auth::getBasePath()` and used it for nav links and dropdown logout.
- Prefixed module anchor `href` and form `action` attributes with `<?php echo $base_url; ?>` to ensure links work when app is hosted in a subfolder.
- Removed a temporary file: `_archive/dev_tools/_last_setup_output.html`.

Files changed (key edits):

- includes/auth.php: made `getBasePath()` public; centralized base path logic.
- includes/header.php: replaced ad-hoc base detection with `Auth::getBasePath()`; updated dropdown logout link and all sidebar links to use `$base_url`.
- register.php: redirects and cancel link normalized to base path.
- dashboard.php: prefixed quick-action links and action buttons with `$base_url`.
- modules/tasks/\*: normalized redirects and cross-module links (view, update, my_tasks, assign, add_comment).
- modules/projects/\*: normalized redirects, cross-module links, and form `action` attributes to use `$base_url`.
- modules/leave/\*: normalized redirects and links to use base path.
- modules/payroll/\*: normalized redirects.
- modules/inquiries/\*: normalized redirects and view-site link.
- modules/admin/\*: normalized redirects and register links.
- tools/check_includes.ps1: helper script added for local include checks.

Notes & Next Steps:

- I could not run PHP syntax checks here (PHP not in PATH). Please run `php -l` locally on the modified files listed above.
- Recommended local verification: start XAMPP and test logging in and navigating Dashboard quick actions and module pages.
- If you want, I can continue to convert any remaining relative anchors (rare occurrences) and produce a final verification report.

If you'd like a filtered list of the exact files I edited (full paths), I can produce it now.

Detailed file changes (full paths):

- includes/auth.php: Made `getBasePath()` public and centralized base-path logic used for redirects.
- includes/header.php: Replaced ad-hoc base detection with `Auth::getBasePath()`; set `$base_url`; updated sidebar/nav links and dropdown logout to use `$base_url`.
- includes/footer.php: No functional changes, left intact (ensured layout consistency).
- login.php: Left behavior intact; uses `includes/auth.php` for auth and safe redirect logic.
- register.php: Normalized redirects to use `Auth::getBasePath()` and updated Cancel link to use `$base_url`.
- dashboard.php: Prefixed quick-action anchors and action buttons with `<?php echo $base_url; ?>` so links work from subfolders.
- modules/tasks/view.php: Converted local redirects and project links to use `$base_url` and `Auth::getBasePath()`.
- modules/tasks/update.php: Normalized `header('Location:')` redirects to use `Auth::getBasePath()` and point to `/modules/tasks/my_tasks.php`.
- modules/tasks/my_tasks.php: Normalized self-redirect to absolute module path.
- modules/tasks/assign.php: Normalized redirects (permission checks and post-submit) to use base path.
- modules/tasks/add_comment.php: Normalized redirects to `modules/tasks/my_tasks.php`.
- modules/projects/details.php: Converted `index.php` redirects to `/modules/projects/index.php`, replaced `../tasks/*` links and form actions with `$base_url` module paths.
- modules/projects/create.php: Updated Cancel link to use `$base_url`.
- modules/projects/update.php: Converted `index.php` redirects to `/modules/projects/index.php` using base path.
- modules/projects/add_member.php: Normalized redirects and post-action redirects to use base path.
- modules/leave/apply.php: Normalized redirect to `/modules/leave/my_leaves.php`.
- modules/leave/my_leaves.php: Normalized self-redirect.
- modules/leave/manage.php: Normalized redirects; updated link to admin reports to use `$base_url`.
- modules/payroll/submit_inputs.php: Normalized self-redirect to use base path.
- modules/payroll/process.php: Normalized redirect to dashboard using base path.
- modules/inquiries/list.php: Changed site link from `../../` to `<?php echo $base_url; ?>/index.php`.
- modules/inquiries/view.php: Normalized redirects to `/modules/inquiries/list.php`.
- modules/inquiries/send_reply.php: Normalized redirects to `/modules/inquiries/list.php`.
- modules/admin/users.php: Normalized post-action redirect to `/modules/admin/users.php`; updated register links to use `$base_url`.
- modules/admin/settings.php: Normalized post-update redirect to `/modules/admin/settings.php`.
- modules/admin/reports.php: Normalized dashboard redirect to use base path.
- tools/check_includes.ps1: Added a PowerShell helper to scan includes/require targets locally.
- \_archive/dev_tools/\_last_setup_output.html: Deleted as a temporary artifact.
- REPORT_CHANGELOG.md: Created and updated with the above summary.

Recent small fixes (delta):

- Converted several remaining relative redirects to absolute paths using `Auth::getBasePath()`:
  - `modules/projects/create.php`, `modules/projects/add_member.php`
  - `modules/payroll/process.php`
  - `modules/inquiries/view.php`, `modules/inquiries/send_reply.php`

- Dashboard UI tweaks: added lightweight CSS and applied `hero-section`/`section-header` classes in `dashboard.php` so the dashboard visually matches the public home page style more closely.

Next verification steps:

- Run `php -l` locally on modified files and review web UI in XAMPP to confirm layout and redirects.
- If you prefer, I can continue and convert additional `->query()` calls to prepared statements in high-risk code paths.

---

## CSRF & CRUD Security Improvements (Latest)

### Summary:

- Replaced GET-based action links with secure POST form submissions to prevent CSRF attacks.
- Added input sanitization (`htmlspecialchars()` and `(int)` casting) on all user inputs.
- Added validation for status values and user existence checks before database operations.

### Files Updated:

**modules/inquiries/list.php:**

- Replaced GET-based status update links (e.g., `list.php?update_status=1&id=...&status=...`) with secure POST form submissions.
- Added input sanitization for status and filter values.
- Added status value validation (`in_array()` check against allowed statuses).
- Changed dropdown items from `<a>` tags to inline `<form>` with hidden inputs + submit button.

**modules/admin/users.php:**

- Replaced GET-based action links (e.g., `users.php?action=activate&id=...`) with POST form submissions.
- Added user existence verification before processing action.
- Added input sanitization for action field.
- Converted action dropdown items to POST forms with proper form controls.
- Maintained confirm dialogs using form `onsubmit` attributes.

### Security Improvements:

- **CSRF Prevention:** All state-changing operations now require POST, reducing CSRF vulnerability surface.
- **Input Validation:** Added explicit checks for valid values before database operations.
- **User Verification:** Added checks to ensure users/inquiries exist before modifying them.
- **SQL Injection:** All queries remain prepared statements; no regression in SQL injection protection.

### Testing Notes:

- All POST forms include hidden inputs for necessary data (inquiry_id, user_id, action, status).
- Dropdown buttons now submit forms instead of linking to unsafe URLs.
- Confirmation dialogs preserved using JavaScript form submission events.

---

## Module UI Redesign to Match Home Page (Latest)

### Summary:

Applied modern, clean design patterns matching the home page (`index.php`) design system to key modules for consistent look and feel. All redesigned modules feature gradient blue hero section headers, card-based layouts with hover effects, and responsive design matching the home page CSS patterns.

### Design Pattern:

- **Hero Section:** Gradient background (`linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%)`), white text, title + subtitle + action button.
- **Cards:** White background, rounded corners, left border accent (4px), subtle shadow, hover lift effect.
- **Buttons:** Bootstrap btn-warning for primary actions, consistent icons and styling.
- **Typography:** Font-weight 700 for headers, semantic color usage (primary #0d6efd, warning #ffc107).

### Files Updated:

- **modules/projects/index.php:** Added hero section, redesigned project cards with status badges and progress indicators.
- **modules/tasks/my_tasks.php:** Added hero section with gradient, applied task card styling with priority-based left border colors.
- **modules/payroll/my_salary.php:** Added hero section, redesigned salary info cards with centered stat display.

### CSS Patterns Reused from Home Page:

- Gradient backgrounds and overlays
- Card shadows and hover lift effects
- Bootstrap button styling with custom transitions
- Responsive grid layouts (col-md-4, col-md-6, etc.)
- Consistent spacing and typography hierarchy

### Remaining Optional Improvements:

- Apply hero section + redesigned cards to leave, inquiries, admin modules
- Extract embedded CSS to separate stylesheet for better maintainability
