<?php
// admin/pengaturan_admin.php (Layout Menyatu)
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
// $foto_profil_admin_session = isset($_SESSION['foto_profil']) ? htmlspecialchars($_SESSION['foto_profil']) : BASE_URL_ADMIN . 'assets/images/default_avatar.png'; // Dihapus
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png'; // Selalu default karena tidak ada fitur upload
$email_admin_session = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';

// Pengaturan untuk halaman ini
$page_title_admin = "Pengaturan Akun";
$current_page = 'pengaturan_admin';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors_profil = [];
$success_profil = "";
$errors_password = [];
$success_password = "";

// Ambil data admin saat ini dari database untuk form profil
$admin_data_db = null;
// Menghapus 'foto_profil' dari SELECT karena diasumsikan tidak ada
$stmt_admin_current = $conn->prepare("SELECT nama_lengkap, email, no_telepon, username FROM pengguna WHERE id_pengguna = ?");
if ($stmt_admin_current) {
    $stmt_admin_current->bind_param("i", $id_admin_logged_in);
    $stmt_admin_current->execute();
    $result_admin_current = $stmt_admin_current->get_result();
    if ($result_admin_current->num_rows === 1) {
        $admin_data_db = $result_admin_current->fetch_assoc();
    } else {
        $errors_profil[] = "Gagal memuat data admin.";
    }
    $stmt_admin_current->close();
} else {
    $errors_profil[] = "Gagal mempersiapkan query data admin: " . $conn->error;
}


// Proses Update Profil
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profil'])) {
    $nama_lengkap_new = $conn->real_escape_string(trim($_POST['nama_lengkap_profil']));
    $email_new = $conn->real_escape_string(trim($_POST['email_profil']));
    $no_telepon_new = $conn->real_escape_string(trim($_POST['no_telepon_profil']));

    if (empty($nama_lengkap_new)) $errors_profil[] = "Nama Lengkap tidak boleh kosong.";
    if (empty($email_new)) $errors_profil[] = "Email tidak boleh kosong.";
    elseif (!filter_var($email_new, FILTER_VALIDATE_EMAIL)) $errors_profil[] = "Format email tidak valid.";

    if ($admin_data_db && $email_new !== $admin_data_db['email']) {
        $stmt_check_email = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE email = ? AND id_pengguna != ?");
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("si", $email_new, $id_admin_logged_in);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                $errors_profil[] = "Email sudah digunakan oleh pengguna lain.";
            }
            $stmt_check_email->close();
        } else {
            $errors_profil[] = "Gagal memvalidasi email: " . $conn->error;
        }
    }

    // Menghapus semua logika terkait upload foto profil
    // $new_foto_profil_path = ... (dihapus)

    if (empty($errors_profil)) {
        // Menghapus 'foto_profil = ?' dari query UPDATE
        $sql_update = "UPDATE pengguna SET nama_lengkap = ?, email = ?, no_telepon = ? WHERE id_pengguna = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            // Menyesuaikan bind_param, menghapus 's' untuk foto_profil
            $stmt_update->bind_param("sssi", $nama_lengkap_new, $email_new, $no_telepon_new, $id_admin_logged_in);
            if ($stmt_update->execute()) {
                $success_profil = "Profil berhasil diperbarui.";
                $_SESSION['nama_lengkap'] = $nama_lengkap_new;
                $_SESSION['email'] = $email_new;
                // $_SESSION['foto_profil'] = $new_foto_profil_path; // Dihapus

                $admin_data_db['nama_lengkap'] = $nama_lengkap_new;
                $admin_data_db['email'] = $email_new;
                $admin_data_db['no_telepon'] = $no_telepon_new;
                // $foto_profil_admin_session tetap default karena tidak ada upload
            } else {
                $errors_profil[] = "Gagal memperbarui profil: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors_profil[] = "Gagal mempersiapkan update profil: " . $conn->error;
        }
    }
}

