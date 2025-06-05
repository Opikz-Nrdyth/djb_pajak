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
$page_title_user = "Informasi Tagihan Pajak Saya";
$current_page_user = 'informasi_tagihan';
$page_title_for_header = $page_title_user;

require_once '../php/db_connect.php';

$errors = [];
$daftar_tagihan = [];

// Ambil data tagihan/perhitungan pajak untuk pengguna yang login
$stmt_tagihan = $conn->prepare("
    SELECT pp.id_perhitungan, pp.periode_pajak_tahun, pp.tanggal_perhitungan, 
           pp.jumlah_pbb_terutang, pp.status_perhitungan, du.alamat_objek_pajak
    FROM perhitungan_pajak pp
    JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
    WHERE du.id_pengguna = ? && pp.status_perhitungan = 'dikirim_ke_user'
    ORDER BY pp.periode_pajak_tahun DESC, pp.tanggal_perhitungan DESC
");

if ($stmt_tagihan) {
    $stmt_tagihan->bind_param("i", $id_user_logged_in);
    $stmt_tagihan->execute();
    $result_tagihan = $stmt_tagihan->get_result();
    if ($result_tagihan->num_rows > 0) {
        while ($row = $result_tagihan->fetch_assoc()) {
            $daftar_tagihan[] = $row;
        }
    }
    $stmt_tagihan->close();
} else {
    $errors[] = "Gagal mengambil data tagihan pajak: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user-informasi-tagihan.css?v=<?php echo time(); ?>">

    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user-informasi-tagihan-content.css?v=<?php echo time(); ?>">
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
                <div class="user-card">
                    <div class="user-card-header">
                        Daftar Tagihan Pajak Anda
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="flash-message error" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($daftar_tagihan)): ?>
                        <div class="table-container-user"> <!-- Menggunakan kelas spesifik user jika diperlukan -->
                            <table class="data-table-user"> <!-- Menggunakan kelas spesifik user jika diperlukan -->
                                <thead>
                                    <tr>
                                        <th>ID Perhitungan</th>
                                        <th>Objek Pajak (Alamat)</th>
                                        <th>Tahun Periode</th>
                                        <th>Tanggal Perhitungan</th>
                                        <th>Jumlah PBB Terutang</th>
                                        <th>Status Perhitungan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daftar_tagihan as $tagihan): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($tagihan['id_perhitungan']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($tagihan['alamat_objek_pajak'], 0, 70)) . (strlen($tagihan['alamat_objek_pajak']) > 70 ? '...' : ''); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($tagihan['periode_pajak_tahun']); ?></td>
                                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($tagihan['tanggal_perhitungan']))); ?></td>
                                            <td class="text-right">Rp <?php echo number_format($tagihan['jumlah_pbb_terutang'], 2, ',', '.'); ?></td>
                                            <td class="status-<?php echo htmlspecialchars(str_replace('_', '-', $tagihan['status_perhitungan'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($tagihan['status_perhitungan']))); ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="<?php echo BASE_URL_USER_ROOT; ?>user/detail_tagihan.php?id=<?php echo $tagihan['id_perhitungan']; ?>" class="btn btn-info btn-sm" title="Lihat Detail Tagihan">
                                                    <i class="fas fa-eye"></i> <span>Lihat Detail</span>
                                                </a>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; margin-top:20px;">Belum ada data tagihan atau perhitungan pajak untuk Anda.</p>
                        <p style="text-align:center;">Pastikan data objek pajak Anda sudah dilengkapi dan telah diverifikasi oleh admin.</p>
                        <div style="text-align:center; margin-top:15px;">
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>user/data_objek_pajak.php" class="button btn-primary">Lengkapi Data Objek Pajak</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_USER_ROOT; ?>assets/js/user_script.js?v=<?php echo time(); ?>"></script>
    <script>
        // JavaScript kustom untuk halaman informasi tagihan (jika ada)
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