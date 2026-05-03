# ResumeCraft

**Build ATS-Ready Resumes in Minutes.**

ResumeCraft is a full-featured, web-based resume builder that lets users create, customise, preview, and export professional resumes with zero design experience required. Every template is optimised to pass Applicant Tracking Systems (ATS).

---

## ✨ Features

| Feature | Details |
|---|---|
| 🎨 **8 Modern Templates** | Classic, Modern, Minimal, Sidebar Left, Sidebar Right, Executive, Tech, Creative |
| 🤖 **ATS Optimised** | Semantic HTML, standard fonts, zero layout hacks — built to pass ATS scanners |
| 🎛️ **Full Customisation** | Change colours, fonts, font sizes, line height, spacing, and section order in real time |
| 📄 **One-Click PDF Export** | High-fidelity PDF via mPDF that matches the live preview exactly |
| 👁️ **Live Preview** | See every change instantly without saving |
| ☁️ **Auto Save** | AJAX auto-save every 30 seconds — never lose your work |
| 🔐 **Auth System** | Registration, login, persistent sessions (30-day remember-me) |
| 👤 **User Roles & Plans** | `user` / `admin` roles; `free` / `pro` / `enterprise` plans |
| 🔗 **Public Sharing** | Generate a public share link for your resume |

---

## 🛠️ Tech Stack

