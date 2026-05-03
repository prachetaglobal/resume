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
│   ├── export.php          # PDF generation endpoint
│   └── resume.php          # CRUD operations for resume data
├── assets/                 # CSS, JS, images
├── config/
│   ├── app.php             # App constants, env settings
│   └── database.php        # DB credentials
├── includes/
│   ├── Auth.php            # Session & authentication logic
│   ├── Database.php        # PDO singleton
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
├── database.sql            # Full schema + seed data
├── requirements.php        # Environment / requirements checker
├── dashboard.php           # User resume dashboard
├── editor.php              # Resume editor with live preview
├── preview.php             # Public / shareable resume preview
├── settings.php            # Account settings
├── index.php               # Landing page
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

**5. Configure the application**

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

**6. Set directory permissions**

```bash
mkdir -p uploads
chmod -R 775 uploads
```

**7. Configure your web server**

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

| Plan | Max Resumes |
|---|---|
| Free | 3 |
| Pro | 20 |
| Enterprise | Unlimited |

---

## 📄 License

This project is open-source and available under the [MIT License](LICENSE).
