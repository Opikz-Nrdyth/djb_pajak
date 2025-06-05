<?php
// admin/edit_wajib_pajak.php (Layout Menyatu - Edit User & Daftar Multi Objek)
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
$nama_admin_session = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin');
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan untuk halaman ini
$page_title_admin = "Edit Wajib Pajak & Objek Pajaknya";
$current_page = 'management_wajib_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$success_message = "";
$user_data_to_edit = null; // Data pengguna dari tabel 'pengguna'
$daftar_objek_pajak_user = []; // Array untuk menampung semua objek pajak user
$id_wp_to_edit = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_wp_to_edit = intval($_GET['id']);

    // Ambil data pengguna dari tabel pengguna
    $stmt_user = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ? AND role = 'user'");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $id_wp_to_edit);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows === 1) {
            $user_data_to_edit = $result_user->fetch_assoc();
        } else {
            $errors[] = "Data wajib pajak tidak ditemukan.";
        }
        $stmt_user->close();
    } else {
        $errors[] = "Gagal mengambil data pengguna: " . $conn->error;
    }

    // Ambil SEMUA data DJP (objek pajak) milik pengguna ini jika pengguna ditemukan
    if ($user_data_to_edit) {
        $stmt_djp_list = $conn->prepare("SELECT * FROM data_djp_user WHERE id_pengguna = ? ORDER BY id_data_djp ASC");
        if ($stmt_djp_list) {
            $stmt_djp_list->bind_param("i", $id_wp_to_edit);
            $stmt_djp_list->execute();
            $result_djp_list = $stmt_djp_list->get_result();
            while ($row_djp = $result_djp_list->fetch_assoc()) {
                $daftar_objek_pajak_user[] = $row_djp;
            }
            $stmt_djp_list->close();
        } else {
            $errors[] = "Gagal mengambil daftar objek pajak pengguna: " . $conn->error;
        }
    }
} else {
    $errors[] = "ID Wajib Pajak tidak valid atau tidak disediakan.";
}

