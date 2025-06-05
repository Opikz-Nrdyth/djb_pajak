<?php
// admin/buat_perhitungan.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definisi BASE_URL_ADMIN
if (!defined('BASE_URL_ADMIN')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_path_parts = explode('/', dirname($_SERVER['SCRIPT_NAME']));
    array_pop($script_path_parts);
    $root_path = implode('/', $script_path_parts) . '/';
    if ($root_path === '//') $root_path = '/';
    define('BASE_URL_ADMIN', $protocol . $host . $root_path);
}

// Proteksi halaman admin
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL_ADMIN . "login.php");
    exit();
}

// Data Admin dari Session
$id_admin_logged_in = $_SESSION['id_pengguna'];
$nama_admin_session = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin');
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan untuk halaman ini
$page_title_admin = "Buat Perhitungan Pajak Baru";
$current_page = 'tagihan_perhitungan';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$all_users_with_objek = []; // Untuk dropdown pertama (pilih pengguna)
$objek_pajak_user_selected = []; // Untuk dropdown kedua (pilih objek pajak spesifik)

// Ambil daftar semua pengguna (role 'user' dan status 'aktif')
$sql_users = "SELECT id_pengguna, nama_lengkap, nik 
              FROM pengguna 
              WHERE role = 'user' AND status_akun = 'aktif' 
              ORDER BY nama_lengkap ASC";
$result_users = $conn->query($sql_users);
if ($result_users && $result_users->num_rows > 0) {
    while ($row_user = $result_users->fetch_assoc()) {
        // Cek apakah user ini punya data objek pajak
        $stmt_check_objek = $conn->prepare("SELECT COUNT(*) as total_objek FROM data_djp_user WHERE id_pengguna = ?");
        if ($stmt_check_objek) {
            $stmt_check_objek->bind_param("i", $row_user['id_pengguna']);
            $stmt_check_objek->execute();
            $count_objek = $stmt_check_objek->get_result()->fetch_assoc()['total_objek'];
            if ($count_objek > 0) {
                $all_users_with_objek[] = $row_user;
            }
            $stmt_check_objek->close();
        }
    }
}

// Jika ada pengguna yang dipilih dari dropdown pertama (via GET dari JS atau POST sebelumnya)
$selected_user_id_for_objek = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_pengguna_wp'])) { // Jika form disubmit dengan id_pengguna_wp
    $selected_user_id_for_objek = intval($_POST['id_pengguna_wp']);
} elseif (isset($_GET['get_objek_for_user_id']) && is_numeric($_GET['get_objek_for_user_id'])) { // Jika AJAX request
    // Ini bagian untuk AJAX request, akan mengembalikan JSON
    header('Content-Type: application/json');
    $userId = intval($_GET['get_objek_for_user_id']);
    $sql_objek_ajax = "SELECT id_data_djp, alamat_objek_pajak, luas_bangunan, luas_tanah 
                       FROM data_djp_user 
                       WHERE id_pengguna = ? 
                       ORDER BY alamat_objek_pajak ASC";
    $stmt_objek_ajax = $conn->prepare($sql_objek_ajax);
    $data_to_return = ['objek_pajak' => []];
    if ($stmt_objek_ajax) {
        $stmt_objek_ajax->bind_param("i", $userId);
        $stmt_objek_ajax->execute();
        $result_objek_ajax = $stmt_objek_ajax->get_result();
        while ($row_objek = $result_objek_ajax->fetch_assoc()) {
            // Format luas untuk ditampilkan di dropdown/data attribute jika perlu
            $row_objek['luas_bangunan_formatted'] = number_format(floatval($row_objek['luas_bangunan']), 2, '.', '');
            $row_objek['luas_tanah_formatted'] = number_format(floatval($row_objek['luas_tanah']), 2, '.', '');
            $data_to_return['objek_pajak'][] = $row_objek;
        }
        $stmt_objek_ajax->close();
    }
    echo json_encode($data_to_return);
    $conn->close();
    exit(); // Hentikan eksekusi setelah mengirim JSON
}

