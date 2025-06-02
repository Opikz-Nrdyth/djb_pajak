<?php
// admin/dashboard_admin.php (Layout Menyatu)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definisikan BASE_URL_ADMIN di awal, sebelum digunakan
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
$id_admin = $_SESSION['id_pengguna'];
$nama_admin = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin');
$foto_profil_admin = isset($_SESSION['foto_profil']) ? htmlspecialchars($_SESSION['foto_profil']) : BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan untuk halaman ini
$page_title_admin = "Dashboard"; // Judul spesifik untuk halaman ini
$current_page = 'dashboard_admin'; // Untuk menandai menu aktif di sidebar
// $current_parent_page = ''; // Tidak ada parent untuk dashboard
// $current_parent_page_sub = ''; // Tidak ada sub-parent untuk dashboard

$page_title_for_header = $page_title_admin;

// Include koneksi database jika diperlukan untuk konten halaman
require_once '../php/db_connect.php'; // Path disesuaikan karena file ini ada di folder admin

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_for_header . ' - Admin Panel InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
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
                    <li class="<?php echo ($current_page == 'dashboard_admin') ? 'active' : ''; ?>">
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
                    <h1 class="header-page-title"><?php echo $page_title_for_header; ?></h1>
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
                        <span class="notification-badge">3</span> </a>
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
                        Ringkasan Sistem
                    </div>
                    <div class="admin-dashboard-summary">
                        <div class="summary-item">
                            <h4>Total Pengguna Terdaftar</h4>
                            <p class="summary-value">
                                <?php
                                if ($conn) {
                                    $result = $conn->query("SELECT COUNT(*) as total_users FROM pengguna");
                                    echo ($result && $row = $result->fetch_assoc()) ? $row['total_users'] : "N/A";
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                        </div>
                        <div class="summary-item">
                            <h4>Pengguna Menunggu Persetujuan</h4>
                            <p class="summary-value">
                                <?php
                                if ($conn) {
                                    $result_pending = $conn->query("SELECT COUNT(*) as total_pending FROM pengguna WHERE status_akun = 'pending'");
                                    echo ($result_pending && $row_pending = $result_pending->fetch_assoc()) ? $row_pending['total_pending'] : "N/A";
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                            <a href="<?php echo BASE_URL_ADMIN; ?>admin/kelola_pengguna.php?status=pending" class="summary-link">Lihat Detail</a>
                        </div>
                        <div class="summary-item">
                            <h4>Total Perhitungan Pajak</h4>
                            <p class="summary-value">
                                <?php
                                if ($conn) {
                                    $result_calc = $conn->query("SELECT COUNT(*) as total_calc FROM perhitungan_pajak");
                                    echo ($result_calc && $row_calc = $result_calc->fetch_assoc()) ? $row_calc['total_calc'] : "N/A";
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                        </div>
                        <div class="summary-item">
                            <h4>Laporan Dibuat Bulan Ini</h4>
                            <p class="summary-value">
                                <?php
                                if ($conn) {
                                    $current_month_start = date('Y-m-01');
                                    $current_month_end = date('Y-m-t');
                                    $stmt_reports = $conn->prepare("SELECT COUNT(*) as total_reports FROM laporan_pajak WHERE tanggal_pembuatan_laporan BETWEEN ? AND ?");
                                    if ($stmt_reports) {
                                        $stmt_reports->bind_param("ss", $current_month_start, $current_month_end);
                                        $stmt_reports->execute();
                                        $result_reports = $stmt_reports->get_result();
                                        echo ($result_reports && $row_reports = $result_reports->fetch_assoc()) ? $row_reports['total_reports'] : "N/A";
                                        $stmt_reports->close();
                                    } else {
                                        echo "N/A";
                                    }
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-header">
                        Aktivitas Terbaru (Contoh)
                    </div>
                    <ul class="admin-recent-activity">
                        <li>Pengguna 'user123' baru saja mendaftar. <span class="activity-time">5 menit lalu</span></li>
                        <li>Perhitungan pajak untuk NIK '320xxxxxxxxx' telah diverifikasi. <span class="activity-time">1 jam lalu</span></li>
                        <li>Laporan bulanan Juni 2024 telah dibuat. <span class="activity-time">Kemarin</span></li>
                        <li>Admin 'admin_utama' mengubah pengaturan sistem. <span class="activity-time">2 hari lalu</span></li>
                    </ul>
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