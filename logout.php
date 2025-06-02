<?php
// logout.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

// Definisikan BASE_URL jika belum ada (untuk redirect ke login.php di root)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    // Menentukan root path dari script saat ini (logout.php ada di root)
    $root_path = dirname($_SERVER['SCRIPT_NAME']);
    // Pastikan $root_path diakhiri dengan slash jika bukan root domain
    if ($root_path === '/' || $root_path === '\\') {
        $root_path = '/';
    } else {
        $root_path = rtrim($root_path, '/\\') . '/';
    }
    if ($root_path === '//') $root_path = '/';
    define('BASE_URL', $protocol . $host . $root_path);
}


// Redirect ke halaman login
header("Location: " . BASE_URL . "login.php");
exit();
