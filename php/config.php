<?php

// Definisikan BASE_URL_ADMIN
if (!defined('BASE_URL_ADMIN')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_path_parts = explode('/', dirname($_SERVER['SCRIPT_NAME']));
    array_pop($script_path_parts);
    $root_path = implode('/', $script_path_parts) . '/';
    if ($root_path === '//') $root_path = '/';
    define('BASE_URL_ADMIN', $protocol . $host . $root_path);
}
