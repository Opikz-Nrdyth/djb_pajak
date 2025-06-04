<?php
// admin/tagihan_perhitungan.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("../php/config.php");

// Proteksi halaman
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL_ADMIN . "login.php");
    exit();
}

// Data Admin
$id_admin_logged_in = $_SESSION['id_pengguna'];
$nama_admin_session = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin');
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan Halaman
$page_title_admin = "Tagihan & Perhitungan Pajak";
$current_page = 'tagihan_perhitungan';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

// Flash Message
$flash_message = '';
$flash_message_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_message_type = isset($_SESSION['flash_message_type']) ? $_SESSION['flash_message_type'] : 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']);
}

// Filter dan Pencarian Data
$perhitungan_list = [];
$search_query = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : '';
$filter_tahun = isset($_GET['tahun']) && is_numeric($_GET['tahun']) ? intval($_GET['tahun']) : date("Y");
$filter_status_verifikasi = isset($_GET['status_verifikasi']) ? $conn->real_escape_string($_GET['status_verifikasi']) : '';
$filter_status_perhitungan = isset($_GET['status_perhitungan']) ? $conn->real_escape_string($_GET['status_perhitungan']) : '';

$sql_perhitungan = "SELECT pp.id_perhitungan, pp.periode_pajak_tahun, pp.tanggal_perhitungan, pp.jumlah_pbb_terutang, 
                           pp.status_verifikasi_data_user, pp.status_perhitungan,
                           p.nama_lengkap as nama_wajib_pajak, p.nik as nik_wajib_pajak,
                           admin.nama_lengkap as nama_admin_pereview
                    FROM perhitungan_pajak pp
                    JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
                    JOIN pengguna p ON du.id_pengguna = p.id_pengguna
                    LEFT JOIN pengguna admin ON pp.id_admin_pereview = admin.id_pengguna 
                    WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_tahun)) {
    $sql_perhitungan .= " AND pp.periode_pajak_tahun = ?";
    $params[] = $filter_tahun;
    $types .= "i";
}
if (!empty($search_query)) {
    $sql_perhitungan .= " AND (p.nama_lengkap LIKE ? OR p.nik LIKE ? OR du.npwp LIKE ?)"; // Asumsi ada du.npwp di data_djp_user
    $search_param = "%" . $search_query . "%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= "sss";
}
if (!empty($filter_status_verifikasi)) {
    $sql_perhitungan .= " AND pp.status_verifikasi_data_user = ?";
    array_push($params, $filter_status_verifikasi);
    $types .= "s";
}
if (!empty($filter_status_perhitungan)) {
    $sql_perhitungan .= " AND pp.status_perhitungan = ?";
    array_push($params, $filter_status_perhitungan);
    $types .= "s";
}
$sql_perhitungan .= " ORDER BY pp.tanggal_perhitungan DESC";

$stmt_perhitungan = $conn->prepare($sql_perhitungan);
if ($stmt_perhitungan) {
    if (!empty($types) && !empty($params)) {
        $stmt_perhitungan->bind_param($types, ...$params);
    }
    $stmt_perhitungan->execute();
    $result_perhitungan = $stmt_perhitungan->get_result();
    if ($result_perhitungan && $result_perhitungan->num_rows > 0) {
        while ($row = $result_perhitungan->fetch_assoc()) {
            $perhitungan_list[] = $row;
        }
    }
    $stmt_perhitungan->close();
} else {
    // error_log("Prepare statement failed for perhitungan_list: " . $conn->error);
    // Anda bisa menambahkan pesan error untuk user jika perlu
}

