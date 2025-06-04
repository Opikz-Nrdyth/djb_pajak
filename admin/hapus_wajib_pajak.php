<?php
// admin/hapus_wajib_pajak.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("../php/config.php");

// Proteksi: hanya admin yang bisa mengakses
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    $_SESSION['flash_message_type'] = "error";
    header("Location: " . BASE_URL_ADMIN . "login.php");
    exit();
}

require_once '../php/db_connect.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_wp_to_delete = intval($_GET['id']);

    // Mulai transaksi
    $conn->begin_transaction();

    try {
        // 1. Hapus data terkait di `perhitungan_pajak` (jika ada, melalui `id_data_djp`)
        // Kita perlu mendapatkan id_data_djp terlebih dahulu
        $id_data_djp_list = [];
        $stmt_get_djp_ids = $conn->prepare("SELECT id_data_djp FROM data_djp_user WHERE id_pengguna = ?");
        if ($stmt_get_djp_ids) {
            $stmt_get_djp_ids->bind_param("i", $id_wp_to_delete);
            $stmt_get_djp_ids->execute();
            $result_djp_ids = $stmt_get_djp_ids->get_result();
            while ($row_djp_id = $result_djp_ids->fetch_assoc()) {
                $id_data_djp_list[] = $row_djp_id['id_data_djp'];
            }
            $stmt_get_djp_ids->close();
        } else {
            throw new Exception("Gagal mengambil ID Data DJP: " . $conn->error);
        }

        if (!empty($id_data_djp_list)) {
            $placeholders = implode(',', array_fill(0, count($id_data_djp_list), '?'));
            $types_djp = str_repeat('i', count($id_data_djp_list));

            $stmt_delete_perhitungan = $conn->prepare("DELETE FROM perhitungan_pajak WHERE id_data_djp IN ($placeholders)");
            if ($stmt_delete_perhitungan) {
                $stmt_delete_perhitungan->bind_param($types_djp, ...$id_data_djp_list);
                if (!$stmt_delete_perhitungan->execute()) {
                    throw new Exception("Gagal menghapus data perhitungan terkait: " . $stmt_delete_perhitungan->error);
                }
                $stmt_delete_perhitungan->close();
            } else {
                throw new Exception("Gagal mempersiapkan penghapusan perhitungan: " . $conn->error);
            }
        }

        // 2. Hapus data di `data_djp_user`
        $stmt_delete_djp = $conn->prepare("DELETE FROM data_djp_user WHERE id_pengguna = ?");
        if ($stmt_delete_djp) {
            $stmt_delete_djp->bind_param("i", $id_wp_to_delete);
            if (!$stmt_delete_djp->execute()) {
                throw new Exception("Gagal menghapus data DJP pengguna: " . $stmt_delete_djp->error);
            }
            $stmt_delete_djp->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan data DJP: " . $conn->error);
        }

        // 3. Hapus data pengguna dari tabel `pengguna`
        $stmt_delete_user = $conn->prepare("DELETE FROM pengguna WHERE id_pengguna = ? AND role = 'user'");
        if ($stmt_delete_user) {
            $stmt_delete_user->bind_param("i", $id_wp_to_delete);
            if ($stmt_delete_user->execute()) {
                if ($stmt_delete_user->affected_rows > 0) {
                    $conn->commit();
                    $_SESSION['flash_message'] = "Data wajib pajak (ID: $id_wp_to_delete) dan semua data terkait berhasil dihapus.";
                    $_SESSION['flash_message_type'] = "success";
                } else {
                    throw new Exception("Tidak ada data wajib pajak yang dihapus (mungkin ID tidak ditemukan atau bukan role 'user').");
                }
            } else {
                throw new Exception("Gagal menghapus data wajib pajak: " . $stmt_delete_user->error);
            }
            $stmt_delete_user->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan wajib pajak: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = "Terjadi kesalahan saat menghapus data: " . $e->getMessage();
        $_SESSION['flash_message_type'] = "error";
    }
} else {
    $_SESSION['flash_message'] = "Permintaan tidak valid untuk menghapus data.";
    $_SESSION['flash_message_type'] = "error";
}

$conn->close();
header("Location: " . BASE_URL_ADMIN . "admin/kelola_wajib_pajak.php");
exit();
