<?php
// admin/rekapitulasi_laporan.php (Layout Menyatu)
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
$foto_profil_admin = isset($_SESSION['foto_profil']) ? htmlspecialchars($_SESSION['foto_profil']) : BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan untuk halaman ini
$page_title_admin = "Rekapitulasi Laporan";
$current_page = 'rekapitulasi';
$current_parent_page_sub = 'laporan_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$rekapitulasi_results = []; // Untuk menampung hasil query
$jenis_rekapitulasi_selected = '';
$tanggal_mulai_selected = '';
$tanggal_akhir_selected = '';

// Jenis rekapitulasi yang tersedia (contoh)
$jenis_rekap_options = [
    '' => '-- Pilih Jenis Rekapitulasi --',
    'total_pbb_per_periode' => 'Total PBB Terutang per Periode',
    'jumlah_perhitungan_per_status' => 'Jumlah Perhitungan berdasarkan Status',
    'kinerja_admin_pereview' => 'Kinerja Admin Pereview'
    // Tambahkan jenis lain sesuai kebutuhan
];


if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['tampilkan_rekap'])) {
    $jenis_rekapitulasi_selected = isset($_GET['jenis_rekapitulasi']) ? $conn->real_escape_string(trim($_GET['jenis_rekapitulasi'])) : '';
    $tanggal_mulai_selected = isset($_GET['tanggal_mulai']) ? $conn->real_escape_string(trim($_GET['tanggal_mulai'])) : '';
    $tanggal_akhir_selected = isset($_GET['tanggal_akhir']) ? $conn->real_escape_string(trim($_GET['tanggal_akhir'])) : '';

    // Validasi
    if (empty($jenis_rekapitulasi_selected)) {
        $errors[] = "Jenis Rekapitulasi harus dipilih.";
    }
    if (empty($tanggal_mulai_selected) || !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $tanggal_mulai_selected)) {
        $errors[] = "Format Tanggal Mulai tidak valid (YYYY-MM-DD).";
    }
    if (empty($tanggal_akhir_selected) || !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $tanggal_akhir_selected)) {
        $errors[] = "Format Tanggal Akhir tidak valid (YYYY-MM-DD).";
    }
    if (!empty($tanggal_mulai_selected) && !empty($tanggal_akhir_selected) && $tanggal_mulai_selected > $tanggal_akhir_selected) {
        $errors[] = "Tanggal Mulai tidak boleh lebih besar dari Tanggal Akhir.";
    }

    if (empty($errors)) {
        // Logika untuk mengambil dan memproses data berdasarkan jenis rekapitulasi
        // Ini akan sangat bergantung pada bagaimana Anda ingin data direkapitulasi
        // dan bagaimana data disimpan (terutama jika menggunakan kolom JSON di tabel laporan_pajak)

        if ($jenis_rekapitulasi_selected == 'total_pbb_per_periode') {
            $sql = "SELECT SUM(jumlah_pbb_terutang) as total_pbb, COUNT(*) as jumlah_transaksi 
                    FROM perhitungan_pajak 
                    WHERE DATE(tanggal_perhitungan) BETWEEN ? AND ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $tanggal_mulai_selected, $tanggal_akhir_selected);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $rekapitulasi_results = $row; // Hasilnya adalah satu baris summary
                }
                $stmt->close();
            } else {
                $errors[] = "Gagal mempersiapkan query: " . $conn->error;
            }
        } elseif ($jenis_rekapitulasi_selected == 'jumlah_perhitungan_per_status') {
            $sql = "SELECT status_perhitungan, COUNT(*) as jumlah 
                    FROM perhitungan_pajak 
                    WHERE DATE(tanggal_perhitungan) BETWEEN ? AND ?
                    GROUP BY status_perhitungan";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $tanggal_mulai_selected, $tanggal_akhir_selected);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $rekapitulasi_results[] = $row; // Hasilnya bisa beberapa baris
                }
                $stmt->close();
            } else {
                $errors[] = "Gagal mempersiapkan query: " . $conn->error;
            }
        } elseif ($jenis_rekapitulasi_selected == 'kinerja_admin_pereview') {
            $sql = "SELECT p_admin.nama_lengkap as nama_admin, COUNT(pp.id_perhitungan) as jumlah_direview
                    FROM perhitungan_pajak pp
                    JOIN pengguna p_admin ON pp.id_admin_pereview = p_admin.id_pengguna
                    WHERE DATE(pp.tanggal_perhitungan) BETWEEN ? AND ?
                    GROUP BY pp.id_admin_pereview, p_admin.nama_lengkap
                    ORDER BY jumlah_direview DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $tanggal_mulai_selected, $tanggal_akhir_selected);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $rekapitulasi_results[] = $row;
                }
                $stmt->close();
            } else {
                $errors[] = "Gagal mempersiapkan query: " . $conn->error;
            }
        } else {
            $errors[] = "Jenis rekapitulasi belum diimplementasikan.";
        }

        if (empty($rekapitulasi_results) && empty($errors) && $jenis_rekapitulasi_selected != '') {
            $errors[] = "Tidak ada data ditemukan untuk kriteria rekapitulasi yang dipilih.";
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
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-rekaptulasi.css?v=<?php echo time(); ?>">
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
                <img src="<?php echo $foto_profil_admin; ?>" alt="Foto Profil <?php echo $nama_admin_session; ?>" class="admin-sidebar-profile-pic"
                    onerror="this.onerror=null;this.src='https://placehold.co/80x80/003366/ffffff?text=<?php echo substr($nama_admin_session, 0, 1); ?>';">
                <span class="admin-sidebar-user-name"><?php echo $nama_admin_session; ?></span>
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
                            <img src="<?php echo $foto_profil_admin; ?>" alt="Avatar <?php echo $nama_admin_session; ?>" class="admin-avatar-header"
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
                        Filter Rekapitulasi Laporan
                    </div>
                    <?php if (!empty($errors) && isset($_GET['tampilkan_rekap'])): ?>
                        <div class="auth-errors" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form action="rekapitulasi_laporan.php" method="GET" class="admin-form filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="jenis_rekapitulasi">Jenis Rekapitulasi</label>
                                <select name="jenis_rekapitulasi" id="jenis_rekapitulasi" required>
                                    <?php foreach ($jenis_rekap_options as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($jenis_rekapitulasi_selected == $value) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tanggal_mulai">Dari Tanggal</label>
                                <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="<?php echo htmlspecialchars($tanggal_mulai_selected); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="tanggal_akhir">Sampai Tanggal</label>
                                <input type="date" id="tanggal_akhir" name="tanggal_akhir" value="<?php echo htmlspecialchars($tanggal_akhir_selected); ?>" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="tampilkan_rekap" class="button btn-primary"><i class="fas fa-eye"></i> Tampilkan Rekapitulasi</button>
                        </div>
                    </form>
                </div>

                <?php if (isset($_GET['tampilkan_rekap']) && !empty($rekapitulasi_results) && empty($errors)): ?>
                    <div class="admin-card" id="hasil-rekapitulasi">
                        <div class="admin-card-header">
                            Hasil Rekapitulasi: <?php echo htmlspecialchars($jenis_rekap_options[$jenis_rekapitulasi_selected]); ?>
                            <small>(Periode: <?php echo htmlspecialchars(date('d M Y', strtotime($tanggal_mulai_selected))) . " - " . htmlspecialchars(date('d M Y', strtotime($tanggal_akhir_selected))); ?>)</small>
                            <div class="header-actions">
                                <button onclick="printLaporan('hasil-rekapitulasi', 'Rekapitulasi Laporan: <?php echo htmlspecialchars($jenis_rekap_options[$jenis_rekapitulasi_selected]); ?>', '<?php echo htmlspecialchars(date('d M Y', strtotime($tanggal_mulai_selected))) . " - " . htmlspecialchars(date('d M Y', strtotime($tanggal_akhir_selected))); ?>')" class="btn btn-print btn-sm"><i class="fas fa-print"></i> Cetak</button>
                            </div>
                        </div>

                        <div class="rekap-content">
                            <?php if ($jenis_rekapitulasi_selected == 'total_pbb_per_periode' && isset($rekapitulasi_results['total_pbb'])): ?>
                                <div class="report-summary">
                                    <p><strong>Total PBB Terutang:</strong> Rp <?php echo number_format($rekapitulasi_results['total_pbb'], 2, ',', '.'); ?></p>
                                    <p><strong>Jumlah Transaksi Perhitungan:</strong> <?php echo $rekapitulasi_results['jumlah_transaksi']; ?></p>
                                </div>
                            <?php elseif ($jenis_rekapitulasi_selected == 'jumlah_perhitungan_per_status' && !empty($rekapitulasi_results)): ?>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Status Perhitungan</th>
                                                <th class="text-center">Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rekapitulasi_results as $row): ?>
                                                <tr>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($row['status_perhitungan']))); ?></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($row['jumlah']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($jenis_rekapitulasi_selected == 'kinerja_admin_pereview' && !empty($rekapitulasi_results)): ?>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Nama Admin Pereview</th>
                                                <th class="text-center">Jumlah Perhitungan Direview</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rekapitulasi_results as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['nama_admin']); ?></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($row['jumlah_direview']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">Data rekapitulasi tidak tersedia atau jenis rekapitulasi belum terdefinisi dengan baik.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (isset($_GET['tampilkan_rekap']) && empty($rekapitulasi_results) && empty($errors)): ?>
                    <div class="admin-card">
                        <p class="text-center">Tidak ada data ditemukan untuk kriteria rekapitulasi yang Anda masukkan.</p>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <script>
        // Fungsi print generik
        function printLaporan(elementId, reportTitle, reportPeriod) {
            const printContents = document.getElementById(elementId).innerHTML;
            const originalContents = document.body.innerHTML;

            const headerHtml = `
                <div style="text-align:center; margin-bottom:20px; font-family: Arial, sans-serif;">
                    <img src="<?php echo BASE_URL_ADMIN; ?>assets/images/logo_djp_admin.png" alt="Logo" style="height:50px; margin-bottom:10px;">
                    <h2>${reportTitle}</h2>
                    <p>Periode: ${reportPeriod}</p>
                    <hr style="margin-top:15px;">
                </div>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
                    th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .action-buttons, .header-actions { display: none !important; } /* Sembunyikan kolom/tombol aksi saat cetak */
                    .text-right { text-align: right !important; }
                    .text-center { text-align: center !important; }
                    .report-summary, .rekap-content { margin-bottom: 15px; padding: 10px; border: 1px solid #eee; background-color: #f9f9f9;}
                    .report-summary p, .rekap-content p { margin: 5px 0; }
                    .admin-card-header small { display: block; font-size: 0.9em; color: #555; margin-top: 5px;}
                </style>
            `;

            // Sembunyikan elemen yang tidak ingin dicetak dari printContents
            let tempDiv = document.createElement('div');
            tempDiv.innerHTML = printContents;
            // Contoh: Sembunyikan tombol cetak/export di dalam konten yang akan dicetak
            let actionsToHide = tempDiv.querySelectorAll('.header-actions');
            actionsToHide.forEach(el => el.style.display = 'none');

            document.body.innerHTML = headerHtml + tempDiv.innerHTML;
            window.print();
            document.body.innerHTML = originalContents;
            // Re-initialize event listeners if needed
        }
    </script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>