// Proses form submission untuk update data AKUN PENGGUNA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_akun_wajib_pajak']) && $user_data_to_edit) {
    $nama_lengkap_update = $conn->real_escape_string(trim($_POST['nama_lengkap']));
    $email_update = $conn->real_escape_string(trim($_POST['email']));
    $no_telepon_update = $conn->real_escape_string(trim($_POST['no_telepon']));
    $status_akun_update = $conn->real_escape_string(trim($_POST['status_akun']));
    $role_update = $conn->real_escape_string(trim($_POST['role']));

    // Validasi dasar data pengguna
    if (empty($nama_lengkap_update)) $errors[] = "Nama Lengkap tidak boleh kosong.";
    if (empty($email_update)) $errors[] = "Email tidak boleh kosong.";
    elseif (!filter_var($email_update, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid.";
    if (!in_array($status_akun_update, ['aktif', 'nonaktif', 'pending'])) $errors[] = "Status akun tidak valid.";
    if (!in_array($role_update, ['user', 'admin'])) $errors[] = "Role tidak valid.";

    // Validasi keunikan jika email diubah
    if ($email_update !== $user_data_to_edit['email']) {
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

    if (empty($errors)) {
        // Update tabel pengguna
        $sql_update_user = "UPDATE pengguna SET nama_lengkap = ?, email = ?, no_telepon = ?, status_akun = ?, role = ? WHERE id_pengguna = ?";
        $stmt_update_user = $conn->prepare($sql_update_user);
        if ($stmt_update_user) {
            $stmt_update_user->bind_param("sssssi", $nama_lengkap_update, $email_update, $no_telepon_update, $status_akun_update, $role_update, $id_wp_to_edit);
            if ($stmt_update_user->execute()) {
                $success_message = "Data akun wajib pajak berhasil diperbarui.";
                // Ambil ulang data terbaru untuk ditampilkan di form
                $stmt_refresh_user = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ?");
                $stmt_refresh_user->bind_param("i", $id_wp_to_edit);
                $stmt_refresh_user->execute();
                $user_data_to_edit = $stmt_refresh_user->get_result()->fetch_assoc();
                $stmt_refresh_user->close();
            } else {
                $errors[] = "Gagal memperbarui data akun pengguna: " . $stmt_update_user->error;
            }
            $stmt_update_user->close();
        } else {
            $errors[] = "Gagal mempersiapkan update data akun pengguna: " . $conn->error;
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
    <!-- CSS Spesifik Konten Halaman Ini -->
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/css/admin-edit-wajib-pajak.css?v=<?php echo time(); ?>">
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
                            <li class="<?php echo ($current_page == 'management_wajib_pajak') ? 'active' : ''; ?>">
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
                        Edit Akun Wajib Pajak: <?php echo $user_data_to_edit ? htmlspecialchars($user_data_to_edit['nama_lengkap']) : 'Tidak Ditemukan'; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin: 15px 0;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="auth-success" style="margin: 15px 0;">
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($user_data_to_edit): ?>
                        <form action="edit_wajib_pajak.php?id=<?php echo $id_wp_to_edit; ?>" method="POST" class="admin-form">
                            <fieldset>
                                <legend>Data Akun Pengguna</legend>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nama_lengkap">Nama Lengkap</label>
                                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data_to_edit['nama_lengkap']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="nik">NIK</label>
                                        <input type="text" id="nik" name="nik_display" value="<?php echo htmlspecialchars($user_data_to_edit['nik']); ?>" readonly disabled>
                                        <small>NIK tidak dapat diubah.</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data_to_edit['email']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="no_telepon">Nomor Telepon</label>
                                        <input type="tel" id="no_telepon" name="no_telepon" value="<?php echo htmlspecialchars($user_data_to_edit['no_telepon'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" id="username" name="username_display" value="<?php echo htmlspecialchars($user_data_to_edit['username']); ?>" readonly disabled>
                                        <small>Username tidak dapat diubah.</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="status_akun">Status Akun</label>
                                        <select id="status_akun" name="status_akun" required>
                                            <option value="aktif" <?php echo ($user_data_to_edit['status_akun'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="nonaktif" <?php echo ($user_data_to_edit['status_akun'] == 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                                            <option value="pending" <?php echo ($user_data_to_edit['status_akun'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="role">Role</label>
                                        <select id="role" name="role" required>
                                            <option value="user" <?php echo ($user_data_to_edit['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                            <option value="admin" <?php echo ($user_data_to_edit['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <small>Hati-hati mengubah role.</small>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_akun_wajib_pajak" class="button btn-primary">Simpan Perubahan Akun</button>
                                </div>
                            </fieldset>
                        </form>
                        <hr class="form-separator">

                        <div class="objek-pajak-management">
                            <div class="page-header" style="margin-top: 20px;">
                                <h3>Daftar Objek Pajak Milik Pengguna Ini</h3>
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/form_data_objek_pajak.php?user_id=<?php echo $id_wp_to_edit; ?>" class="btn-add-new btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Objek Pajak
                                </a>
                            </div>

                            <?php if (!empty($daftar_objek_pajak_user)): ?>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>ID Objek</th>
                                                <th>Alamat Objek Pajak</th>
                                                <th>Jenis Bangunan</th>
                                                <th class="text-right">L. Bangunan (m²)</th>
                                                <th class="text-right">L. Tanah (m²)</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($daftar_objek_pajak_user as $objek): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($objek['id_data_djp']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($objek['alamat_objek_pajak'], 0, 50)) . (strlen($objek['alamat_objek_pajak']) > 50 ? '...' : ''); ?></td>
                                                    <td><?php echo htmlspecialchars($objek['jenis_bangunan'] ?? '-'); ?></td>
                                                    <td class="text-right"><?php echo number_format(floatval($objek['luas_bangunan']), 2, ',', '.'); ?></td>
                                                    <td class="text-right"><?php echo number_format(floatval($objek['luas_tanah']), 2, ',', '.'); ?></td>
                                                    <td class="action-buttons">
                                                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/form_data_objek_pajak.php?edit_objek_id=<?php echo $objek['id_data_djp']; ?>" class="btn btn-edit btn-sm" title="Edit Objek Pajak Ini">
                                                            <i class="fas fa-edit"></i> <span>Edit</span>
                                                        </a>
                                                        <!-- Tombol Hapus Objek Pajak Individual -->
                                                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/hapus_data_objek_pajak.php?id_objek=<?php echo $objek['id_data_djp']; ?>&user_id=<?php echo $id_wp_to_edit; ?>"
                                                            class="btn btn-delete btn-sm" title="Hapus Objek Pajak Ini"
                                                            onclick="return confirm('Apakah Anda yakin ingin menghapus objek pajak ini? Perhitungan terkait juga akan dihapus.');">
                                                            <i class="fas fa-trash"></i> <span>Hapus</span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>Pengguna ini belum memiliki data objek pajak yang tersimpan.</p>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions" style="margin-top: 30px; padding-top:0; border-top:none; justify-content:flex-start;">
                            <a href="<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php" class="button btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Wajib Pajak</a>
                        </div>

                    <?php else: ?>
                        <?php if (empty($errors)): ?>
                            <p>Data wajib pajak dengan ID yang diminta tidak ditemukan.</p>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php" class="button btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Wajib Pajak</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>