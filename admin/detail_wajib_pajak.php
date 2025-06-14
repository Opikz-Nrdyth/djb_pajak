<?php
// admin/detail_wajib_pajak.php (Layout Menyatu)
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
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png'; // Menggunakan avatar default

// Pengaturan untuk halaman ini
$page_title_admin = "Detail Wajib Pajak";
$current_page = 'management_wajib_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$user_detail_data = null;
$daftar_objek_pajak_user = []; // Array untuk menampung semua objek pajak user
$id_wp_to_view = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_wp_to_view = intval($_GET['id']);

    // Ambil data pengguna dari tabel pengguna
    $stmt_user = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ? AND role = 'user'");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $id_wp_to_view);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows === 1) {
            $user_detail_data = $result_user->fetch_assoc();
        } else {
            $errors[] = "Data wajib pajak tidak ditemukan.";
        }
        $stmt_user->close();
    } else {
        $errors[] = "Gagal mengambil data pengguna: " . $conn->error;
    }

    // Ambil SEMUA data DJP dari tabel data_djp_user jika pengguna ditemukan
    if ($user_detail_data) {
        $stmt_djp = $conn->prepare("SELECT * FROM data_djp_user WHERE id_pengguna = ? ORDER BY id_data_djp ASC");
        if ($stmt_djp) {
            $stmt_djp->bind_param("i", $id_wp_to_view);
            $stmt_djp->execute();
            $result_djp = $stmt_djp->get_result();
            if ($result_djp->num_rows > 0) {
                while ($row_djp = $result_djp->fetch_assoc()) {
                    $daftar_objek_pajak_user[] = $row_djp;
                }
            }
            // Jika tidak ada data DJP, $daftar_objek_pajak_user akan tetap kosong
            $stmt_djp->close();
        } else {
            $errors[] = "Gagal mengambil data objek pajak pengguna: " . $conn->error;
        }
    }
} else {
    $errors[] = "ID Wajib Pajak tidak valid atau tidak disediakan.";
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - Admin Panel InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-detail-wp.css?v=<?php echo time(); ?>">
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
                <div class="admin-card detail-view-card">
                    <div class="admin-card-header">
                        Detail Wajib Pajak: <?php echo $user_detail_data ? htmlspecialchars($user_detail_data['nama_lengkap']) : 'Tidak Ditemukan'; ?>
                        <div class="header-actions">
                            <?php if ($user_detail_data): ?>
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/edit_wajib_pajak.php?id=<?php echo $id_wp_to_view; ?>" class="btn btn-edit btn-sm"><i class="fas fa-edit"></i> Edit Akun WP</a>
                                <!-- Tombol Tambah Objek Pajak Baru untuk user ini -->
                                <a href="<?php echo BASE_URL_ADMIN; ?>user/data_objek_pajak.php?user_id_for_new_objek=<?php echo $id_wp_to_view; ?>" class="btn btn-add-new btn-sm"><i class="fas fa-plus"></i> Tambah Objek Pajak</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($user_detail_data): ?>
                        <div class="detail-section user-info-section">
                            <h3>Informasi Akun Pengguna</h3>
                            <p><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($user_detail_data['nama_lengkap']); ?></p>
                            <p><strong>NIK:</strong> <?php echo htmlspecialchars($user_detail_data['nik']); ?></p>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($user_detail_data['username']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user_detail_data['email']); ?></p>
                            <p><strong>No. Telepon:</strong> <?php echo htmlspecialchars($user_detail_data['no_telepon'] ?? '-'); ?></p>
                            <p><strong>Role:</strong> <span class="role-<?php echo strtolower($user_detail_data['role']); ?>"><?php echo ucfirst(htmlspecialchars($user_detail_data['role'])); ?></span></p>
                            <p><strong>Status Akun:</strong> <span class="status-<?php echo htmlspecialchars($user_detail_data['status_akun']); ?>"><?php echo ucfirst(htmlspecialchars($user_detail_data['status_akun'])); ?></span></p>
                            <p><strong>Tanggal Registrasi:</strong> <?php echo htmlspecialchars(date('d F Y, H:i', strtotime($user_detail_data['tanggal_registrasi']))); ?></p>
                        </div>

                        <div class="detail-section objek-pajak-list-section">
                            <h3>Daftar Objek Pajak Milik Pengguna</h3>
                            <?php if (!empty($daftar_objek_pajak_user)): ?>
                                <?php foreach ($daftar_objek_pajak_user as $index => $objek): ?>
                                    <div class="objek-pajak-item">
                                        <h4>Objek Pajak #<?php echo $index + 1; ?> <small>(ID Data: <?php echo htmlspecialchars($objek['id_data_djp']); ?>)</small></h4>
                                        <p><strong>NPWP:</strong> <?php echo htmlspecialchars($objek['npwp'] ?? '-'); ?></p>
                                        <p><strong>Nama Pemilik (PBB):</strong> <?php echo htmlspecialchars($objek['nama_pemilik_bangunan'] ?? '-'); ?></p>
                                        <p><strong>Alamat Objek Pajak:</strong> <?php echo nl2br(htmlspecialchars($objek['alamat_objek_pajak'] ?? '-')); ?></p>
                                        <p><strong>Jenis Bangunan:</strong> <?php echo htmlspecialchars($objek['jenis_bangunan'] ?? '-'); ?></p>
                                        <p><strong>Luas Bangunan:</strong> <?php echo isset($objek['luas_bangunan']) ? number_format(floatval($objek['luas_bangunan']), 2, ',', '.') . ' m²' : '-'; ?></p>
                                        <p><strong>Luas Tanah:</strong> <?php echo isset($objek['luas_tanah']) ? number_format(floatval($objek['luas_tanah']), 2, ',', '.') . ' m²' : '-'; ?></p>
                                        <p><strong>Data Tambahan:</strong> <?php echo nl2br(htmlspecialchars($objek['data_tambahan'] ?? 'Tidak ada.')); ?></p>
                                        <p><strong>Terakhir Update:</strong> <?php echo htmlspecialchars(date('d F Y, H:i', strtotime($objek['tanggal_update']))); ?></p>

                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Pengguna ini belum memiliki data objek pajak yang tersimpan.</p>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions" style="justify-content: flex-start; margin-top: 20px; border-top:none; padding-top:0;">
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

    <script src="<?php echo BASE_URL_ADMIN; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>