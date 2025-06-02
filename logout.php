<?php
// logout.php

// 1. Mulai atau lanjutkan session yang ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Hapus semua variabel session
$_SESSION = array();

// 3. Hancurkan session
// Jika ingin menghancurkan session sepenuhnya, hapus juga cookie session.
// Catatan: Ini akan menghancurkan session, dan bukan hanya data session!
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

// Akhirnya, hancurkan session.
session_destroy();

// 4. Arahkan pengguna ke halaman login publik
// Kita asumsikan halaman login utama adalah 'login.php' di root direktori.
// Jika Anda memiliki halaman landing sebelum login (misalnya index.php), Anda bisa arahkan ke sana.
header("Location: login.php");
exit;
