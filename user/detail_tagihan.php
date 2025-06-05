<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("../php/config.php");

// Proteksi halaman user
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'user') {
    header("Location: " . BASE_URL_USER_ROOT . "login.php");
    exit();
}

// Data Pengguna dari Session
$id_user_logged_in = $_SESSION['id_pengguna'];
$nama_user_session = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Pengguna');
$foto_profil_user_session = BASE_URL_USER_ROOT . 'assets/images/default_avatar_user.png';

// Pengaturan untuk halaman ini
$page_title_user = "Detail Tagihan Pajak";
$current_page_user = 'informasi_tagihan'; // Agar menu "Informasi Tagihan Pajak" tetap aktif
$page_title_for_header = $page_title_user;

require_once '../php/db_connect.php';

$errors = [];
$tagihan_data = null; // Untuk menampung data perhitungan/tagihan
$id_perhitungan_to_view = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_perhitungan_to_view = intval($_GET['id']);

    // Ambil data perhitungan pajak lengkap, pastikan milik user yang login
    $stmt = $conn->prepare("
        SELECT pp.*, 
               p.nama_lengkap AS nama_wajib_pajak, p.nik AS nik_wajib_pajak,
               du.npwp, du.nama_pemilik_bangunan, du.alamat_objek_pajak, du.luas_bangunan, du.luas_tanah, du.jenis_bangunan,
               admin.nama_lengkap AS nama_admin_pereview
        FROM perhitungan_pajak pp
        JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
        JOIN pengguna p ON du.id_pengguna = p.id_pengguna
        LEFT JOIN pengguna admin ON pp.id_admin_pereview = admin.id_pengguna
        WHERE pp.id_perhitungan = ? AND du.id_pengguna = ?
    ");

    if ($stmt) {
        $stmt->bind_param("ii", $id_perhitungan_to_view, $id_user_logged_in);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $tagihan_data = $result->fetch_assoc();
        } else {
            $errors[] = "Data tagihan pajak tidak ditemukan atau Anda tidak berhak mengaksesnya.";
        }
        $stmt->close();
    } else {
        $errors[] = "Gagal mengambil data tagihan pajak: " . $conn->error;
    }
} else {
    $errors[] = "ID Tagihan tidak valid atau tidak disediakan.";
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - InfoPajak'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/icon.png">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT . $root_project; ?>assets/css/user_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT . $root_project; ?>assets/css/user-detail-tagihan.css?v=<?php echo time(); ?>">

    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT . $root_project; ?>assets/css/user-detail-tagihan-content.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body>
    <div class="user-page-wrapper">
        <button class="user-sidebar-toggle-button" id="user-sidebar-toggle" aria-label="Toggle Sidebar" aria-expanded="true">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>

        <aside class="user-sidebar" id="user-main-sidebar">
            <div class="user-sidebar-header">
                <img src="<?php echo $foto_profil_user_session; ?>" alt="Foto Profil <?php echo $nama_user_session; ?>" class="user-sidebar-profile-pic"
                    onerror="this.onerror=null;this.src='https://placehold.co/70x70/191970/ffffff?text=<?php echo substr($nama_user_session, 0, 1); ?>';">
                <span class="user-sidebar-user-name"><?php echo $nama_user_session; ?></span>
            </div>
            <nav class="user-sidebar-nav">
                <ul>
                    <li class="<?php echo ($current_page_user == 'dashboard_user') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/index.php">
                            <i class="fas fa-tachometer-alt fa-fw"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="<?php echo ($current_page_user == 'daftar_objek_pajak') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/daftar_objek_pajak.php"> <!-- Diarahkan ke halaman daftar -->
                            <i class="fas fa-landmark fa-fw"></i>
                            <span>Data Objek Pajak</span>
                        </a>
                    </li>
                    <li class="<?php echo ($current_page_user == 'informasi_tagihan') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/informasi_tagihan.php">
                            <i class="fas fa-file-invoice-dollar fa-fw"></i>
                            <span>Informasi Tagihan Pajak</span>
                        </a>
                    </li>
                    <li class="<?php echo ($current_page_user == 'pengaturan_akun_user') ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/pengaturan_akun.php">
                            <i class="fas fa-user-cog fa-fw"></i>
                            <span>Pengaturan Akun</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>logout.php">
                            <i class="fas fa-sign-out-alt fa-fw"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="user-sidebar-footer">
                <p>&copy; <?php echo date("Y"); ?> InfoPajak</p>
            </div>
        </aside>

        <main class="user-main-content" id="user-main-content-area">
            <header class="user-content-header">
                <h1 class="user-header-page-title"><?php echo htmlspecialchars($page_title_for_header); ?></h1>
                <div class="header-right">
                    <div class="user-profile-dropdown">
                        <a href="#" class="user-profile-link-header" id="user-profile-dropdown-toggle">
                            <img src="<?php echo $foto_profil_user_session; ?>" alt="Avatar" class="user-avatar-header" onerror="this.onerror=null;this.src='https://placehold.co/30x30/cccccc/000000?text=<?php echo substr($nama_user_session, 0, 1); ?>';">
                            <span><?php echo $nama_user_session; ?></span>
                            <i class="fas fa-chevron-down dropdown-arrow-header" style="font-size:0.8em; margin-left:5px;"></i>
                        </a>
                        <div class="profile-dropdown-menu" id="user-profile-menu" style="right:0; width:180px;">
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>user/pengaturan_akun.php">Pengaturan Akun</a>
                            <hr>
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="user-content-inner">
                <div class="user-card calculation-detail-card">
                    <div class="user-card-header">
                        Detail Tagihan Pajak #<?php echo $tagihan_data ? htmlspecialchars($tagihan_data['id_perhitungan']) : 'N/A'; ?>
                        <?php if ($tagihan_data): ?>
                            <div class="header-actions">
                                <button onclick="window.print();" class="btn btn-print btn-sm"><i class="fas fa-print"></i> Cetak Rincian</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="flash-message error" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tagihan_data): ?>
                        <div class="calculation-details"> <!-- Kelas spesifik untuk styling detail -->
                            <div class="detail-section">
                                <h3>Informasi Wajib Pajak</h3>
                                <p><strong>Nama Wajib Pajak:</strong> <?php echo htmlspecialchars($tagihan_data['nama_wajib_pajak']); ?></p>
                                <p><strong>NIK:</strong> <?php echo htmlspecialchars($tagihan_data['nik_wajib_pajak']); ?></p>
                            </div>

                            <div class="detail-section">
                                <h3>Informasi Objek Pajak</h3>
                                <p><strong>NPWP:</strong> <?php echo htmlspecialchars($tagihan_data['npwp'] ?? '-'); ?></p>
                                <p><strong>Alamat Objek Pajak:</strong> <?php echo nl2br(htmlspecialchars($tagihan_data['alamat_objek_pajak'] ?? '-')); ?></p>
                                <p><strong>Jenis Bangunan:</strong> <?php echo htmlspecialchars($tagihan_data['jenis_bangunan'] ?? '-'); ?></p>
                                <p><strong>Luas Bangunan:</strong> <?php echo number_format(floatval($tagihan_data['luas_bangunan']), 2, ',', '.'); ?> m²</p>
                                <p><strong>Luas Tanah:</strong> <?php echo number_format(floatval($tagihan_data['luas_tanah']), 2, ',', '.'); ?> m²</p>
                            </div>

                            <div class="detail-section">
                                <h3>Rincian Perhitungan PBB</h3>
                                <p><strong>Periode Pajak Tahun:</strong> <?php echo htmlspecialchars($tagihan_data['periode_pajak_tahun']); ?></p>
                                <p><strong>Tanggal Perhitungan:</strong> <?php echo htmlspecialchars(date('d F Y, H:i', strtotime($tagihan_data['tanggal_perhitungan']))); ?></p>
                                <p><strong>Dihitung oleh Admin:</strong> <?php echo htmlspecialchars($tagihan_data['nama_admin_pereview'] ?? 'Sistem'); ?></p>
                                <hr>
                                <p><strong>NJOP Bangunan / m²:</strong> Rp <?php echo number_format($tagihan_data['njop_bangunan_per_meter'], 2, ',', '.'); ?></p>
                                <p><strong>NJOP Tanah / m²:</strong> Rp <?php echo number_format($tagihan_data['njop_tanah_per_meter'], 2, ',', '.'); ?></p>
                                <p><strong>Total NJOP Bangunan:</strong> Rp <?php echo number_format($tagihan_data['total_njop_bangunan'], 2, ',', '.'); ?></p>
                                <p><strong>Total NJOP Tanah:</strong> Rp <?php echo number_format($tagihan_data['total_njop_tanah'], 2, ',', '.'); ?></p>
                                <p><strong>NJOP Total Objek Pajak:</strong> Rp <?php echo number_format($tagihan_data['njop_total_objek_pajak'], 2, ',', '.'); ?></p>
                                <p><strong>NJOPTKP:</strong> Rp <?php echo number_format($tagihan_data['njoptkp'], 2, ',', '.'); ?></p>
                                <p><strong>NJOP Kena Pajak (NJKP):</strong> Rp <?php echo number_format($tagihan_data['njkp'], 2, ',', '.'); ?></p>
                                <p><strong>Persentase PBB:</strong> <?php echo rtrim(rtrim(number_format($tagihan_data['persentase_pbb'] * 100, 2, ',', '.'), '0'), ','); ?> %</p>
                                <h4 class="pbb-terutang"><strong>Jumlah PBB Terutang:</strong> Rp <?php echo number_format($tagihan_data['jumlah_pbb_terutang'], 2, ',', '.'); ?></h4>
                            </div>

                            <div class="detail-section">
                                <h3>Status</h3>
                                <p><strong>Status Verifikasi Data oleh Admin:</strong> <span class="status-<?php echo htmlspecialchars(str_replace('_', '-', $tagihan_data['status_verifikasi_data_user'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($tagihan_data['status_verifikasi_data_user']))); ?></span></p>
                                <p><strong>Status Perhitungan:</strong> <span class="status-<?php echo htmlspecialchars(str_replace('_', '-', $tagihan_data['status_perhitungan'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($tagihan_data['status_perhitungan']))); ?></span></p>
                                <?php if (!empty($tagihan_data['catatan_admin'])): ?>
                                    <p><strong>Catatan dari Admin:</strong></p>
                                    <div class="catatan-box">
                                        <?php echo nl2br(htmlspecialchars($tagihan_data['catatan_admin'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-actions" style="justify-content: space-between; margin-top: 20px; border-top:none; padding-top:0;">
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>user/informasi_tagihan.php" class="button btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Tagihan</a>
                            <?php // Tombol bayar atau konfirmasi bisa ditambahkan di sini
                            // if($tagihan_data['status_perhitungan'] == 'final'): 
                            ?>
                            <!-- <a href="#" class="button btn-success"><i class="fas fa-money-check-alt"></i> Konfirmasi Pembayaran (Contoh)</a> -->
                            <?php // endif; 
                            ?>
                        </div>
                    <?php else: ?>
                        <?php if (empty($errors)): ?>
                            <p>Data tagihan tidak dapat ditampilkan karena ID tidak valid atau data tidak ditemukan.</p>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/informasi_tagihan.php" class="button btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Tagihan</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_USER_ROOT . $root_project; ?>assets/js/user_script.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userProfileDropdownToggle = document.getElementById('user-profile-dropdown-toggle');
            const userProfileMenu = document.getElementById('user-profile-menu');

            if (userProfileDropdownToggle && userProfileMenu) {
                userProfileDropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    this.parentElement.classList.toggle('open');
                });

                document.addEventListener('click', function(e) {
                    if (userProfileDropdownToggle && !userProfileDropdownToggle.contains(e.target) && !userProfileMenu.contains(e.target)) {
                        if (userProfileDropdownToggle.parentElement.classList.contains('open')) {
                            userProfileDropdownToggle.parentElement.classList.remove('open');
                        }
                    }
                });
            }
        });
    </script>
    <?php
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ?>
</body>

</html>