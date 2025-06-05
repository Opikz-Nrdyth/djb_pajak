<?php
// admin/hapus_data_objek_pajak.php (Skrip Aksi)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definisikan BASE_URL_ADMIN jika belum ada (untuk redirect)
if (!defined('BASE_URL_ADMIN')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_path_parts = explode('/', dirname($_SERVER['SCRIPT_NAME']));
    array_pop($script_path_parts);
    $root_path = implode('/', $script_path_parts) . '/';
    if ($root_path === '//') $root_path = '/';
    define('BASE_URL_ADMIN', $protocol . $host . $root_path);
}

// Proteksi: hanya admin yang bisa mengakses
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    $_SESSION['flash_message_type'] = "error";
    // Redirect ke halaman login atau dashboard admin jika role tidak sesuai
    header("Location: " . BASE_URL_ADMIN . "login.php");
    exit();
}

require_once '../php/db_connect.php';

$id_objek_to_delete = null;
$id_user_redirect = null; // Untuk redirect kembali ke halaman edit user yang benar

if (isset($_GET['id_objek']) && is_numeric($_GET['id_objek']) && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $id_objek_to_delete = intval($_GET['id_objek']);
    $id_user_redirect = intval($_GET['user_id']);

    // Mulai transaksi database
    $conn->begin_transaction();

    try {
        // Langkah 1: Hapus semua data perhitungan terkait di tabel `perhitungan_pajak`
        // yang merujuk ke id_data_djp (objek pajak) yang akan dihapus.
        $stmt_delete_perhitungan = $conn->prepare("DELETE FROM perhitungan_pajak WHERE id_data_djp = ?");
        if ($stmt_delete_perhitungan) {
            $stmt_delete_perhitungan->bind_param("i", $id_objek_to_delete);
            // Eksekusi penghapusan perhitungan. Tidak dianggap error fatal jika tidak ada perhitungan terkait.
            // Namun, jika query itu sendiri gagal, itu adalah error.
            if (!$stmt_delete_perhitungan->execute()) {
                if ($stmt_delete_perhitungan->errno) { // Hanya throw exception jika ada error MySQL
                    throw new Exception("Gagal menghapus data perhitungan terkait: " . $stmt_delete_perhitungan->error);
                }
            }
            $stmt_delete_perhitungan->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan perhitungan terkait: " . $conn->error);
        }

        // Langkah 2: Hapus data objek pajak dari tabel `data_djp_user`
        // Pastikan juga bahwa objek pajak ini memang milik user_id yang dikirim (untuk keamanan tambahan jika diperlukan)
        // Namun, karena ini admin yang menghapus, cukup pastikan id_objeknya valid.
        $stmt_delete_objek = $conn->prepare("DELETE FROM data_djp_user WHERE id_data_djp = ? AND id_pengguna = ?");
        if ($stmt_delete_objek) {
            $stmt_delete_objek->bind_param("ii", $id_objek_to_delete, $id_user_redirect);
            if ($stmt_delete_objek->execute()) {
                if ($stmt_delete_objek->affected_rows > 0) {
                    $conn->commit(); // Commit transaksi jika semua berhasil
                    $_SESSION['flash_message'] = "Data objek pajak (ID: $id_objek_to_delete) dan perhitungan terkait berhasil dihapus.";
                    $_SESSION['flash_message_type'] = "success";
                } else {
                    // Ini bisa terjadi jika data sudah dihapus di proses lain atau ID tidak valid/tidak cocok dengan user_id
                    throw new Exception("Tidak ada data objek pajak yang dihapus. Data mungkin sudah tidak ada atau tidak sesuai dengan pengguna yang dipilih.");
                }
            } else {
                throw new Exception("Gagal menghapus data objek pajak: " . $stmt_delete_objek->error);
            }
            $stmt_delete_objek->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan data objek pajak: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback(); // Rollback jika ada kesalahan
        $_SESSION['flash_message'] = "Terjadi kesalahan saat menghapus data objek pajak: " . $e->getMessage();
        $_SESSION['flash_message_type'] = "error";
    }
} else {
    $_SESSION['flash_message'] = "Permintaan tidak valid untuk menghapus data objek pajak. ID Objek atau ID Pengguna tidak lengkap.";
    $_SESSION['flash_message_type'] = "error";
    // Jika $id_user_redirect tidak terset, arahkan ke halaman umum
    if (empty($id_user_redirect)) {
        header("Location: " . BASE_URL_ADMIN . "admin/kelola_wajib_pajak.php");
        exit();
    }
}

if ($conn) {
    $conn->close();
}

// Redirect kembali ke halaman edit wajib pajak untuk user yang bersangkutan
if ($id_user_redirect) {
    header("Location: " . BASE_URL_ADMIN . "admin/edit_wajib_pajak.php?id=" . $id_user_redirect);
} else {
    // Fallback jika id_user_redirect tidak ada
    header("Location: " . BASE_URL_ADMIN . "admin/kelola_wajib_pajak.php");
}
exit();