- **Backend** — PHP 8+ (no framework), PDO/MySQL
- **Frontend** — Bootstrap 5, Bootstrap Icons, vanilla JS
- **PDF Generation** — [mPDF](https://mpdf.github.io/) `^8.2.5`
- **UUIDs** — [ramsey/uuid](https://github.com/ramsey/uuid) `^4.7`
- **Database** — MySQL 5.7+ / MariaDB 10.4+
- **Dependency Management** — Composer

---

## 📁 Project Structure

```
resume/
├── api/
│   ├── customization.php   # Save resume styling preferences
│   ├── export.php          # PDF generation endpoint (enforces download limits)
│   └── resume.php          # CRUD operations for resume data
├── admin/
│   ├── index.php              # Admin dashboard (stats, plan distribution, recent activity)
│   ├── users.php              # User management (search, edit, reset password, delete)
│   ├── resumes.php            # Resume management (search, preview, delete)
│   ├── site-settings.php      # Branding: app name, logo, colour, flags
│   ├── plan-settings.php      # Plan limits and PDF download caps
│   ├── logs.php               # Activity log viewer (colour-coded, filterable)
│   ├── layout_start.php       # Shared admin sidebar + topbar (open)
│   └── layout_end.php         # Shared admin layout (close)
├── assets/                 # CSS, JS, images
├── config/
│   ├── app.php             # App constants, env settings
│   └── database.php        # DB credentials
├── includes/
│   ├── Auth.php            # Session & authentication logic
│   ├── Database.php        # PDO singleton
│   ├── PlanLimits.php      # DB-driven plan limit helper (resumes + exports)
│   ├── SiteSettings.php    # Key-value site settings helper (app name, logo, flags)
│   ├── ActivityLog.php     # Audit trail writer and reader
│   ├── Resume.php          # Core resume model & queries
│   ├── functions.php       # Shared helper functions
│   ├── header.php          # Shared HTML header/navbar
│   └── footer.php          # Shared HTML footer
├── templates/              # Resume template directories (8 templates)
│   ├── classic/
│   ├── modern/
│   ├── minimal/
│   ├── sidebar-left/
│   ├── sidebar-right/
│   ├── executive/
│   ├── tech/
│   └── creative/
├── composer.json
├── database.sql                  # Full schema + seed data
├── migration_plan_settings.sql   # Adds plan_settings & resume_export_log tables
├── migration_admin_features.sql  # Adds site_settings & activity_log tables
├── requirements.php              # Environment / requirements checker
├── dashboard.php                # User resume dashboard
├── editor.php                   # Resume editor with live preview
├── preview.php                  # Public / shareable resume preview
├── settings.php                 # Account settings
├── index.php                    # Landing page
├── login.php
├── register.php
└── logout.php
```

---

## 🚀 Installation

### Prerequisites

| Requirement | Minimum | Notes |
|---|---|---|
| PHP | 8.0 | CLI & web SAPI |
| ext-pdo | any | Database abstraction |
| ext-pdo_mysql | any | MySQL driver |
| ext-mbstring | any | Multi-byte strings (mPDF) |
| ext-gd | any | Image processing (mPDF PDF export) |
| ext-xml | any | XML parsing (mPDF) |
| ext-zip | any | ZIP support (mPDF font handling) |
| ext-json | any | JSON encode/decode |
| ext-curl | any | HTTP client |
| ext-session | any | Session management |
| ext-fileinfo | any | File type detection (uploads) |
| MySQL / MariaDB | 5.7 / 10.4 | Database server |
| Composer | 2.x | PHP dependency manager |
| Apache / Nginx | any | `mod_rewrite` must be enabled on Apache |

Install all required PHP extensions at once (Debian/Ubuntu):

```bash
sudo apt install php8.4-{mbstring,gd,xml,zip,curl,pdo,pdo-mysql,fileinfo}
```

### Steps

**1. Clone the repository**

```bash
git clone https://github.com/your-username/resume.git /var/www/html/resume
cd /var/www/html/resume
```

**2. Check requirements**

Open `http://your-domain.com/requirements.php` in your browser (or run `php requirements.php` in the terminal) to verify all extensions and permissions are satisfied before proceeding.

> ⚠️ Delete or restrict access to `requirements.php` once you're done.

**3. Install PHP dependencies**

```bash
composer install --optimize-autoloader
```

> **Note (PHP 8.4+):** The `composer.json` requires `mpdf/mpdf ^8.2.5` (the first release with PHP 8.4 support). Ensure `ext-gd` is installed before running Composer.

**4. Set up the database**

```bash
mysql -u root -p < database.sql
```

**5. Run the plan-settings migration**

```bash
mysql -u your_db_user -p resume_maker < migration_plan_settings.sql
```

This creates two tables:
- `plan_settings` — admin-editable limits per plan
- `resume_export_log` — per-download audit log for rate-limiting

**6. Run the admin features migration**

```bash
mysql -u your_db_user -p resume_maker < migration_admin_features.sql
```

This creates two tables:
- `site_settings` — admin-editable branding and feature flags
- `activity_log` — full audit trail for all user and admin actions

Edit `config/app.php` and update the constants:

```php
define('APP_NAME', 'ResumeCraft');
define('APP_URL',  'http://your-domain.com');
define('APP_ENV',  'production'); // change from 'development'
```

Edit `config/database.php` and add your credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'resume_maker');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

**7. Set directory permissions**

```bash
mkdir -p uploads
chmod -R 775 uploads
```

**8. Configure your web server**

Point the document root to `/var/www/html/resume`. The included `.htaccess` handles URL rewriting for Apache automatically.

---

## 🔑 Default Credentials

> ⚠️ Change these immediately after first login!

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `Admin@123` |
| Demo User | `demo@example.com` | `Demo@123` |

---

## 📋 Plan Limits

> These are the **default** values. All limits can be changed at any time from the admin settings panel without touching code.

| Plan | Max Resumes | PDF Downloads |
|---|---|---|
| Free | 3 | Configurable (can be restricted or capped) |
| Pro | 20 | Configurable |
| Enterprise | Unlimited | Unlimited |

**Admin-configurable controls** (via `admin/plan-settings.php`):
- **Max resumes per plan** — ceiling for how many resumes each plan can create; enforced in `dashboard.php`
- **PDF exports enabled** — toggle PDF export on/off per plan; enforced in `api/export.php`
- **Max PDF downloads per day** — daily cap per user; tracked in `resume_export_log`, enforced in `api/export.php` (HTTP 429 when exceeded)
- **Plan assignment** — promote or demote any user's plan from the admin panel

**How enforcement works:**
1. `PlanLimits::maxResumes($plan)` is called on dashboard load — the "New Resume" button is disabled when the limit is hit
2. `PlanLimits::canExport($userId, $plan)` is checked before every PDF render — returns `false` if disabled or daily cap exceeded
3. Each successful download is logged to `resume_export_log` via `PlanLimits::logExport()`
4. All limits fall back to the hardcoded constants in `config/app.php` if the `plan_settings` table is missing (safe during migration)

---

## 📄 License

This project is open-source and available under the [MIT License](LICENSE).
