<?php
// admin/form_data_objek_pajak.php (Layout Menyatu - Tambah/Edit Objek Pajak oleh Admin)
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
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png';

require_once '../php/db_connect.php';

$errors = [];
$success_message = "";
$objek_pajak_data = null;
$edit_mode_objek = false;
$id_objek_to_edit = null;
$id_user_for_new_objek = null; // Untuk mode tambah baru, kita perlu tahu objek ini milik siapa
$nama_wp_display = ''; // Untuk menampilkan nama WP di judul

// Cek apakah mode edit atau tambah baru
if (isset($_GET['edit_objek_id']) && is_numeric($_GET['edit_objek_id'])) {
    $edit_mode_objek = true;
    $id_objek_to_edit = intval($_GET['edit_objek_id']);

    // Ambil data objek pajak yang akan diedit
    $stmt_get_objek = $conn->prepare("SELECT du.*, p.nama_lengkap FROM data_djp_user du JOIN pengguna p ON du.id_pengguna = p.id_pengguna WHERE du.id_data_djp = ?");
    if ($stmt_get_objek) {
        $stmt_get_objek->bind_param("i", $id_objek_to_edit);
        $stmt_get_objek->execute();
        $result_objek = $stmt_get_objek->get_result();
        if ($result_objek->num_rows === 1) {
            $objek_pajak_data = $result_objek->fetch_assoc();
            $id_user_for_new_objek = $objek_pajak_data['id_pengguna']; // Set user ID untuk konsistensi
            $nama_wp_display = htmlspecialchars($objek_pajak_data['nama_lengkap']);
        } else {
            $_SESSION['flash_message'] = "Data objek pajak tidak ditemukan."; // Menggunakan flash message umum
            $_SESSION['flash_message_type'] = "error";
            header("Location: " . BASE_URL_ADMIN . "admin/kelola_wajib_pajak.php");
            exit();
        }
        $stmt_get_objek->close();
    } else {
        $errors[] = "Gagal mengambil data objek pajak: " . $conn->error;
    }
} elseif (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    // Mode Tambah Baru untuk pengguna tertentu
    $id_user_for_new_objek = intval($_GET['user_id']);
    // Ambil nama pengguna untuk ditampilkan
    $stmt_get_user_nama = $conn->prepare("SELECT nama_lengkap FROM pengguna WHERE id_pengguna = ? AND role = 'user'");
    if ($stmt_get_user_nama) {
        $stmt_get_user_nama->bind_param("i", $id_user_for_new_objek);
        $stmt_get_user_nama->execute();
        $result_user_nama = $stmt_get_user_nama->get_result();
        if ($user_nama_row = $result_user_nama->fetch_assoc()) {
            $nama_wp_display = htmlspecialchars($user_nama_row['nama_lengkap']);
        } else {
            $errors[] = "Pengguna target untuk objek pajak baru tidak ditemukan.";
        }
        $stmt_get_user_nama->close();
    } else {
        $errors[] = "Gagal mengambil nama pengguna: " . $conn->error;
    }
} else {
    // Jika tidak ada user_id (untuk tambah) atau edit_objek_id (untuk edit), ini adalah error
    $_SESSION['flash_message'] = "Permintaan tidak valid. ID Pengguna atau ID Objek Pajak tidak disediakan.";
    $_SESSION['flash_message_type'] = "error";
    header("Location: " . BASE_URL_ADMIN . "admin/kelola_wajib_pajak.php");
    exit();
}

// Pengaturan judul halaman dinamis
$page_title_admin = $edit_mode_objek ? "Edit Objek Pajak" : "Tambah Objek Pajak Baru";
$page_title_admin .= $nama_wp_display ? " untuk " . $nama_wp_display : "";
$current_page = 'management_wajib_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;