// Jika ada pengguna terpilih (baik dari POST atau GET untuk repopulate), ambil objek pajaknya
if ($selected_user_id_for_objek) {
    $stmt_objek_selected = $conn->prepare("SELECT id_data_djp, alamat_objek_pajak, luas_bangunan, luas_tanah FROM data_djp_user WHERE id_pengguna = ? ORDER BY alamat_objek_pajak ASC");
    if ($stmt_objek_selected) {
        $stmt_objek_selected->bind_param("i", $selected_user_id_for_objek);
        $stmt_objek_selected->execute();
        $result_objek_selected = $stmt_objek_selected->get_result();
        while ($row_objek_s = $result_objek_selected->fetch_assoc()) {
            $objek_pajak_user_selected[] = $row_objek_s;
        }
        $stmt_objek_selected->close();
    }
}


// Proses form submission untuk buat perhitungan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_perhitungan'])) {
    $id_pengguna_wp = isset($_POST['id_pengguna_wp']) ? intval($_POST['id_pengguna_wp']) : null; // ID Pengguna dari select pertama
    $id_data_djp = isset($_POST['id_data_djp']) ? intval($_POST['id_data_djp']) : null; // ID Objek Pajak dari select kedua
    $periode_pajak_tahun = isset($_POST['periode_pajak_tahun']) ? intval($_POST['periode_pajak_tahun']) : null;
    $njop_bangunan_per_meter = isset($_POST['njop_bangunan_per_meter']) ? floatval(str_replace('.', '', str_replace(',', '.', $_POST['njop_bangunan_per_meter']))) : 0.00;
    $njop_tanah_per_meter = isset($_POST['njop_tanah_per_meter']) ? floatval(str_replace('.', '', str_replace(',', '.', $_POST['njop_tanah_per_meter']))) : 0.00;
    $njoptkp = isset($_POST['njoptkp']) ? floatval(str_replace('.', '', str_replace(',', '.', $_POST['njoptkp']))) : 0.00;
    $persentase_pbb = isset($_POST['persentase_pbb']) ? floatval(str_replace(',', '.', $_POST['persentase_pbb'])) / 100 : 0.0050;
    $catatan_admin = isset($_POST['catatan_admin']) ? $conn->real_escape_string(trim($_POST['catatan_admin'])) : null;
    $status_verifikasi_data_user = isset($_POST['status_verifikasi_data_user']) ? $conn->real_escape_string(trim($_POST['status_verifikasi_data_user'])) : 'belum_lengkap';
    $status_perhitungan = 'draft';

    if (empty($id_pengguna_wp)) $errors[] = "Wajib Pajak harus dipilih.";
    if (empty($id_data_djp)) $errors[] = "Objek Pajak spesifik harus dipilih.";
    if (empty($periode_pajak_tahun) || !is_numeric($periode_pajak_tahun) || $periode_pajak_tahun < 1900 || $periode_pajak_tahun > (date("Y") + 5)) {
        $errors[] = "Tahun Periode Pajak tidak valid.";
    }
    if (!in_array($status_verifikasi_data_user, ['lengkap', 'belum_lengkap', 'perlu_revisi', 'diverifikasi'])) {
        $errors[] = "Status Verifikasi Data tidak valid.";
    }

    $luas_bangunan = 0;
    $luas_tanah = 0;
    if ($id_data_djp && empty($errors)) {
        $stmt_luas = $conn->prepare("SELECT luas_bangunan, luas_tanah FROM data_djp_user WHERE id_data_djp = ? AND id_pengguna = ?");
        if ($stmt_luas) {
            $stmt_luas->bind_param("ii", $id_data_djp, $id_pengguna_wp); // Pastikan objek pajak milik user yang dipilih
            $stmt_luas->execute();
            $result_luas = $stmt_luas->get_result();
            if ($objek_pajak = $result_luas->fetch_assoc()) {
                $luas_bangunan = floatval($objek_pajak['luas_bangunan']);
                $luas_tanah = floatval($objek_pajak['luas_tanah']);
                if ($luas_bangunan <= 0 && $luas_tanah <= 0) {
                    $errors[] = "Luas bangunan dan luas tanah untuk objek pajak terpilih adalah 0 atau tidak valid.";
                }
            } else {
                $errors[] = "Data objek pajak dengan ID terpilih tidak ditemukan untuk pengguna yang dipilih.";
            }
            $stmt_luas->close();
        } else {
            $errors[] = "Gagal mengambil data luas objek pajak: " . $conn->error;
        }
    }

    if (empty($errors)) {
        $total_njop_bangunan = $luas_bangunan * $njop_bangunan_per_meter;
        $total_njop_tanah = $luas_tanah * $njop_tanah_per_meter;
        $njop_total_objek_pajak = $total_njop_bangunan + $total_njop_tanah;
        $njkp = $njop_total_objek_pajak - $njoptkp;
        if ($njkp < 0) $njkp = 0;
        $jumlah_pbb_terutang = $njkp * $persentase_pbb;

        $sql_insert = "INSERT INTO perhitungan_pajak 
                        (id_data_djp, id_admin_pereview, periode_pajak_tahun, 
                         njop_bangunan_per_meter, njop_tanah_per_meter, 
                         total_njop_bangunan, total_njop_tanah, njop_total_objek_pajak,
                         njoptkp, njkp, persentase_pbb, jumlah_pbb_terutang, 
                         catatan_admin, status_verifikasi_data_user, status_perhitungan, tanggal_perhitungan)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert) {
            $stmt_insert->bind_param(
                "iiiddddddddssss",
                $id_data_djp,
                $id_admin_logged_in,
                $periode_pajak_tahun,
                $njop_bangunan_per_meter,
                $njop_tanah_per_meter,
                $total_njop_bangunan,
                $total_njop_tanah,
                $njop_total_objek_pajak,
                $njoptkp,
                $njkp,
                $persentase_pbb,
                $jumlah_pbb_terutang,
                $catatan_admin,
                $status_verifikasi_data_user,
                $status_perhitungan
            );

            if ($stmt_insert->execute()) {
                $_SESSION['flash_message'] = "Perhitungan pajak baru (ID: " . $stmt_insert->insert_id . ") berhasil disimpan.";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . BASE_URL_ADMIN . "admin/tagihan_perhitungan.php");
                exit();
            } else {
                $errors[] = "Gagal menyimpan perhitungan pajak: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        } else {
            $errors[] = "Gagal mempersiapkan penyimpanan: " . $conn->error;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - Admin Panel InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/css/admin-buat-perhitungan.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body>
    <div class="admin-page-wrapper">
        <button class="admin-sidebar-toggle-button" id="admin-sidebar-toggle" aria-label="Toggle Sidebar" aria-expanded="true">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>

        <aside class="admin-sidebar" id="admin-main-sidebar">
            <div class="admin-sidebar-header">
                <img src="<?php echo $foto_profil_admin_session; ?>" alt="Foto Profil <?php echo $nama_admin_session; ?>" class="admin-sidebar-profile-pic"
                    onerror="this.onerror=null;this.src='https://placehold.co/80x80/003366/ffffff?text=<?php echo substr($nama_admin_session, 0, 1); ?>';">
                <span class="admin-sidebar-user-name"><?php echo $nama_admin_session; ?></span>
                <span class="admin-sidebar-user-role">(Administrator)</span>
            </div>
            <nav class="admin-sidebar-nav">
                <ul>
                    <li class="<?php echo (isset($current_page) && $current_page == 'dashboard_admin') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/index.php">
                            <i class="fas fa-tachometer-alt fa-fw"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="has-submenu <?php echo (isset($current_parent_page) && $current_parent_page == 'pengelolaan_pajak') ? 'open active' : ''; ?>">
                        <a href="#" class="submenu-toggle">
                            <i class="fas fa-edit fa-fw"></i>
                            <span>Menu Pengelolaan Pajak</span>
                            <span class="submenu-arrow"><i class="fas fa-chevron-down"></i></span>
                        </a>
                        <ul class="admin-submenu">
                            <li class="<?php echo (isset($current_page) && $current_page == 'management_wajib_pajak') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php">
                                    <i class="fas fa-users fa-fw"></i> Management Wajib Pajak
                                </a>
                            </li>
                            <li class="<?php echo ($current_page == 'tagihan_perhitungan') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php">
                                    <i class="fas fa-file-invoice-dollar fa-fw"></i> Tagihan & Perhitungan
                                </a>
                            </li>
                            <li class="has-submenu <?php echo (isset($current_parent_page_sub) && $current_parent_page_sub == 'laporan_pajak') ? 'open active' : ''; ?>">
                                <a href="#" class="submenu-toggle">
                                    <i class="fas fa-chart-bar fa-fw"></i>
                                    <span>Laporan Pajak</span>
                                    <span class="submenu-arrow"><i class="fas fa-chevron-down"></i></span>
                                </a>
                                <ul class="admin-submenu">
                                    <li class="<?php echo (isset($current_page) && $current_page == 'laporan_detail') ? 'active' : ''; ?>">
                                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/laporan_detail.php"><i class="fas fa-file-alt fa-fw"></i> Laporan Detail</a>
                                    </li>
                                    <li class="<?php echo (isset($current_page) && $current_page == 'laporan_harian') ? 'active' : ''; ?>">
                                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/laporan_harian.php"><i class="fas fa-calendar-day fa-fw"></i> Laporan Harian</a>
                                    </li>
                                    <li class="<?php echo (isset($current_page) && $current_page == 'laporan_bulanan') ? 'active' : ''; ?>">
                                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/laporan_bulanan.php"><i class="fas fa-calendar-alt fa-fw"></i> Laporan Bulanan</a>
                                    </li>
                                    <li class="<?php echo (isset($current_page) && $current_page == 'rekapitulasi') ? 'active' : ''; ?>">
                                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/rekapitulasi_laporan.php"><i class="fas fa-clipboard-list fa-fw"></i> Rekapitulasi</a>
                                    </li>
                                </ul>
                            </li>
                            <li class="<?php echo (isset($current_page) && $current_page == 'pengaturan_admin') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/pengaturan_admin.php">
                                    <i class="fas fa-cog fa-fw"></i>
                                    <span>Pengaturan</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL_ADMIN; ?>logout.php">
                            <i class="fas fa-sign-out-alt fa-fw"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="admin-sidebar-footer">
                <p>&copy; <?php echo date("Y"); ?> InfoPajak</p>
            </div>
        </aside>

        <main class="admin-main-content" id="admin-main-content-area">
            <header class="admin-content-header">
                <div class="header-left">
                    <h1 class="header-page-title"><?php echo htmlspecialchars($page_title_for_header); ?></h1>
                    <p class="header-welcome-text">Selamat Datang, <?php echo $nama_admin_session; ?>!</p>
                </div>
                <div class="header-right">
                    <div class="admin-profile-dropdown">
                        <a href="#" class="admin-profile-link-header" id="profile-dropdown-toggle">
                            <img src="<?php echo $foto_profil_admin_session; ?>" alt="Avatar <?php echo $nama_admin_session; ?>" class="admin-avatar-header"
                                onerror="this.onerror=null;this.src='https://placehold.co/32x32/cccccc/000000?text=<?php echo substr($nama_admin_session, 0, 1); ?>';">
                            <span><?php echo $nama_admin_session; ?></span>
                            <i class="fas fa-chevron-down dropdown-arrow-header"></i>
                        </a>
                        <div class="profile-dropdown-menu" id="profile-menu">
                            <a href="<?php echo BASE_URL_ADMIN; ?>admin/pengaturan_admin.php?tab=profil">Profil Saya</a>
                            <a href="<?php echo BASE_URL_ADMIN; ?>admin/pengaturan_admin.php?tab=keamanan">Ganti Password</a>
                            <hr>
                            <a href="<?php echo BASE_URL_ADMIN; ?>logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="admin-content-inner">
                <div class="admin-card">
                    <div class="admin-card-header">
                        Formulir Perhitungan Pajak Bangunan Baru
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin: 15px 0;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form action="buat_perhitungan.php" method="POST" class="admin-form calculation-form">
                        <fieldset>
                            <legend>Pilih Wajib Pajak & Objek Pajak</legend>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="id_pengguna_wp">Wajib Pajak (Pengguna)</label>
                                    <select name="id_pengguna_wp" id="id_pengguna_wp" required>
                                        <option value="">-- Pilih Wajib Pajak --</option>
                                        <?php foreach ($all_users_with_objek as $user): ?>
                                            <option value="<?php echo $user['id_pengguna']; ?>"
                                                <?php echo (isset($_POST['id_pengguna_wp']) && $_POST['id_pengguna_wp'] == $user['id_pengguna']) ? 'selected' : (isset($_GET['user_id_for_objek']) && $_GET['user_id_for_objek'] == $user['id_pengguna'] ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($user['nama_lengkap']) . " (NIK: " . htmlspecialchars($user['nik']) . ")"; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="id_data_djp">Objek Pajak Spesifik</label>
                                    <select name="id_data_djp" id="id_data_djp" required <?php echo empty($objek_pajak_user_selected) && !isset($_POST['id_pengguna_wp']) ? 'disabled' : ''; ?>>
                                        <option value="">-- Pilih Objek Pajak --</option>
                                        <?php foreach ($objek_pajak_user_selected as $objek): ?>
                                            <option value="<?php echo $objek['id_data_djp']; ?>"
                                                data-luas-bangunan="<?php echo htmlspecialchars(number_format(floatval($objek['luas_bangunan']), 2, '.', '')); ?>"
                                                data-luas-tanah="<?php echo htmlspecialchars(number_format(floatval($objek['luas_tanah']), 2, '.', '')); ?>"
                                                <?php echo (isset($_POST['id_data_djp']) && $_POST['id_data_djp'] == $objek['id_data_djp']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($objek['alamat_objek_pajak'] ? substr($objek['alamat_objek_pajak'], 0, 70) . '...' : 'ID Objek: ' . $objek['id_data_djp']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Pilih Wajib Pajak terlebih dahulu untuk memuat objek pajaknya.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="luas_bangunan_display">Luas Bangunan (m²)</label>
                                    <input type="text" id="luas_bangunan_display" readonly disabled placeholder="Pilih objek pajak">
                                </div>
                                <div class="form-group">
                                    <label for="luas_tanah_display">Luas Tanah (m²)</label>
                                    <input type="text" id="luas_tanah_display" readonly disabled placeholder="Pilih objek pajak">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Parameter Perhitungan PBB</legend>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="periode_pajak_tahun">Tahun Periode Pajak</label>
                                    <input type="number" id="periode_pajak_tahun" name="periode_pajak_tahun" value="<?php echo isset($_POST['periode_pajak_tahun']) ? htmlspecialchars($_POST['periode_pajak_tahun']) : date("Y"); ?>" required min="1900" max="<?php echo date("Y") + 5; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="status_verifikasi_data_user">Status Verifikasi Data User</label>
                                    <select id="status_verifikasi_data_user" name="status_verifikasi_data_user" required>
                                        <option value="belum_lengkap" <?php echo (isset($_POST['status_verifikasi_data_user']) && $_POST['status_verifikasi_data_user'] == 'belum_lengkap') ? 'selected' : ''; ?>>Belum Lengkap</option>
                                        <option value="perlu_revisi" <?php echo (isset($_POST['status_verifikasi_data_user']) && $_POST['status_verifikasi_data_user'] == 'perlu_revisi') ? 'selected' : ''; ?>>Perlu Revisi</option>
                                        <option value="lengkap" <?php echo (isset($_POST['status_verifikasi_data_user']) && $_POST['status_verifikasi_data_user'] == 'lengkap') ? 'selected' : ''; ?>>Lengkap</option>
                                        <option value="diverifikasi" <?php echo (isset($_POST['status_verifikasi_data_user']) && $_POST['status_verifikasi_data_user'] == 'diverifikasi') ? 'selected' : ''; ?>>Diverifikasi</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="njop_bangunan_per_meter">NJOP Bangunan / m² (Rp)</label>
                                    <input type="text" class="input-currency" id="njop_bangunan_per_meter" name="njop_bangunan_per_meter" value="<?php echo isset($_POST['njop_bangunan_per_meter']) ? htmlspecialchars($_POST['njop_bangunan_per_meter']) : '0'; ?>" required placeholder="Contoh: 1.500.000">
                                </div>
                                <div class="form-group">
                                    <label for="njop_tanah_per_meter">NJOP Tanah / m² (Rp)</label>
                                    <input type="text" class="input-currency" id="njop_tanah_per_meter" name="njop_tanah_per_meter" value="<?php echo isset($_POST['njop_tanah_per_meter']) ? htmlspecialchars($_POST['njop_tanah_per_meter']) : '0'; ?>" required placeholder="Contoh: 2.000.000">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="njoptkp">NJOPTKP (Rp)</label>
                                    <input type="text" class="input-currency" id="njoptkp" name="njoptkp" value="<?php echo isset($_POST['njoptkp']) ? htmlspecialchars($_POST['njoptkp']) : '12.000.000'; ?>" required placeholder="Contoh: 12.000.000">
                                </div>
                                <div class="form-group">
                                    <label for="persentase_pbb">Persentase PBB (%)</label>
                                    <input type="text" id="persentase_pbb" name="persentase_pbb" value="<?php echo isset($_POST['persentase_pbb']) ? htmlspecialchars($_POST['persentase_pbb']) : '0,5'; ?>" required placeholder="Contoh: 0,5">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="catatan_admin">Catatan Admin</label>
                                <textarea id="catatan_admin" name="catatan_admin" rows="3"><?php echo isset($_POST['catatan_admin']) ? htmlspecialchars($_POST['catatan_admin']) : ''; ?></textarea>
                            </div>
                        </fieldset>

                        <div class="calculation-summary" id="calculation-summary-section" style="display:none;">
                            <h4>Ringkasan Perhitungan (Estimasi)</h4>
                            <p>Total NJOP Bangunan: Rp <span id="summary_total_njop_bangunan">0</span></p>
                            <p>Total NJOP Tanah: Rp <span id="summary_total_njop_tanah">0</span></p>
                            <p>NJOP Total Objek Pajak: Rp <span id="summary_njop_total_objek_pajak">0</span></p>
                            <p>NJOP Kena Pajak (NJKP): Rp <span id="summary_njkp">0</span></p>
                            <p><strong>Jumlah PBB Terutang: Rp <span id="summary_jumlah_pbb_terutang">0</span></strong></p>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="button btn-info" id="btn-preview-calculation">Preview Perhitungan</button>
                            <button type="submit" name="simpan_perhitungan" class="button btn-primary">Simpan Perhitungan</button>
                            <a href="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php" class="button btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const idPenggunaWpSelect = document.getElementById('id_pengguna_wp');
            const idDataDjpSelect = document.getElementById('id_data_djp');
            const luasBangunanDisplay = document.getElementById('luas_bangunan_display');
            const luasTanahDisplay = document.getElementById('luas_tanah_display');
            const btnPreview = document.getElementById('btn-preview-calculation');
            const summarySection = document.getElementById('calculation-summary-section');

            function fetchObjekPajak(userId) {
                idDataDjpSelect.innerHTML = '<option value="">Memuat objek pajak...</option>';
                idDataDjpSelect.disabled = true;
                luasBangunanDisplay.value = '';
                luasTanahDisplay.value = '';
                summarySection.style.display = 'none';


                if (!userId) {
                    idDataDjpSelect.innerHTML = '<option value="">-- Pilih Wajib Pajak Dahulu --</option>';
                    return;
                }

                fetch(`buat_perhitungan.php?get_objek_for_user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        idDataDjpSelect.innerHTML = '<option value="">-- Pilih Objek Pajak --</option>';
                        if (data.objek_pajak && data.objek_pajak.length > 0) {
                            data.objek_pajak.forEach(objek => {
                                const option = document.createElement('option');
                                option.value = objek.id_data_djp;
                                option.textContent = objek.alamat_objek_pajak ? String(objek.alamat_objek_pajak).substring(0, 70) + '...' : `ID Objek: ${objek.id_data_djp}`;
                                option.setAttribute('data-luas-bangunan', objek.luas_bangunan_formatted);
                                option.setAttribute('data-luas-tanah', objek.luas_tanah_formatted);
                                idDataDjpSelect.appendChild(option);
                            });
                            idDataDjpSelect.disabled = false;
                        } else {
                            idDataDjpSelect.innerHTML = '<option value="">-- Tidak ada objek pajak terdaftar untuk user ini --</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching objek pajak:', error);
                        idDataDjpSelect.innerHTML = '<option value="">Gagal memuat objek pajak</option>';
                    });
            }

            function updateLuasDisplayFromSelectedObjek() {
                const selectedOption = idDataDjpSelect.options[idDataDjpSelect.selectedIndex];
                if (selectedOption && selectedOption.value !== "") {
                    let luasBangunan = selectedOption.getAttribute('data-luas-bangunan');
                    let luasTanah = selectedOption.getAttribute('data-luas-tanah');
                    luasBangunanDisplay.value = parseFloat(luasBangunan.replace(/[^0-9.]/g, '')).toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' m²';
                    luasTanahDisplay.value = parseFloat(luasTanah.replace(/[^0-9.]/g, '')).toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' m²';
                } else {
                    luasBangunanDisplay.value = 'Pilih objek pajak';
                    luasTanahDisplay.value = 'Pilih objek pajak';
                }
            }

            if (idPenggunaWpSelect) {
                idPenggunaWpSelect.addEventListener('change', function() {
                    fetchObjekPajak(this.value);
                });
                // Jika ada nilai POST untuk id_pengguna_wp (misalnya setelah validasi gagal), panggil fetchObjekPajak
                if (idPenggunaWpSelect.value) {
                    fetchObjekPajak(idPenggunaWpSelect.value);
                    // Beri sedikit waktu agar dropdown objek pajak terisi sebelum mencoba memilih nilai POST untuk id_data_djp
                    setTimeout(() => {
                        <?php if (isset($_POST['id_data_djp'])): ?>
                            idDataDjpSelect.value = "<?php echo $_POST['id_data_djp']; ?>";
                            updateLuasDisplayFromSelectedObjek(); // Update luas berdasarkan pilihan dari POST
                        <?php endif; ?>
                    }, 500); // Waktu tunggu bisa disesuaikan
                }
            }
            if (idDataDjpSelect) {
                idDataDjpSelect.addEventListener('change', updateLuasDisplayFromSelectedObjek);
            }


            document.querySelectorAll('.input-currency').forEach(function(input) {
                function formatCurrencyOnInput(value) {
                    if (!value) return '';
                    let numStr = String(value).replace(/[^0-9]/g, '');
                    if (numStr === '') return '';
                    return parseInt(numStr, 10).toLocaleString('id-ID');
                }
                input.addEventListener('input', function(e) {
                    let originalCursorPos = e.target.selectionStart;
                    let oldValue = e.target.value;
                    e.target.value = formatCurrencyOnInput(e.target.value);
                    let newValue = e.target.value;
                    if (oldValue.length < newValue.length && originalCursorPos === oldValue.length - (oldValue.match(/\./g) || []).length + (newValue.match(/\./g) || []).length) {
                        e.target.selectionStart = e.target.selectionEnd = newValue.length;
                    } else {
                        let diff = newValue.length - oldValue.length;
                        e.target.selectionStart = e.target.selectionEnd = Math.max(0, originalCursorPos + diff);
                    }
                });
                // Format nilai awal saat load
                let initialVal = input.value.replace(/[^0-9]/g, '');
                if (initialVal) {
                    input.value = parseInt(initialVal, 10).toLocaleString('id-ID');
                }
            });

            if (btnPreview && summarySection) {
                btnPreview.addEventListener('click', function() {
                    const selectedOptionObjek = idDataDjpSelect.options[idDataDjpSelect.selectedIndex];
                    const luasBangunan = selectedOptionObjek && selectedOptionObjek.value !== "" ? parseFloat(selectedOptionObjek.getAttribute('data-luas-bangunan').replace(/[^0-9.]/g, '')) : 0;
                    const luasTanah = selectedOptionObjek && selectedOptionObjek.value !== "" ? parseFloat(selectedOptionObjek.getAttribute('data-luas-tanah').replace(/[^0-9.]/g, '')) : 0;

                    const njopBangunanPerM = parseFloat(String(document.getElementById('njop_bangunan_per_meter').value).replace(/\./g, '').replace(',', '.')) || 0;
                    const njopTanahPerM = parseFloat(String(document.getElementById('njop_tanah_per_meter').value).replace(/\./g, '').replace(',', '.')) || 0;
                    const njoptkp = parseFloat(String(document.getElementById('njoptkp').value).replace(/\./g, '').replace(',', '.')) || 0;
                    const persentasePbbInput = String(document.getElementById('persentase_pbb').value).replace(',', '.');
                    const persentasePbb = parseFloat(persentasePbbInput) / 100 || 0.005;

                    if (!idDataDjpSelect.value) {
                        alert("Silakan pilih Wajib Pajak dan Objek Pajaknya terlebih dahulu.");
                        summarySection.style.display = 'none';
                        return;
                    }
                    if (luasBangunan <= 0 && luasTanah <= 0) {
                        alert("Luas bangunan dan luas tanah untuk objek pajak terpilih adalah 0 atau tidak valid. Tidak dapat melakukan preview perhitungan.");
                        summarySection.style.display = 'none';
                        return;
                    }


                    const totalNjopBangunan = luasBangunan * njopBangunanPerM;
                    const totalNjopTanah = luasTanah * njopTanahPerM;
                    const njopTotalObjekPajak = totalNjopBangunan + totalNjopTanah;
                    let njkp = njopTotalObjekPajak - njoptkp;
                    if (njkp < 0) njkp = 0;
                    const jumlahPbbTerutang = njkp * persentasePbb;

                    document.getElementById('summary_total_njop_bangunan').textContent = totalNjopBangunan.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    document.getElementById('summary_total_njop_tanah').textContent = totalNjopTanah.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    document.getElementById('summary_njop_total_objek_pajak').textContent = njopTotalObjekPajak.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    document.getElementById('summary_njkp').textContent = njkp.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    document.getElementById('summary_jumlah_pbb_terutang').textContent = jumlahPbbTerutang.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                    summarySection.style.display = 'block';
                });
            }

        });
    </script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>