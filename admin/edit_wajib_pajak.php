<?php
// admin/edit_wajib_pajak.php (Layout Menyatu)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// Proteksi halaman admin
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL_ADMIN . "login.php");
    exit();
}

// Data Admin dari Session
$id_admin_logged_in = $_SESSION['id_pengguna'];
$nama_admin = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin');
$foto_profil_admin = isset($_SESSION['foto_profil']) ? htmlspecialchars($_SESSION['foto_profil']) : BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan untuk halaman ini
$page_title_admin = "Edit Wajib Pajak";
$current_page = 'management_wajib_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$success_message = "";
$user_data = null;
$djp_data = null;
$id_wp_to_edit = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_wp_to_edit = intval($_GET['id']);

    $stmt_user = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ? AND role = 'user'");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $id_wp_to_edit);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows === 1) {
            $user_data = $result_user->fetch_assoc();
        } else {
            $errors[] = "Data wajib pajak tidak ditemukan.";
        }
        $stmt_user->close();
    } else {
        $errors[] = "Gagal mengambil data pengguna: " . $conn->error;
    }

    if ($user_data) {
        $stmt_djp = $conn->prepare("SELECT * FROM data_djp_user WHERE id_pengguna = ?");
        if ($stmt_djp) {
            $stmt_djp->bind_param("i", $id_wp_to_edit);
            $stmt_djp->execute();
            $result_djp = $stmt_djp->get_result();
            if ($result_djp->num_rows === 1) {
                $djp_data = $result_djp->fetch_assoc();
            }
            $stmt_djp->close();
        } else {
            $errors[] = "Gagal mengambil data DJP pengguna: " . $conn->error;
        }
    }
} else {
    $errors[] = "ID Wajib Pajak tidak valid atau tidak disediakan.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_wajib_pajak']) && $user_data) {
    $nama_lengkap_update = $conn->real_escape_string(trim($_POST['nama_lengkap']));
    $email_update = $conn->real_escape_string(trim($_POST['email']));
    $no_telepon_update = $conn->real_escape_string(trim($_POST['no_telepon']));
    $status_akun_update = $conn->real_escape_string(trim($_POST['status_akun']));
    $role_update = $conn->real_escape_string(trim($_POST['role']));
    $nik_update = $user_data['nik'];
    $username_update = $user_data['username'];

    if (empty($nama_lengkap_update)) $errors[] = "Nama Lengkap tidak boleh kosong.";
    if (empty($email_update)) $errors[] = "Email tidak boleh kosong.";
    elseif (!filter_var($email_update, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid.";
    if (!in_array($status_akun_update, ['aktif', 'nonaktif', 'pending'])) $errors[] = "Status akun tidak valid.";
    if (!in_array($role_update, ['user', 'admin'])) $errors[] = "Role tidak valid.";

    if ($email_update !== $user_data['email']) {
        $stmt_check_email = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE email = ? AND id_pengguna != ?");
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("si", $email_update, $id_wp_to_edit);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                $errors[] = "Email sudah digunakan oleh pengguna lain.";
            }
            $stmt_check_email->close();
        } else {
            $errors[] = "Gagal memvalidasi email: " . $conn->error;
        }
    }

    // Data DJP
    $npwp_update = isset($_POST['npwp']) ? $conn->real_escape_string(trim($_POST['npwp'])) : null;
    $nama_pemilik_bangunan_update = isset($_POST['nama_pemilik_bangunan']) ? $conn->real_escape_string(trim($_POST['nama_pemilik_bangunan'])) : null;
    $alamat_objek_pajak_update = isset($_POST['alamat_objek_pajak']) ? $conn->real_escape_string(trim($_POST['alamat_objek_pajak'])) : null;
    $jenis_bangunan_update = isset($_POST['jenis_bangunan']) ? $conn->real_escape_string(trim($_POST['jenis_bangunan'])) : null;
    $data_tambahan_update = isset($_POST['data_tambahan']) ? $conn->real_escape_string(trim($_POST['data_tambahan'])) : null;

    // Penanganan input Luas Bangunan dengan sanitasi koma ke titik
    $luas_bangunan_input = isset($_POST['luas_bangunan']) ? trim($_POST['luas_bangunan']) : '';
    if ($luas_bangunan_input === '') {
        $luas_bangunan_update = null; // Atau 0.00 jika Anda ingin defaultnya 0
    } else {
        $luas_bangunan_sanitized = str_replace(',', '.', $luas_bangunan_input);
        if (is_numeric($luas_bangunan_sanitized)) {
            $luas_bangunan_update = floatval($luas_bangunan_sanitized);
        } else {
            $luas_bangunan_update = null; // Jadi null jika input tidak valid setelah sanitasi
            $errors[] = "Format Luas Bangunan tidak valid. Gunakan angka (misal 100 atau 100.50).";
        }
    }

    // Penanganan input Luas Tanah dengan sanitasi koma ke titik
    $luas_tanah_input = isset($_POST['luas_tanah']) ? trim($_POST['luas_tanah']) : '';
    if ($luas_tanah_input === '') {
        $luas_tanah_update = null; // Atau 0.00 jika Anda ingin defaultnya 0
    } else {
        $luas_tanah_sanitized = str_replace(',', '.', $luas_tanah_input);
        if (is_numeric($luas_tanah_sanitized)) {
            $luas_tanah_update = floatval($luas_tanah_sanitized);
        } else {
            $luas_tanah_update = null; // Jadi null jika input tidak valid setelah sanitasi
            $errors[] = "Format Luas Tanah tidak valid. Gunakan angka (misal 100 atau 100.50).";
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        $sql_update_user = "UPDATE pengguna SET nama_lengkap = ?, email = ?, no_telepon = ?, status_akun = ?, role = ? WHERE id_pengguna = ?";
        $stmt_update_user = $conn->prepare($sql_update_user);
        if ($stmt_update_user) {
            $stmt_update_user->bind_param("sssssi", $nama_lengkap_update, $email_update, $no_telepon_update, $status_akun_update, $role_update, $id_wp_to_edit);
            if (!$stmt_update_user->execute()) {
                $errors[] = "Gagal memperbarui data pengguna: " . $stmt_update_user->error;
            }
            $stmt_update_user->close();
        } else {
            $errors[] = "Gagal mempersiapkan update data pengguna: " . $conn->error;
        }

        if (empty($errors)) {
            if ($djp_data) {
                $sql_djp = "UPDATE data_djp_user SET npwp = ?, nama_pemilik_bangunan = ?, alamat_objek_pajak = ?, luas_bangunan = ?, luas_tanah = ?, jenis_bangunan = ?, data_tambahan = ? WHERE id_pengguna = ?";
                $stmt_djp_update = $conn->prepare($sql_djp);
                if ($stmt_djp_update) {
                    $stmt_djp_update->bind_param("sssddssi", $npwp_update, $nama_pemilik_bangunan_update, $alamat_objek_pajak_update, $luas_bangunan_update, $luas_tanah_update, $jenis_bangunan_update, $data_tambahan_update, $id_wp_to_edit);
                    if (!$stmt_djp_update->execute()) {
                        $errors[] = "Gagal memperbarui data DJP: " . $stmt_djp_update->error;
                    }
                    $stmt_djp_update->close();
                } else {
                    $errors[] = "Gagal mempersiapkan update data DJP: " . $conn->error;
                }
            } else {
                if (!empty($npwp_update) || !empty($nama_pemilik_bangunan_update) || !empty($alamat_objek_pajak_update) || $luas_bangunan_update !== null || $luas_tanah_update !== null || !empty($jenis_bangunan_update) || !empty($data_tambahan_update)) {
                    $sql_djp_insert = "INSERT INTO data_djp_user (id_pengguna, npwp, nama_pemilik_bangunan, alamat_objek_pajak, luas_bangunan, luas_tanah, jenis_bangunan, data_tambahan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_djp_insert = $conn->prepare($sql_djp_insert);
                    if ($stmt_djp_insert) {
                        $stmt_djp_insert->bind_param("isssddss", $id_wp_to_edit, $npwp_update, $nama_pemilik_bangunan_update, $alamat_objek_pajak_update, $luas_bangunan_update, $luas_tanah_update, $jenis_bangunan_update, $data_tambahan_update);
                        if (!$stmt_djp_insert->execute()) {
                            $errors[] = "Gagal menyimpan data DJP baru: " . $stmt_djp_insert->error;
                        } else {
                            // Ambil ulang data DJP setelah insert
                            $stmt_djp_fetch = $conn->prepare("SELECT * FROM data_djp_user WHERE id_pengguna = ?");
                            if ($stmt_djp_fetch) {
                                $stmt_djp_fetch->bind_param("i", $id_wp_to_edit);
                                $stmt_djp_fetch->execute();
                                $djp_data = $stmt_djp_fetch->get_result()->fetch_assoc();
                                $stmt_djp_fetch->close();
                            }
                        }
                        $stmt_djp_insert->close();
                    } else {
                        $errors[] = "Gagal mempersiapkan penyimpanan data DJP baru: " . $conn->error;
                    }
                }
            }
        }

        if (empty($errors)) {
            $conn->commit();
            $success_message = "Data wajib pajak berhasil diperbarui.";
            // Ambil ulang data pengguna terbaru
            $stmt_user_refresh = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ?");
            if ($stmt_user_refresh) {
                $stmt_user_refresh->bind_param("i", $id_wp_to_edit);
                $stmt_user_refresh->execute();
                $user_data = $stmt_user_refresh->get_result()->fetch_assoc();
                $stmt_user_refresh->close();
            }
            // Ambil ulang data DJP terbaru
            $stmt_djp_refresh = $conn->prepare("SELECT * FROM data_djp_user WHERE id_pengguna = ?");
            if ($stmt_djp_refresh) {
                $stmt_djp_refresh->bind_param("i", $id_wp_to_edit);
                $stmt_djp_refresh->execute();
                $djp_data = $stmt_djp_refresh->get_result()->fetch_assoc();
                $stmt_djp_refresh->close();
            }
        } else {
            $conn->rollback();
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
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-edit-wajib-pajak.css?v=<?php echo time(); ?>">
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
                <img src="<?php echo $foto_profil_admin; ?>" alt="Foto Profil <?php echo $nama_admin; ?>" class="admin-sidebar-profile-pic"
                    onerror="this.onerror=null;this.src='https://placehold.co/80x80/003366/ffffff?text=<?php echo substr($nama_admin, 0, 1); ?>';">
                <span class="admin-sidebar-user-name"><?php echo $nama_admin; ?></span>
                <span class="admin-sidebar-user-role">(Administrator)</span>
            </div>
            <nav class="admin-sidebar-nav">
                <ul>
                    <li class="<?php echo (isset($current_page) && $current_page == 'dashboard_admin') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/dashboard_admin.php">
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
                            <li class="<?php echo (isset($current_page) && $current_page == 'tagihan_perhitungan') ? 'active' : ''; ?>">
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
                            <li class="<?php echo (isset($current_page) && $current_page == 'kelola_pengguna') ? 'active' : ''; ?>">
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/kelola_pengguna.php">
                                    <i class="fas fa-user-cog fa-fw"></i>
                                    <span>Manajemen Pengguna</span>
                                </a>
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
                    <p class="header-welcome-text">Selamat Datang, <?php echo $nama_admin; ?>!</p>
                </div>
                <div class="header-center">
                    <form action="<?php echo BASE_URL_ADMIN; ?>admin/search_results.php" method="GET" class="header-search-form">
                        <input type="search" name="q" placeholder="Cari..." aria-label="Search">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="header-right">
                    <a href="<?php echo BASE_URL_ADMIN; ?>admin/notifikasi.php" class="header-icon-link notification-link" aria-label="Notifikasi">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </a>
                    <div class="admin-profile-dropdown">
                        <a href="#" class="admin-profile-link-header" id="profile-dropdown-toggle">
                            <img src="<?php echo $foto_profil_admin; ?>" alt="Avatar <?php echo $nama_admin; ?>" class="admin-avatar-header"
                                onerror="this.onerror=null;this.src='https://placehold.co/32x32/cccccc/000000?text=<?php echo substr($nama_admin, 0, 1); ?>';">
                            <span><?php echo $nama_admin; ?></span>
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
                        Edit Data Wajib Pajak: <?php echo $user_data ? htmlspecialchars($user_data['nama_lengkap']) : 'Tidak Ditemukan'; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin: 15px 0; text-align:left;">
                            <strong>Terjadi kesalahan:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="auth-success" style="margin: 15px 0;">
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($user_data): ?>
                        <form action="edit_wajib_pajak.php?id=<?php echo $id_wp_to_edit; ?>" method="POST" class="admin-form">
                            <fieldset>
                                <legend>Data Akun Pengguna</legend>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nama_lengkap">Nama Lengkap</label>
                                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="nik">NIK (Nomor Induk Kependudukan)</label>
                                        <input type="text" id="nik" name="nik_display" value="<?php echo htmlspecialchars($user_data['nik']); ?>" readonly disabled>
                                        <small>NIK tidak dapat diubah.</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="no_telepon">Nomor Telepon</label>
                                        <input type="tel" id="no_telepon" name="no_telepon" value="<?php echo htmlspecialchars($user_data['no_telepon'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" id="username" name="username_display" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly disabled>
                                        <small>Username tidak dapat diubah.</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="status_akun">Status Akun</label>
                                        <select id="status_akun" name="status_akun" required>
                                            <option value="aktif" <?php echo ($user_data['status_akun'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="nonaktif" <?php echo ($user_data['status_akun'] == 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                                            <option value="pending" <?php echo ($user_data['status_akun'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="role">Role</label>
                                        <select id="role" name="role" required>
                                            <option value="user" <?php echo ($user_data['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                            <option value="admin" <?php echo ($user_data['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <small>Hati-hati mengubah role ke Admin.</small>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend>Data DJP (Objek Pajak)</legend>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="npwp">NPWP</label>
                                        <input type="text" id="npwp" name="npwp" value="<?php echo htmlspecialchars($djp_data['npwp'] ?? ''); ?>" placeholder="Contoh: 00.000.000.0-000.000">
                                    </div>
                                    <div class="form-group">
                                        <label for="nama_pemilik_bangunan">Nama Pemilik Bangunan (sesuai PBB)</label>
                                        <input type="text" id="nama_pemilik_bangunan" name="nama_pemilik_bangunan" value="<?php echo htmlspecialchars($djp_data['nama_pemilik_bangunan'] ?? $user_data['nama_lengkap']); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="alamat_objek_pajak">Alamat Objek Pajak</label>
                                    <textarea id="alamat_objek_pajak" name="alamat_objek_pajak" rows="3"><?php echo htmlspecialchars($djp_data['alamat_objek_pajak'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="luas_bangunan">Luas Bangunan (m²)</label>
                                        <input type="text" id="luas_bangunan" name="luas_bangunan" value="<?php echo isset($djp_data['luas_bangunan']) ? number_format(floatval($djp_data['luas_bangunan']), 2, '.', '') : ''; ?>" placeholder="Contoh: 100.50">
                                    </div>
                                    <div class="form-group">
                                        <label for="luas_tanah">Luas Tanah (m²)</label>
                                        <input type="text" id="luas_tanah" name="luas_tanah" value="<?php echo isset($djp_data['luas_tanah']) ? number_format(floatval($djp_data['luas_tanah']), 2, '.', '') : ''; ?>" placeholder="Contoh: 200.75">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="jenis_bangunan">Jenis Bangunan</label>
                                    <input type="text" id="jenis_bangunan" name="jenis_bangunan" value="<?php echo htmlspecialchars($djp_data['jenis_bangunan'] ?? ''); ?>" placeholder="Contoh: Rumah Tinggal, Ruko, Gudang">
                                </div>
                                <div class="form-group">
                                    <label for="data_tambahan">Data Tambahan DJP</label>
                                    <textarea id="data_tambahan" name="data_tambahan" rows="3"><?php echo htmlspecialchars($djp_data['data_tambahan'] ?? ''); ?></textarea>
                                </div>
                            </fieldset>

                            <div class="form-actions">
                                <button type="submit" name="update_wajib_pajak" class="button btn-primary">Simpan Perubahan</button>
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php" class="button btn-secondary">Batal</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p>Tidak dapat memuat form karena data wajib pajak tidak ditemukan atau ID tidak valid.</p>
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php" class="button btn-secondary">Kembali ke Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>