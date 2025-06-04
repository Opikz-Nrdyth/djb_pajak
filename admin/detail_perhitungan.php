<?php
// admin/detail_perhitungan.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("../php/config.php");

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
$page_title_admin = "Detail Perhitungan Pajak";
$current_page = 'tagihan_perhitungan';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$perhitungan_data = null;
$id_perhitungan_to_view = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_perhitungan_to_view = intval($_GET['id']);

    // Ambil data perhitungan pajak lengkap
    $stmt = $conn->prepare("
        SELECT pp.*, 
               p.nama_lengkap AS nama_wajib_pajak, p.nik AS nik_wajib_pajak, p.email AS email_wajib_pajak, p.no_telepon AS telepon_wajib_pajak,
               du.npwp, du.nama_pemilik_bangunan, du.alamat_objek_pajak, du.luas_bangunan, du.luas_tanah, du.jenis_bangunan,
               admin.nama_lengkap AS nama_admin_pereview
        FROM perhitungan_pajak pp
        JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
        JOIN pengguna p ON du.id_pengguna = p.id_pengguna
        JOIN pengguna admin ON pp.id_admin_pereview = admin.id_pengguna
        WHERE pp.id_perhitungan = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id_perhitungan_to_view);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $perhitungan_data = $result->fetch_assoc();
        } else {
            $errors[] = "Data perhitungan pajak tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $errors[] = "Gagal mengambil data perhitungan: " . $conn->error;
    }
} else {
    $errors[] = "ID Perhitungan tidak valid atau tidak disediakan.";
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - Admin Panel InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-detail-perhitungan.css?v=<?php echo time(); ?>">
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
                <div class="admin-card calculation-detail-card">
                    <div class="admin-card-header">
                        Detail Perhitungan Pajak #<?php echo $perhitungan_data ? htmlspecialchars($perhitungan_data['id_perhitungan']) : 'N/A'; ?>
                        <?php if ($perhitungan_data): ?>
                            <div class="header-actions">
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/edit_perhitungan.php?id=<?php echo $id_perhitungan_to_view; ?>" class="btn btn-edit btn-sm"><i class="fas fa-edit"></i> Edit</a>
                                <button onclick="window.print();" class="btn btn-print btn-sm"><i class="fas fa-print"></i> Cetak</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($perhitungan_data): ?>
                        <div class="calculation-details">
                            <div class="detail-section">
                                <h3>Informasi Wajib Pajak</h3>
                                <p><strong>Nama Wajib Pajak:</strong> <?php echo htmlspecialchars($perhitungan_data['nama_wajib_pajak']); ?></p>
                                <p><strong>NIK:</strong> <?php echo htmlspecialchars($perhitungan_data['nik_wajib_pajak']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($perhitungan_data['email_wajib_pajak']); ?></p>
                                <p><strong>No. Telepon:</strong> <?php echo htmlspecialchars($perhitungan_data['telepon_wajib_pajak']); ?></p>
                            </div>

                            <div class="detail-section">
                                <h3>Informasi Objek Pajak</h3>
                                <p><strong>NPWP:</strong> <?php echo htmlspecialchars($perhitungan_data['npwp'] ?? '-'); ?></p>
                                <p><strong>Nama Pemilik (sesuai PBB):</strong> <?php echo htmlspecialchars($perhitungan_data['nama_pemilik_bangunan'] ?? '-'); ?></p>
                                <p><strong>Alamat Objek Pajak:</strong> <?php echo nl2br(htmlspecialchars($perhitungan_data['alamat_objek_pajak'] ?? '-')); ?></p>
                                <p><strong>Jenis Bangunan:</strong> <?php echo htmlspecialchars($perhitungan_data['jenis_bangunan'] ?? '-'); ?></p>
                                <p><strong>Luas Bangunan:</strong> <?php echo number_format(floatval($perhitungan_data['luas_bangunan']), 2, ',', '.'); ?> m²</p>
                                <p><strong>Luas Tanah:</strong> <?php echo number_format(floatval($perhitungan_data['luas_tanah']), 2, ',', '.'); ?> m²</p>
                            </div>

                            <div class="detail-section">
                                <h3>Detail Perhitungan PBB</h3>
                                <p><strong>Periode Pajak Tahun:</strong> <?php echo htmlspecialchars($perhitungan_data['periode_pajak_tahun']); ?></p>
                                <p><strong>Tanggal Perhitungan:</strong> <?php echo htmlspecialchars(date('d F Y, H:i', strtotime($perhitungan_data['tanggal_perhitungan']))); ?></p>
                                <p><strong>Admin Pereview:</strong> <?php echo htmlspecialchars($perhitungan_data['nama_admin_pereview']); ?></p>
                                <hr>
                                <p><strong>NJOP Bangunan / m²:</strong> Rp <?php echo number_format($perhitungan_data['njop_bangunan_per_meter'], 2, ',', '.'); ?></p>
                                <p><strong>NJOP Tanah / m²:</strong> Rp <?php echo number_format($perhitungan_data['njop_tanah_per_meter'], 2, ',', '.'); ?></p>
                                <p><strong>Total NJOP Bangunan:</strong> Rp <?php echo number_format($perhitungan_data['total_njop_bangunan'], 2, ',', '.'); ?></p>
                                <p><strong>Total NJOP Tanah:</strong> Rp <?php echo number_format($perhitungan_data['total_njop_tanah'], 2, ',', '.'); ?></p>
                                <p><strong>NJOP Total Objek Pajak:</strong> Rp <?php echo number_format($perhitungan_data['njop_total_objek_pajak'], 2, ',', '.'); ?></p>
                                <p><strong>NJOPTKP:</strong> Rp <?php echo number_format($perhitungan_data['njoptkp'], 2, ',', '.'); ?></p>
                                <p><strong>NJOP Kena Pajak (NJKP):</strong> Rp <?php echo number_format($perhitungan_data['njkp'], 2, ',', '.'); ?></p>
                                <p><strong>Persentase PBB:</strong> <?php echo rtrim(rtrim(number_format($perhitungan_data['persentase_pbb'] * 100, 2, ',', '.'), '0'), ','); ?> %</p>
                                <h4 class="pbb-terutang"><strong>Jumlah PBB Terutang:</strong> Rp <?php echo number_format($perhitungan_data['jumlah_pbb_terutang'], 2, ',', '.'); ?></h4>
                            </div>

                            <div class="detail-section">
                                <h3>Status & Catatan</h3>
                                <p><strong>Status Verifikasi Data User:</strong> <span class="status-<?php echo htmlspecialchars(str_replace('_', '-', $perhitungan_data['status_verifikasi_data_user'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($perhitungan_data['status_verifikasi_data_user']))); ?></span></p>
                                <p><strong>Status Perhitungan:</strong> <span class="status-<?php echo htmlspecialchars(str_replace('_', '-', $perhitungan_data['status_perhitungan'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($perhitungan_data['status_perhitungan']))); ?></span></p>
                                <p><strong>Catatan Admin:</strong></p>
                                <div class="catatan-box">
                                    <?php echo nl2br(htmlspecialchars($perhitungan_data['catatan_admin'] ?? 'Tidak ada catatan.')); ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions" style="justify-content: flex-start; margin-top: 20px; border-top:none; padding-top:0;">
                            <a href="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php" class="button btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar</a>
                        </div>
                    <?php else: ?>
                        <?php if (empty($errors)): ?>
                            <p>Data perhitungan tidak dapat ditampilkan karena ID tidak valid atau data tidak ditemukan.</p>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php" class="button btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar</a>
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