// Proses form submission untuk simpan (tambah baru) atau update (edit)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_objek_pajak'])) {
    $id_pengguna_objek = intval($_POST['id_pengguna_objek']); // ID pengguna pemilik objek
    $npwp = isset($_POST['npwp']) ? $conn->real_escape_string(trim($_POST['npwp'])) : null;
    if (empty($npwp)) $npwp = null;
    $nama_pemilik_bangunan = isset($_POST['nama_pemilik_bangunan']) ? $conn->real_escape_string(trim($_POST['nama_pemilik_bangunan'])) : '';
    $alamat_objek_pajak = isset($_POST['alamat_objek_pajak']) ? $conn->real_escape_string(trim($_POST['alamat_objek_pajak'])) : '';
    $jenis_bangunan = isset($_POST['jenis_bangunan']) ? $conn->real_escape_string(trim($_POST['jenis_bangunan'])) : '';
    $data_tambahan = isset($_POST['data_tambahan']) ? $conn->real_escape_string(trim($_POST['data_tambahan'])) : null;
    $id_data_djp_hidden = isset($_POST['id_data_djp']) ? intval($_POST['id_data_djp']) : null; // Untuk mode edit

    $luas_bangunan_input = isset($_POST['luas_bangunan']) ? trim($_POST['luas_bangunan']) : '';
    $luas_bangunan = null;
    if ($luas_bangunan_input !== '') {
        $luas_bangunan_sanitized = str_replace(',', '.', $luas_bangunan_input);
        if (is_numeric($luas_bangunan_sanitized) && floatval($luas_bangunan_sanitized) >= 0) { // Boleh 0 jika memang tidak ada bangunan
            $luas_bangunan = floatval($luas_bangunan_sanitized);
        } else {
            $errors[] = "Format Luas Bangunan tidak valid. Gunakan angka (misal 100 atau 100.50).";
        }
    } else {
        $errors[] = "Luas Bangunan wajib diisi (bisa 0 jika tidak ada bangunan).";
    }

    $luas_tanah_input = isset($_POST['luas_tanah']) ? trim($_POST['luas_tanah']) : '';
    $luas_tanah = null;
    if ($luas_tanah_input !== '') {
        $luas_tanah_sanitized = str_replace(',', '.', $luas_tanah_input);
        if (is_numeric($luas_tanah_sanitized) && floatval($luas_tanah_sanitized) >= 0) { // Boleh 0 jika misal apartemen
            $luas_tanah = floatval($luas_tanah_sanitized);
        } else {
            $errors[] = "Format Luas Tanah tidak valid. Gunakan angka (misal 100 atau 100.50).";
        }
    } else {
        $errors[] = "Luas Tanah wajib diisi (bisa 0 jika misal apartemen).";
    }

    if ($luas_bangunan === null && $luas_tanah === null) {
        // Jika keduanya null karena input tidak valid, error sudah ditambahkan
    } elseif ($luas_bangunan == 0 && $luas_tanah == 0 && $luas_bangunan_input !== '0' && $luas_tanah_input !== '0') {
        // Ini untuk kasus jika inputnya bukan '0' tapi setelah diproses jadi 0 (error format)
        // Namun, jika input memang '0' dan '0', itu valid.
    }


    if (empty($nama_pemilik_bangunan)) $errors[] = "Nama Pemilik Bangunan wajib diisi.";
    if (empty($alamat_objek_pajak)) $errors[] = "Alamat Objek Pajak wajib diisi.";
    if (empty($jenis_bangunan)) $errors[] = "Jenis Bangunan wajib diisi.";
    if (empty($id_pengguna_objek)) $errors[] = "ID Pengguna untuk objek pajak ini tidak terdefinisi.";


    if (empty($errors)) {
        if ($id_data_djp_hidden && $edit_mode_objek) { // Mode Edit
            $sql_process_djp = "UPDATE data_djp_user SET npwp = ?, nama_pemilik_bangunan = ?, alamat_objek_pajak = ?, luas_bangunan = ?, luas_tanah = ?, jenis_bangunan = ?, data_tambahan = ?, tanggal_update = NOW() WHERE id_data_djp = ? AND id_pengguna = ?";
            $stmt_process_djp = $conn->prepare($sql_process_djp);
            if ($stmt_process_djp) {
                $stmt_process_djp->bind_param("sssddssii", $npwp, $nama_pemilik_bangunan, $alamat_objek_pajak, $luas_bangunan, $luas_tanah, $jenis_bangunan, $data_tambahan, $id_data_djp_hidden, $id_pengguna_objek);
                if ($stmt_process_djp->execute()) {
                    $_SESSION['flash_message'] = "Data objek pajak (ID: " . $id_data_djp_hidden . ") berhasil diperbarui.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . BASE_URL_ADMIN . "admin/edit_wajib_pajak.php?id=" . $id_pengguna_objek);
                    exit();
                } else {
                    $errors[] = "Gagal memperbarui data objek pajak: " . $stmt_process_djp->error;
                }
                $stmt_process_djp->close();
            } else {
                $errors[] = "Gagal mempersiapkan pembaruan data: " . $conn->error;
            }
        } else { // Mode Tambah Baru
            $sql_process_djp = "INSERT INTO data_djp_user (id_pengguna, npwp, nama_pemilik_bangunan, alamat_objek_pajak, luas_bangunan, luas_tanah, jenis_bangunan, data_tambahan, tanggal_input, tanggal_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt_process_djp = $conn->prepare($sql_process_djp);
            if ($stmt_process_djp) {
                $stmt_process_djp->bind_param("isssddss", $id_pengguna_objek, $npwp, $nama_pemilik_bangunan, $alamat_objek_pajak, $luas_bangunan, $luas_tanah, $jenis_bangunan, $data_tambahan);
                if ($stmt_process_djp->execute()) {
                    $_SESSION['flash_message'] = "Data objek pajak baru berhasil disimpan.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . BASE_URL_ADMIN . "admin/edit_wajib_pajak.php?id=" . $id_pengguna_objek);
                    exit();
                } else {
                    $errors[] = "Gagal menyimpan data objek pajak: " . $stmt_process_djp->error;
                }
                $stmt_process_djp->close();
            } else {
                $errors[] = "Gagal mempersiapkan penyimpanan data: " . $conn->error;
            }
        }
    }
    // Jika ada error, $objek_pajak_data diisi kembali dari POST untuk repopulate form
    if (!empty($errors)) {
        $objek_pajak_data = $_POST;
        // Pastikan luas_bangunan dan luas_tanah tetap string
        $objek_pajak_data['luas_bangunan'] = $_POST['luas_bangunan'];
        $objek_pajak_data['luas_tanah'] = $_POST['luas_tanah'];
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
    <!-- CSS Kustom Spesifik Konten Halaman Ini -->
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-form-data-objek-pajak.css?v=<?php echo time(); ?>">
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
                <div class="admin-card">
                    <div class="admin-card-header">
                        <?php echo $edit_mode_objek ? 'Edit Data Objek Pajak' : 'Tambah Data Objek Pajak Baru'; ?>
                        <?php if ($nama_wp_display): ?>
                            <span class="header-subtext">untuk Wajib Pajak: <strong><?php echo $nama_wp_display; ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Pesan sukses akan ditampilkan di halaman edit_wajib_pajak.php setelah redirect -->

                    <form action="form_data_objek_pajak.php<?php echo $edit_mode_objek ? '?edit_objek_id=' . $id_objek_to_edit : '?user_id=' . $id_user_for_new_objek; ?>" method="POST" class="admin-form">
                        <!-- Hidden input untuk ID pengguna (pemilik objek pajak) -->
                        <input type="hidden" name="id_pengguna_objek" value="<?php echo $id_user_for_new_objek; ?>">

                        <?php if ($edit_mode_objek && $id_objek_to_edit): ?>
                            <!-- Hidden input untuk ID data objek pajak yang diedit -->
                            <input type="hidden" name="id_data_djp" value="<?php echo $id_objek_to_edit; ?>">
                        <?php endif; ?>

                        <fieldset>
                            <legend>Detail Objek Pajak</legend>
                            <div class="form-group">
                                <label for="npwp">NPWP (Nomor Pokok Wajib Pajak)</label>
                                <input type="text" id="npwp" name="npwp" value="<?php echo htmlspecialchars($objek_pajak_data['npwp'] ?? ''); ?>" placeholder="Jika ada, contoh: 00.000.000.0-000.000">
                                <small>Opsional.</small>
                            </div>
                            <div class="form-group">
                                <label for="nama_pemilik_bangunan">Nama Pemilik Bangunan (sesuai PBB)</label>
                                <input type="text" id="nama_pemilik_bangunan" name="nama_pemilik_bangunan" value="<?php echo htmlspecialchars($objek_pajak_data['nama_pemilik_bangunan'] ?? ($nama_wp_display ?? '')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="alamat_objek_pajak">Alamat Lengkap Objek Pajak</label>
                                <textarea id="alamat_objek_pajak" name="alamat_objek_pajak" rows="3" required><?php echo htmlspecialchars($objek_pajak_data['alamat_objek_pajak'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="luas_bangunan">Luas Bangunan (m²)</label>
                                    <input type="text" id="luas_bangunan" name="luas_bangunan" value="<?php echo isset($objek_pajak_data['luas_bangunan']) ? number_format(floatval($objek_pajak_data['luas_bangunan']), 2, ',', '.') : (isset($_POST['luas_bangunan']) ? htmlspecialchars($_POST['luas_bangunan']) : ''); ?>" required placeholder="Contoh: 100,50">
                                </div>
                                <div class="form-group">
                                    <label for="luas_tanah">Luas Tanah (m²)</label>
                                    <input type="text" id="luas_tanah" name="luas_tanah" value="<?php echo isset($objek_pajak_data['luas_tanah']) ? number_format(floatval($objek_pajak_data['luas_tanah']), 2, ',', '.') : (isset($_POST['luas_tanah']) ? htmlspecialchars($_POST['luas_tanah']) : ''); ?>" required placeholder="Contoh: 200,75">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="jenis_bangunan">Jenis Bangunan</label>
                                <input type="text" id="jenis_bangunan" name="jenis_bangunan" value="<?php echo htmlspecialchars($objek_pajak_data['jenis_bangunan'] ?? ''); ?>" required placeholder="Contoh: Rumah Tinggal, Ruko, Gudang">
                            </div>
                            <div class="form-group">
                                <label for="data_tambahan">Data Tambahan (jika ada)</label>
                                <textarea id="data_tambahan" name="data_tambahan" rows="3"><?php echo htmlspecialchars($objek_pajak_data['data_tambahan'] ?? ''); ?></textarea>
                                <small>Contoh: Nomor Blok, Kavling, NOP (Nomor Objek Pajak) jika diketahui.</small>
                            </div>
                        </fieldset>

                        <div class="form-actions">
                            <a href="<?php echo BASE_URL_ADMIN; ?>admin/edit_wajib_pajak.php?id=<?php echo $id_user_for_new_objek; ?>" class="button btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Edit WP</a>
                            <button type="submit" name="simpan_objek_pajak" class="button btn-primary">
                                <i class="fas fa-save"></i> <?php echo $edit_mode_objek ? 'Simpan Perubahan Objek' : 'Tambah Objek Pajak'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <script>
        // JavaScript spesifik untuk form ini, jika ada
        document.addEventListener('DOMContentLoaded', function() {
            // Contoh: format input luas saat blur
            document.querySelectorAll('input[name="luas_bangunan"], input[name="luas_tanah"]').forEach(function(input) {
                input.addEventListener('blur', function(e) {
                    let value = this.value.replace(/[^0-9,.]/g, '').replace(',', '.'); // Bersihkan dan ganti koma
                    if (value && !isNaN(parseFloat(value))) {
                        // Format kembali ke format Indonesia untuk tampilan
                        this.value = parseFloat(value).toLocaleString('id-ID', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    } else if (this.value !== '') {
                        // Kosongkan jika tidak valid, atau biarkan PHP yang validasi
                        // this.value = ''; 
                    }
                });
                // Format nilai awal jika ada
                if (input.value) {
                    let initialClean = String(input.value).replace(/[^0-9,.]/g, '').replace(',', '.');
                    if (initialClean && !isNaN(parseFloat(initialClean))) {
                        input.value = parseFloat(initialClean).toLocaleString('id-ID', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
            });
        });
    </script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>