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
$page_title_user = "Data Objek Pajak Saya";
$current_page_user = 'daftar_objek_pajak';
$page_title_for_header = $page_title_user;

require_once '../php/db_connect.php';

$errors = [];
$success_message = ""; // Untuk menampilkan pesan dari aksi (misal setelah hapus)
$daftar_objek_pajak = [];

// Ambil flash message jika ada (misal setelah tambah/edit/hapus berhasil)
if (isset($_SESSION['flash_message_djp'])) {
    $success_message = $_SESSION['flash_message_djp'];
    unset($_SESSION['flash_message_djp']);
}


// Ambil semua data objek pajak pengguna saat ini
$stmt_get_all_djp = $conn->prepare("SELECT * FROM data_djp_user WHERE id_pengguna = ? ORDER BY id_data_djp DESC");
if ($stmt_get_all_djp) {
    $stmt_get_all_djp->bind_param("i", $id_user_logged_in);
    $stmt_get_all_djp->execute();
    $result_all_djp = $stmt_get_all_djp->get_result();
    if ($result_all_djp->num_rows > 0) {
        while ($row = $result_all_djp->fetch_assoc()) {
            $daftar_objek_pajak[] = $row;
        }
    }
    $stmt_get_all_djp->close();
} else {
    $errors[] = "Gagal mengambil daftar data objek pajak: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user-daftar-objek-pajak.css?v=<?php echo time(); ?>">
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
                <div class="admin-card user-form-card">
                    <div class="page-header-user">
                        <h1>Daftar Objek Pajak Anda</h1>
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/data_objek_pajak.php" class="btn-add-new-user">
                            <i class="fas fa-plus"></i> Tambah Objek Pajak
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="flash-message error">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="flash-message success">
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($daftar_objek_pajak)): ?>
                        <div class="table-container-user">
                            <table class="data-table-user">
                                <thead>
                                    <tr>
                                        <th>ID Data</th>
                                        <th>NPWP</th>
                                        <th>Nama Pemilik (PBB)</th>
                                        <th>Alamat Objek Pajak</th>
                                        <th>Luas Bangunan (m²)</th>
                                        <th>Luas Tanah (m²)</th>
                                        <th>Jenis Bangunan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daftar_objek_pajak as $objek): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($objek['id_data_djp']); ?></td>
                                            <td><?php echo htmlspecialchars($objek['npwp'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($objek['nama_pemilik_bangunan']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($objek['alamat_objek_pajak'], 0, 50)) . (strlen($objek['alamat_objek_pajak']) > 50 ? '...' : ''); ?></td>
                                            <td class="text-right"><?php echo number_format(floatval($objek['luas_bangunan']), 2, ',', '.'); ?></td>
                                            <td class="text-right"><?php echo number_format(floatval($objek['luas_tanah']), 2, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($objek['jenis_bangunan']); ?></td>
                                            <td class="action-buttons">
                                                <a href="<?php echo BASE_URL_USER_ROOT; ?>user/data_objek_pajak.php?id=<?php echo $objek['id_data_djp']; ?>" class="btn btn-warning btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i> <span>Edit</span>
                                                </a>
                                                <a href="<?php echo BASE_URL_USER_ROOT; ?>user/hapus_objek_pajak.php?id=<?php echo $objek['id_data_djp']; ?>" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus data objek pajak ini? Ini juga akan menghapus perhitungan terkait.');">
                                                    <i class="fas fa-trash"></i> <span>Hapus</span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; margin-top:20px;">Anda belum memiliki data objek pajak. Silakan tambahkan data objek pajak baru.</p>
                    <?php endif; ?>
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
</body>

</html>