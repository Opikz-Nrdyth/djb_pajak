<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("../php/config.php");

// Proteksi halaman: hanya pengguna 'user' yang login yang bisa mengakses
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'user') {
    // Jika tidak, redirect ke halaman login
    $_SESSION['flash_message_djp_list'] = "Sesi tidak valid atau Anda tidak memiliki izin.";
    $_SESSION['flash_message_djp_list_type'] = "error";
    header("Location: " . BASE_URL_USER_ROOT . "login.php");
    exit();
}

require_once '../php/db_connect.php';

$id_user_logged_in = $_SESSION['id_pengguna'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_data_djp_to_delete = intval($_GET['id']);

    // Mulai transaksi database
    $conn->begin_transaction();

    try {
        // Langkah 1: Verifikasi bahwa data_djp_user ini milik pengguna yang login
        $stmt_check_owner = $conn->prepare("SELECT id_pengguna FROM data_djp_user WHERE id_data_djp = ?");
        if (!$stmt_check_owner) {
            throw new Exception("Gagal mempersiapkan verifikasi kepemilikan: " . $conn->error);
        }
        $stmt_check_owner->bind_param("i", $id_data_djp_to_delete);
        $stmt_check_owner->execute();
        $result_owner = $stmt_check_owner->get_result();
        if ($result_owner->num_rows === 0) {
            throw new Exception("Data objek pajak tidak ditemukan.");
        }
        $owner_data = $result_owner->fetch_assoc();
        if ($owner_data['id_pengguna'] != $id_user_logged_in) {
            throw new Exception("Anda tidak memiliki izin untuk menghapus data objek pajak ini.");
        }
        $stmt_check_owner->close();

        // Langkah 2: Hapus semua data perhitungan terkait di tabel `perhitungan_pajak`
        $stmt_delete_perhitungan = $conn->prepare("DELETE FROM perhitungan_pajak WHERE id_data_djp = ?");
        if ($stmt_delete_perhitungan) {
            $stmt_delete_perhitungan->bind_param("i", $id_data_djp_to_delete);
            if (!$stmt_delete_perhitungan->execute()) {
                // Tidak dianggap error fatal jika tidak ada perhitungan, tapi error jika query gagal
                if ($stmt_delete_perhitungan->errno) {
                    throw new Exception("Gagal menghapus data perhitungan terkait: " . $stmt_delete_perhitungan->error);
                }
            }
            $stmt_delete_perhitungan->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan perhitungan terkait: " . $conn->error);
        }

        // Langkah 3: Hapus data objek pajak dari tabel `data_djp_user`
        $stmt_delete_djp = $conn->prepare("DELETE FROM data_djp_user WHERE id_data_djp = ? AND id_pengguna = ?");
        if ($stmt_delete_djp) {
            $stmt_delete_djp->bind_param("ii", $id_data_djp_to_delete, $id_user_logged_in);
            if ($stmt_delete_djp->execute()) {
                if ($stmt_delete_djp->affected_rows > 0) {
                    $conn->commit(); // Commit transaksi jika semua berhasil
                    $_SESSION['flash_message_djp_list'] = "Data objek pajak (ID: $id_data_djp_to_delete) dan semua perhitungan terkait berhasil dihapus.";
                    $_SESSION['flash_message_djp_list_type'] = "success";
                } else {
                    // Ini bisa terjadi jika data sudah dihapus di proses lain atau ID tidak valid
                    throw new Exception("Tidak ada data objek pajak yang dihapus. Data mungkin sudah tidak ada.");
                }
            } else {
                throw new Exception("Gagal menghapus data objek pajak: " . $stmt_delete_djp->error);
            }
            $stmt_delete_djp->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan data objek pajak: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback(); // Rollback jika ada kesalahan
        $_SESSION['flash_message_djp_list'] = "Terjadi kesalahan: " . $e->getMessage();
        $_SESSION['flash_message_djp_list_type'] = "error";
    }
} else {
    $_SESSION['flash_message_djp_list'] = "Permintaan tidak valid untuk menghapus data objek pajak.";
    $_SESSION['flash_message_djp_list_type'] = "error";
}

if ($conn) {
    $conn->close();
}

// Redirect kembali ke halaman daftar objek pajak pengguna
header("Location: " . BASE_URL_USER_ROOT . "user/daftar_objek_pajak.php");
exit();
