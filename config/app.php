<?php
define('APP_NAME',    'ResumeCraft');
define('APP_URL',     'http://localhost/resume');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development'); // change to 'production' when live

define('SESSION_NAME',     'rc_session');
define('SESSION_LIFETIME', 86400 * 30); // 30 days

define('TEMPLATES_PATH', __DIR__ . '/../templates/');
define('ASSETS_URL',     APP_URL . '/assets');
define('UPLOAD_PATH',    __DIR__ . '/../uploads/');
define('UPLOAD_URL',     APP_URL . '/uploads');

define('PDF_MARGIN_TOP',    15);
define('PDF_MARGIN_BOTTOM', 15);
define('PDF_MARGIN_LEFT',   15);
define('PDF_MARGIN_RIGHT',  15);

define('MAX_RESUMES_FREE', 3);
define('MAX_RESUMES_PRO',  20);

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
