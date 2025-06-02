<?php
// admin/laporan_detail.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definisikan BASE_URL_ADMIN
if (!defined('BASE_URL_ADMIN')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $app_root_path_temp = dirname(dirname($_SERVER['SCRIPT_NAME'])); // Naik 1 level dari /admin/

    if ($app_root_path_temp === '/' || $app_root_path_temp === '\\') {
        $root_path = '/';
    } else {
        $root_path = rtrim($app_root_path_temp, '/') . '/';
    }
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
$foto_profil_admin_session = isset($_SESSION['foto_profil']) ? htmlspecialchars($_SESSION['foto_profil']) : BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan untuk halaman ini
$page_title_admin = "Laporan Detail Pajak";
$current_page = 'laporan_detail';
$current_parent_page_sub = 'laporan_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$laporan_results = [];

// Proses form pencarian laporan
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['cari_laporan'])) {
    $nik_wp_search = isset($_GET['nik_wajib_pajak']) ? $conn->real_escape_string(trim($_GET['nik_wajib_pajak'])) : '';
    $no_ref_search = isset($_GET['no_referensi']) ? $conn->real_escape_string(trim($_GET['no_referensi'])) : '';
    $nama_wp_search = isset($_GET['nama_wajib_pajak']) ? $conn->real_escape_string(trim($_GET['nama_wajib_pajak'])) : '';
    $tahun_periode_search = isset($_GET['tahun_periode']) && is_numeric($_GET['tahun_periode']) ? intval($_GET['tahun_periode']) : null;

    if (empty($nik_wp_search) && empty($no_ref_search) && empty($nama_wp_search) && empty($tahun_periode_search)) {
        $errors[] = "Minimal satu kriteria pencarian harus diisi.";
    } else {
        $sql_search = "SELECT pp.*, p.nama_lengkap, p.nik, du.alamat_objek_pajak 
                       FROM perhitungan_pajak pp
                       JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
                       JOIN pengguna p ON du.id_pengguna = p.id_pengguna
                       WHERE 1=1";

        $params_search = [];
        $types_search = "";

        if (!empty($nik_wp_search)) {
            $sql_search .= " AND p.nik = ?";
            $params_search[] = $nik_wp_search;
            $types_search .= "s";
        }
        if (!empty($no_ref_search)) {
            $sql_search .= " AND pp.id_perhitungan = ?"; // Asumsi no_ref_search = id_perhitungan
            $params_search[] = $no_ref_search;
            $types_search .= (is_numeric($no_ref_search)) ? "i" : "s";
        }
        if (!empty($nama_wp_search)) {
            $sql_search .= " AND p.nama_lengkap LIKE ?";
            $params_search[] = "%" . $nama_wp_search . "%";
            $types_search .= "s";
        }
        if (!empty($tahun_periode_search)) {
            $sql_search .= " AND pp.periode_pajak_tahun = ?";
            $params_search[] = $tahun_periode_search;
            $types_search .= "i";
        }
        $sql_search .= " ORDER BY pp.tanggal_perhitungan DESC";

        $stmt_search = $conn->prepare($sql_search);
        if ($stmt_search) {
            if (!empty($params_search)) {
                $stmt_search->bind_param($types_search, ...$params_search);
            }
            $stmt_search->execute();
            $result_search = $stmt_search->get_result();
            if ($result_search->num_rows > 0) {
                while ($row = $result_search->fetch_assoc()) {
                    $laporan_results[] = $row;
                }
            } else {
                if (empty($errors)) {
                    $errors[] = "Tidak ada data laporan yang ditemukan untuk kriteria yang diberikan.";
                }
            }
            $stmt_search->close();
        } else {
            $errors[] = "Gagal melakukan pencarian: " . $conn->error;
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
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-laporan-detail.css?v=<?php echo time(); ?>">
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
                    <li class="<?php echo ($current_page == 'dashboard_admin' || $current_page == 'index') ? 'active' : ''; ?>">
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
                <div class="header-center">
                    <?php // Form pencarian global di header dihapus sesuai permintaan 
                    ?>
                </div>
                <div class="header-right">
                    <?php // Notifikasi di header dihapus sesuai permintaan 
                    ?>
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
                        Filter Laporan Detail
                    </div>

                    <?php if (!empty($errors) && isset($_GET['cari_laporan'])): ?>
                        <div class="auth-errors" style="margin: 15px 0;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL_ADMIN; ?>admin/laporan_detail.php" method="GET" class="admin-form filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nik_wajib_pajak">NIK Wajib Pajak</label>
                                <input type="text" id="nik_wajib_pajak" name="nik_wajib_pajak" value="<?php echo isset($_GET['nik_wajib_pajak']) ? htmlspecialchars($_GET['nik_wajib_pajak']) : ''; ?>" placeholder="Masukkan NIK Wajib Pajak">
                            </div>
                            <div class="form-group">
                                <label for="no_referensi">No. Referensi (ID Perhitungan)</label>
                                <input type="text" id="no_referensi" name="no_referensi" value="<?php echo isset($_GET['no_referensi']) ? htmlspecialchars($_GET['no_referensi']) : ''; ?>" placeholder="Contoh: 123">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nama_wajib_pajak">Nama Wajib Pajak</label>
                                <input type="text" id="nama_wajib_pajak" name="nama_wajib_pajak" value="<?php echo isset($_GET['nama_wajib_pajak']) ? htmlspecialchars($_GET['nama_wajib_pajak']) : ''; ?>" placeholder="Masukkan Nama">
                            </div>
                            <div class="form-group">
                                <label for="tahun_periode">Tahun Periode Pajak</label>
                                <input type="number" id="tahun_periode" name="tahun_periode" value="<?php echo isset($_GET['tahun_periode']) ? htmlspecialchars($_GET['tahun_periode']) : ''; ?>" placeholder="YYYY Contoh: <?php echo date('Y'); ?>" min="1900" max="<?php echo date('Y') + 5; ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="cari_laporan" class="button btn-primary"><i class="fas fa-search"></i> Tampilkan Laporan</button>
                        </div>
                    </form>
                </div>

                <?php if (isset($_GET['cari_laporan']) && !empty($laporan_results)): ?>
                    <div class="admin-card" id="hasil-laporan-detail">
                        <div class="admin-card-header">
                            <span>Hasil Pencarian Laporan Detail</span>
                            <div class="header-actions">
                                <button onclick="window.print();" class="button btn-secondary btn-sm"><i class="fas fa-print"></i> Cetak</button>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID Hitung</th>
                                        <th>NIK WP</th>
                                        <th>Nama WP</th>
                                        <th class="text-center">Tahun</th>
                                        <th>Tgl. Hitung</th>
                                        <th class="text-right">Jml. PBB</th>
                                        <th>Status Verifikasi</th>
                                        <th>Status Hitung</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laporan_results as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['id_perhitungan']); ?></td>
                                            <td><?php echo htmlspecialchars($result['nik']); ?></td>
                                            <td><?php echo htmlspecialchars($result['nama_lengkap']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($result['periode_pajak_tahun']); ?></td>
                                            <td><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($result['tanggal_perhitungan']))); ?></td>
                                            <td class="text-right">Rp <?php echo number_format($result['jumlah_pbb_terutang'], 2, ',', '.'); ?></td>
                                            <td class="status-<?php echo htmlspecialchars(str_replace('_', '-', $result['status_verifikasi_data_user'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($result['status_verifikasi_data_user']))); ?>
                                            </td>
                                            <td>
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($result['status_perhitungan']))); ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/detail_perhitungan.php?id=<?php echo $result['id_perhitungan']; ?>" class="button btn-view btn-sm" title="Lihat Detail Perhitungan">
                                                    <i class="fas fa-eye"></i> <span>Detail</span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif (isset($_GET['cari_laporan']) && empty($laporan_results) && empty($errors)): ?>
                    <div class="admin-card" style="margin-top: 20px;">
                        <p class="text-center" style="padding: 20px;">Tidak ada data laporan yang ditemukan untuk kriteria yang Anda masukkan.</p>
                    </div>
                <?php endif; ?>
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