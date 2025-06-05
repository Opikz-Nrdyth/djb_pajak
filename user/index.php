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
$page_title_user = "Dashboard Pengguna";
$current_page_user = 'dashboard_user';
$page_title_for_header = $page_title_user;

require_once '../php/db_connect.php';

// Data untuk Ringkasan Cepat
$status_kelengkapan_data = "Belum Lengkap";
$jumlah_objek_pajak_dimiliki = 0;
$jumlah_pajak_terhitung = 0;
$total_pajak_terhitung_nominal = 0.00;

// 1. Cek Status Kelengkapan Data Objek Pajak
$stmt_cek_djp = $conn->prepare("SELECT COUNT(*) as total_objek, SUM(CASE WHEN npwp IS NOT NULL AND alamat_objek_pajak IS NOT NULL AND luas_bangunan > 0 AND luas_tanah > 0 AND jenis_bangunan IS NOT NULL THEN 1 ELSE 0 END) as objek_lengkap FROM data_djp_user WHERE id_pengguna = ?");
if ($stmt_cek_djp) {
    $stmt_cek_djp->bind_param("i", $id_user_logged_in);
    $stmt_cek_djp->execute();
    $result_djp = $stmt_cek_djp->get_result();
    if ($row_djp = $result_djp->fetch_assoc()) {
        $jumlah_objek_pajak_dimiliki = $row_djp['total_objek'];
        if ($row_djp['total_objek'] > 0) {
            if ($row_djp['objek_lengkap'] == $row_djp['total_objek']) {
                $status_kelengkapan_data = "Lengkap";
            } else if ($row_djp['objek_lengkap'] > 0) {
                $status_kelengkapan_data = "Sebagian Lengkap";
            } else {
                $status_kelengkapan_data = "Belum Lengkap";
            }
        } else {
            $status_kelengkapan_data = "Belum Ada Data Objek";
        }
    }
    $stmt_cek_djp->close();
}

// 2. Jumlah Pajak Terhitung & 3. Total Pajak Terhitung (Semua Periode)
$stmt_rekap_pajak = $conn->prepare(
    "SELECT COUNT(pp.id_perhitungan) as jumlah_perhitungan, SUM(pp.jumlah_pbb_terutang) as total_terutang
     FROM perhitungan_pajak pp
     JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
     WHERE du.id_pengguna = ? AND pp.status_perhitungan = 'dikirim_ke_user'"
); // hanya status perhitungan yang dikirim ke user
if ($stmt_rekap_pajak) {
    $stmt_rekap_pajak->bind_param("i", $id_user_logged_in);
    $stmt_rekap_pajak->execute();
    $result_rekap = $stmt_rekap_pajak->get_result();
    if ($row_rekap = $result_rekap->fetch_assoc()) {
        $jumlah_pajak_terhitung = intval($row_rekap['jumlah_perhitungan']);
        $total_pajak_terhitung_nominal = floatval($row_rekap['total_terutang']);
    }
    $stmt_rekap_pajak->close();
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user-index.css?v=<?php echo time(); ?>">

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
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/daftar_objek_pajak.php">
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
                        <a href="#" class="user-profile-link-header" id="user-profile-dropdown-toggle" onclick="event.preventDefault(); document.getElementById('user-profile-menu').classList.toggle('open'); this.parentElement.classList.toggle('open');">
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
                <section class="user-hero-section-simple">
                    <h1 class="greeting">Hi, <strong><?php echo $nama_user_session; ?></strong></h1>
                    <p class="welcome-message">SELAMAT DATANG DI SISTEM INFORMASI PAJAK BANGUNAN</p>
                    <div class="animate-lottie">
                        <dotlottie-player
                            src="https://lottie.host/2ec9df8e-8468-400e-8202-ab04cdce3254/U0OYTZiu0w.lottie"
                            background="transparent"
                            speed="1"
                            style="width: 400px; height: 400px"
                            loop
                            autoplay></dotlottie-player>
                    </div>
                </section>

                <!-- Bagian Ringkasan Cepat -->
                <div class="user-dashboard-quick-summary">
                    <div class="summary-card">
                        <i class="fas fa-file-signature"></i>
                        <h4>Status Kelengkapan Data</h4>
                        <p class="value <?php
                                        if ($status_kelengkapan_data == 'Lengkap') echo 'status-ok';
                                        elseif ($status_kelengkapan_data == 'Belum Ada Data' || $status_kelengkapan_data == 'Belum Lengkap') echo 'status-error';
                                        else echo 'status-warning'; // Untuk Sebagian Lengkap
                                        ?>">
                            <?php echo htmlspecialchars($status_kelengkapan_data); ?>
                        </p>
                    </div>
                    <div class="summary-card">
                        <i class="fas fa-calculator"></i>
                        <h4>Jumlah Pajak Terhitung</h4>
                        <p class="value status-neutral">
                            <?php echo $jumlah_pajak_terhitung; ?> Perhitungan
                        </p>
                    </div>
                    <div class="summary-card">
                        <i class="fas fa-wallet"></i>
                        <h4>Total Estimasi Pajak</h4>
                        <p class="value status-neutral">
                            Rp <?php echo number_format($total_pajak_terhitung_nominal, 2, ',', '.'); ?>
                        </p>
                    </div>
                </div>

                <div class="user-dashboard-actions">
                    <a href="<?php echo BASE_URL_USER_ROOT; ?>user/daftar_objek_pajak.php" class="button btn-primary">
                        <i class="fas fa-edit"></i> Kelola Data Objek Pajak
                    </a>
                    <a href="<?php echo BASE_URL_USER_ROOT; ?>user/informasi_tagihan.php" class="button btn-info">
                        <i class="fas fa-receipt"></i> Lihat Informasi Tagihan
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_USER_ROOT; ?>assets/js/user_script.js?v=<?php echo time(); ?>"></script>
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

    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
</body>

</html>