// Daftar tahun yang tersedia untuk filter
$available_years = [];
$current_year_for_select = date("Y");
$year_query_sql = "SELECT DISTINCT periode_pajak_tahun FROM perhitungan_pajak ORDER BY periode_pajak_tahun DESC";
$year_result = $conn->query($year_query_sql);
if ($year_result) {
    while ($yr_row = $year_result->fetch_assoc()) {
        $available_years[] = $yr_row['periode_pajak_tahun'];
    }
}
// Pastikan tahun saat ini ada di daftar jika belum ada data untuk tahun tsb atau tidak ada data sama sekali
if (empty($available_years) || !in_array($current_year_for_select, $available_years)) {
    if (!in_array($current_year_for_select, $available_years)) { // Hindari duplikasi jika sudah ada karena query tapi tahun filter beda
        array_push($available_years, $current_year_for_select);
    }
    if (empty($available_years)) $available_years[] = $current_year_for_select; // Jika tabel kosong sama sekali
    rsort($available_years); // Urutkan descending
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - Admin Panel InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-tagihan-perhitungan.css?v=<?php echo time(); ?>">
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
                    <li class="<?php echo ($current_page == 'dashboard_admin' || $current_page == 'index') ? 'active' : ''; // Standardized to index for dashboard 
                                ?>">
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
                <div class="header-center">
                    <form action="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php" method="GET" class="header-search-form">
                        <input type="search" name="q" placeholder="Cari NIK, Nama WP, NPWP..." aria-label="Search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <input type="hidden" name="tahun" value="<?php echo htmlspecialchars($filter_tahun); ?>">
                        <input type="hidden" name="status_verifikasi" value="<?php echo htmlspecialchars($filter_status_verifikasi); ?>">
                        <input type="hidden" name="status_perhitungan" value="<?php echo htmlspecialchars($filter_status_perhitungan); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
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
                <?php if (!empty($flash_message)): ?>
                    <div class="flash-message <?php echo $flash_message_type === 'success' ? 'auth-success' : 'auth-errors'; ?>" style="margin-bottom: 15px;">
                        <p><?php echo htmlspecialchars($flash_message); ?></p>
                    </div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="page-header">
                        <h1>Daftar Perhitungan Pajak</h1>
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/buat_perhitungan.php" class="button btn-primary btn-add-new"> <i class="fas fa-calculator"></i> Buat Perhitungan Baru
                        </a>
                    </div>

                    <form action="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php" method="GET" class="filter-form-inline">
                        <div class="form-group">
                            <label for="filter_tahun">Tahun Periode:</label>
                            <select name="tahun" id="filter_tahun" onchange="this.form.submit()">
                                <?php foreach ($available_years as $year_option): ?>
                                    <option value="<?php echo $year_option; ?>" <?php echo ($filter_tahun == $year_option) ? 'selected' : ''; ?>>
                                        <?php echo $year_option; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_status_verifikasi">Status Verifikasi Data:</label>
                            <select name="status_verifikasi" id="filter_status_verifikasi" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <option value="lengkap" <?php echo ($filter_status_verifikasi == 'lengkap') ? 'selected' : ''; ?>>Lengkap</option>
                                <option value="belum_lengkap" <?php echo ($filter_status_verifikasi == 'belum_lengkap') ? 'selected' : ''; ?>>Belum Lengkap</option>
                                <option value="perlu_revisi" <?php echo ($filter_status_verifikasi == 'perlu_revisi') ? 'selected' : ''; ?>>Perlu Revisi</option>
                                <option value="diverifikasi" <?php echo ($filter_status_verifikasi == 'diverifikasi') ? 'selected' : ''; ?>>Diverifikasi</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_status_perhitungan">Status Perhitungan:</label>
                            <select name="status_perhitungan" id="filter_status_perhitungan" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <option value="draft" <?php echo ($filter_status_perhitungan == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="final" <?php echo ($filter_status_perhitungan == 'final') ? 'selected' : ''; ?>>Final</option>
                                <option value="dikirim_ke_user" <?php echo ($filter_status_perhitungan == 'dikirim_ke_user') ? 'selected' : ''; ?>>Dikirim ke User</option>
                            </select>
                        </div>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                        <noscript><button type="submit" class="button btn-primary btn-sm">Filter</button></noscript>
                    </form>

                    <div class="table-container">
                        <table class="data-table" id="tabelPerhitungan">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Wajib Pajak (NIK)</th>
                                    <th class="text-center">Tahun</th>
                                    <th>Tgl. Hitung</th>
                                    <th class="text-right">Jml. PBB</th>
                                    <th>Status Verifikasi & Hitung</th>
                                    <th>Nama Admin</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php if (!empty($perhitungan_list)): ?>
                                    <?php foreach ($perhitungan_list as $ph): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ph['id_perhitungan']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($ph['nama_wajib_pajak']); ?><br>
                                                <small>(NIK: <?php echo htmlspecialchars($ph['nik_wajib_pajak']); ?>)</small>
                                            </td>
                                            <td class="text-center"><?php echo htmlspecialchars($ph['periode_pajak_tahun']); ?></td>
                                            <td><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($ph['tanggal_perhitungan']))); ?></td>
                                            <td class="text-right">Rp <?php echo number_format($ph['jumlah_pbb_terutang'], 2, ',', '.'); ?></td>
                                            <td class="status-<?php echo htmlspecialchars(str_replace('_', '-', $ph['status_verifikasi_data_user'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($ph['status_verifikasi_data_user']))); ?>
                                            </td>
                                            <td class="status-<?php echo htmlspecialchars(str_replace('_', '-', $ph['status_perhitungan'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($ph['status_perhitungan']))); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($ph['nama_admin_pereview'] ?? 'N/A'); ?></td>
                                            <td class="action-buttons">
                                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/detail_perhitungan.php?id=<?php echo $ph['id_perhitungan']; ?>" class="button btn-view btn-sm" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i> <span>Detail</span>
                                                </a>
                                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/edit_perhitungan.php?id=<?php echo $ph['id_perhitungan']; ?>" class="button btn-edit btn-sm" title="Edit Perhitungan">
                                                    <i class="fas fa-edit"></i> <span>Edit</span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center" style="padding: 20px;">Tidak ada data perhitungan pajak yang ditemukan untuk filter yang dipilih.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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