// Proses Ubah Password (tetap sama)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ubah_password'])) {
    $password_lama = trim($_POST['password_lama']);
    $password_baru = trim($_POST['password_baru']);
    $konfirmasi_password_baru = trim($_POST['konfirmasi_password_baru']);

    if (empty($password_lama)) $errors_password[] = "Password Lama tidak boleh kosong.";
    if (empty($password_baru)) $errors_password[] = "Password Baru tidak boleh kosong.";
    elseif (strlen($password_baru) < 8) $errors_password[] = "Password Baru minimal 8 karakter.";
    if ($password_baru !== $konfirmasi_password_baru) $errors_password[] = "Konfirmasi Password Baru tidak cocok.";

    if (empty($errors_password)) {
        $stmt_pass = $conn->prepare("SELECT password FROM pengguna WHERE id_pengguna = ?");
        if ($stmt_pass) {
            $stmt_pass->bind_param("i", $id_admin_logged_in);
            $stmt_pass->execute();
            $result_pass = $stmt_pass->get_result();
            $current_user_data = $result_pass->fetch_assoc();
            $stmt_pass->close();

            if ($current_user_data && password_verify($password_lama, $current_user_data['password'])) {
                $hashed_password_baru = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt_update_pass = $conn->prepare("UPDATE pengguna SET password = ? WHERE id_pengguna = ?");
                if ($stmt_update_pass) {
                    $stmt_update_pass->bind_param("si", $hashed_password_baru, $id_admin_logged_in);
                    if ($stmt_update_pass->execute()) {
                        $success_password = "Password berhasil diubah.";
                    } else {
                        $errors_password[] = "Gagal mengubah password: " . $stmt_update_pass->error;
                    }
                    $stmt_update_pass->close();
                } else {
                    $errors_password[] = "Gagal mempersiapkan update password: " . $conn->error;
                }
            } else {
                $errors_password[] = "Password Lama salah.";
            }
        } else {
            $errors_password[] = "Gagal mengambil data password: " . $conn->error;
        }
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profil';
if (isset($_POST['update_profil'])) $active_tab = 'profil';
if (isset($_POST['ubah_password'])) $active_tab = 'keamanan';

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - Admin Panel InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-pengaturan.css?v=<?php echo time(); ?>">
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
                <img src="<?php echo BASE_URL_ADMIN . 'assets/images/default_avatar.png'; ?>" alt="Foto Profil <?php echo $nama_admin_session; ?>" class="admin-sidebar-profile-pic"
                    onerror="this.onerror=null;this.src='https://placehold.co/80x80/003366/ffffff?text=<?php echo substr($nama_admin_session, 0, 1); ?>';">
                <span class="admin-sidebar-user-name"><?php echo $nama_admin_session; ?></span>
                <span class="admin-sidebar-user-role">(Administrator)</span>
            </div>
            <nav class="admin-sidebar-nav">
                <ul>
                    <li class="<?php echo ($current_page == 'dashboard_admin') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/">
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
                            <img src="<?php echo BASE_URL_ADMIN . 'assets/images/default_avatar.png'; ?>" alt="Avatar <?php echo $nama_admin_session; ?>" class="admin-avatar-header"
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
                <div class="admin-card settings-card">
                    <div class="settings-tabs">
                        <a href="?tab=profil" class="tab-link <?php echo ($active_tab == 'profil') ? 'active' : ''; ?>">Profil Saya</a>
                        <a href="?tab=keamanan" class="tab-link <?php echo ($active_tab == 'keamanan') ? 'active' : ''; ?>">Ubah Password</a>
                    </div>

                    <div class="settings-tab-content <?php echo ($active_tab == 'profil') ? 'active' : ''; ?>" id="profil-content">
                        <div class="admin-card-header" style="border-bottom:none; padding-bottom:0; margin-bottom:20px;">
                            <h3>Informasi Profil</h3>
                        </div>
                        <?php if (!empty($errors_profil)): ?>
                            <div class="auth-errors">
                                <?php foreach ($errors_profil as $error): ?><p><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_profil)): ?>
                            <div class="auth-success">
                                <p><?php echo htmlspecialchars($success_profil); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($admin_data_db): ?>
                            <form action="pengaturan_admin.php?tab=profil" method="POST" class="admin-form">
                                <div class="form-group">
                                    <label for="nama_lengkap_profil">Nama Lengkap</label>
                                    <input type="text" id="nama_lengkap_profil" name="nama_lengkap_profil" value="<?php echo htmlspecialchars($admin_data_db['nama_lengkap']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email_profil">Email</label>
                                    <input type="email" id="email_profil" name="email_profil" value="<?php echo htmlspecialchars($admin_data_db['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="no_telepon_profil">Nomor Telepon</label>
                                    <input type="tel" id="no_telepon_profil" name="no_telepon_profil" value="<?php echo htmlspecialchars($admin_data_db['no_telepon'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="username_profil">Username</label>
                                    <input type="text" id="username_profil" name="username_profil_display" value="<?php echo htmlspecialchars($admin_data_db['username']); ?>" readonly disabled>
                                    <small>Username tidak dapat diubah.</small>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_profil" class="button btn-primary">Simpan Perubahan Profil</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <p>Gagal memuat data profil admin.</p>
                        <?php endif; ?>
                    </div>

                    <div class="settings-tab-content <?php echo ($active_tab == 'keamanan') ? 'active' : ''; ?>" id="keamanan-content">
                        <div class="admin-card-header" style="border-bottom:none; padding-bottom:0; margin-bottom:20px;">
                            <h3>Ubah Password</h3>
                        </div>
                        <?php if (!empty($errors_password)): ?>
                            <div class="auth-errors">
                                <?php foreach ($errors_password as $error): ?><p><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_password)): ?>
                            <div class="auth-success">
                                <p><?php echo htmlspecialchars($success_password); ?></p>
                            </div>
                        <?php endif; ?>
                        <form action="pengaturan_admin.php?tab=keamanan" method="POST" class="admin-form">
                            <div class="form-group">
                                <label for="password_lama">Password Lama</label>
                                <input type="password" id="password_lama" name="password_lama" required>
                            </div>
                            <div class="form-group">
                                <label for="password_baru">Password Baru</label>
                                <input type="password" id="password_baru" name="password_baru" required minlength="8">
                                <small>Minimal 8 karakter.</small>
                            </div>
                            <div class="form-group">
                                <label for="konfirmasi_password_baru">Konfirmasi Password Baru</label>
                                <input type="password" id="konfirmasi_password_baru" name="konfirmasi_password_baru" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="ubah_password" class="button btn-primary">Ubah Password</button>
                            </div>
                        </form>
                    </div>
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