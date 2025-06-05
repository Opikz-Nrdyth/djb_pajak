<?php
// admin/edit_perhitungan.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("../php/config.php");

// Page Protection
if (!isset($_SESSION['id_pengguna']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL_ADMIN . "login.php");
    exit();
}

// Admin Session Data
$id_admin_logged_in = $_SESSION['id_pengguna'];
$nama_admin_session = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin');
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Page Settings
$page_title_admin = "Edit Perhitungan Pajak";
$current_page = 'tagihan_perhitungan'; // For active menu highlighting
$current_parent_page = 'pengelolaan_pajak'; // For active parent menu highlighting
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php'; // Correct path from admin/ to php/

$errors = [];
$success_message = ""; // Not used in current logic, but kept for potential future use
$perhitungan_data_edit = null;
$id_perhitungan_to_edit = null;
$wajib_pajak_info = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_perhitungan_to_edit = intval($_GET['id']);

    // Fetch tax calculation data for editing
    $stmt = $conn->prepare("
        SELECT pp.*, 
               p.nama_lengkap AS nama_wajib_pajak, p.nik AS nik_wajib_pajak,
               du.luas_bangunan, du.luas_tanah, du.alamat_objek_pajak
        FROM perhitungan_pajak pp
        JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
        JOIN pengguna p ON du.id_pengguna = p.id_pengguna
        WHERE pp.id_perhitungan = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id_perhitungan_to_edit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $perhitungan_data_edit = $result->fetch_assoc();
            $wajib_pajak_info = [
                'nama_lengkap' => $perhitungan_data_edit['nama_wajib_pajak'],
                'nik' => $perhitungan_data_edit['nik_wajib_pajak'],
                'alamat_objek_pajak' => $perhitungan_data_edit['alamat_objek_pajak'],
                'luas_bangunan' => $perhitungan_data_edit['luas_bangunan'],
                'luas_tanah' => $perhitungan_data_edit['luas_tanah']
            ];
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


// Process form submission for updating calculation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_perhitungan_pajak']) && $perhitungan_data_edit) {
    // Sanitize and retrieve form data
    $periode_pajak_tahun_update = isset($_POST['periode_pajak_tahun']) ? intval($_POST['periode_pajak_tahun']) : $perhitungan_data_edit['periode_pajak_tahun'];
    $njop_bangunan_per_meter_update = isset($_POST['njop_bangunan_per_meter']) ? floatval(str_replace('.', '', $_POST['njop_bangunan_per_meter'])) : 0.00; // Adjusted for 'id-ID' format
    $njop_tanah_per_meter_update = isset($_POST['njop_tanah_per_meter']) ? floatval(str_replace('.', '', $_POST['njop_tanah_per_meter'])) : 0.00; // Adjusted for 'id-ID' format
    $njoptkp_update = isset($_POST['njoptkp']) ? floatval(str_replace('.', '', $_POST['njoptkp'])) : 0.00; // Adjusted for 'id-ID' format
    $persentase_pbb_update = isset($_POST['persentase_pbb']) ? floatval(str_replace(',', '.', $_POST['persentase_pbb'])) / 100 : 0.0050;
    $catatan_admin_update = isset($_POST['catatan_admin']) ? $conn->real_escape_string(trim($_POST['catatan_admin'])) : null;
    $status_verifikasi_update = isset($_POST['status_verifikasi_data_user']) ? $conn->real_escape_string(trim($_POST['status_verifikasi_data_user'])) : $perhitungan_data_edit['status_verifikasi_data_user'];
    $status_perhitungan_update = isset($_POST['status_perhitungan']) ? $conn->real_escape_string(trim($_POST['status_perhitungan'])) : $perhitungan_data_edit['status_perhitungan'];

    // Validation
    if (empty($periode_pajak_tahun_update) || !is_numeric($periode_pajak_tahun_update) || $periode_pajak_tahun_update < 1900 || $periode_pajak_tahun_update > (date("Y") + 5)) {
        $errors[] = "Tahun Periode Pajak tidak valid.";
    }
    if (!in_array($status_verifikasi_update, ['lengkap', 'belum_lengkap', 'perlu_revisi', 'diverifikasi'])) {
        $errors[] = "Status Verifikasi Data tidak valid.";
    }
    if (!in_array($status_perhitungan_update, ['draft', 'final', 'dikirim_ke_user'])) {
        $errors[] = "Status Perhitungan tidak valid.";
    }

    $luas_bangunan_current = floatval($perhitungan_data_edit['luas_bangunan']);
    $luas_tanah_current = floatval($perhitungan_data_edit['luas_tanah']);

    if (empty($errors)) {
        // Recalculate PBB
        $total_njop_bangunan_new = $luas_bangunan_current * $njop_bangunan_per_meter_update;
        $total_njop_tanah_new = $luas_tanah_current * $njop_tanah_per_meter_update;
        $njop_total_objek_pajak_new = $total_njop_bangunan_new + $total_njop_tanah_new;
        $njkp_new = $njop_total_objek_pajak_new - $njoptkp_update;
        if ($njkp_new < 0) $njkp_new = 0; // NJKP cannot be negative
        $jumlah_pbb_terutang_new = $njkp_new * $persentase_pbb_update;

        $sql_update = "UPDATE perhitungan_pajak SET
                            periode_pajak_tahun = ?, 
                            njop_bangunan_per_meter = ?, njop_tanah_per_meter = ?,
                            total_njop_bangunan = ?, total_njop_tanah = ?, njop_total_objek_pajak = ?,
                            njoptkp = ?, njkp = ?, persentase_pbb = ?, jumlah_pbb_terutang = ?,
                            catatan_admin = ?, status_verifikasi_data_user = ?, status_perhitungan = ?,
                            id_admin_pereview = ?, tanggal_perhitungan = NOW() 
                           WHERE id_perhitungan = ?";

        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param(
                "idddddddddsssii", // String tipe yang sudah diperbaiki
                $periode_pajak_tahun_update,
                $njop_bangunan_per_meter_update,
                $njop_tanah_per_meter_update,
                $total_njop_bangunan_new,
                $total_njop_tanah_new,
                $njop_total_objek_pajak_new,
                $njoptkp_update,
                $njkp_new,
                $persentase_pbb_update,
                $jumlah_pbb_terutang_new,
                $catatan_admin_update,
                $status_verifikasi_update,
                $status_perhitungan_update,
                $id_admin_logged_in,
                $id_perhitungan_to_edit
            );

            if ($stmt_update->execute()) {
                $_SESSION['flash_message'] = "Perhitungan pajak (ID: " . $id_perhitungan_to_edit . ") berhasil diperbarui.";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . BASE_URL_ADMIN . "admin/tagihan_perhitungan.php?tahun=" . $periode_pajak_tahun_update);
                exit();
            } else {
                $errors[] = "Gagal memperbarui perhitungan pajak: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors[] = "Gagal mempersiapkan pembaruan: " . $conn->error;
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
    <link rel="icon" type="image/png" href="<?php echo BASE_URL_ADMIN; ?>assets/images/icon.png">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-edit-perhitungan.css?v=<?php echo time(); ?>">
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
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/index.php"> <i class="fas fa-tachometer-alt fa-fw"></i>
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
                        <span class="notification-badge">3</span> </a>
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
                        Edit Perhitungan Pajak #<?php echo $perhitungan_data_edit ? htmlspecialchars($perhitungan_data_edit['id_perhitungan']) : 'N/A'; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin: 15px 0;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($perhitungan_data_edit && $wajib_pajak_info): ?>
                        <form action="<?php echo BASE_URL_ADMIN; ?>admin/edit_perhitungan.php?id=<?php echo $id_perhitungan_to_edit; ?>" method="POST" class="admin-form calculation-form">
                            <fieldset>
                                <legend>Informasi Wajib Pajak & Objek Pajak (Read-only)</legend>
                                <div class="form-group">
                                    <label>Nama Wajib Pajak</label>
                                    <input type="text" value="<?php echo htmlspecialchars($wajib_pajak_info['nama_lengkap']); ?>" readonly disabled>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>NIK</label>
                                        <input type="text" value="<?php echo htmlspecialchars($wajib_pajak_info['nik']); ?>" readonly disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>Alamat Objek Pajak</label>
                                        <input type="text" value="<?php echo htmlspecialchars($wajib_pajak_info['alamat_objek_pajak'] ?? 'Belum ada'); ?>" readonly disabled>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Luas Bangunan (m²)</label>
                                        <input type="text" value="<?php echo number_format(floatval($wajib_pajak_info['luas_bangunan']), 2, ',', '.'); ?>" readonly disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>Luas Tanah (m²)</label>
                                        <input type="text" value="<?php echo number_format(floatval($wajib_pajak_info['luas_tanah']), 2, ',', '.'); ?>" readonly disabled>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend>Edit Parameter Perhitungan PBB</legend>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="periode_pajak_tahun">Tahun Periode Pajak</label>
                                        <input type="number" id="periode_pajak_tahun" name="periode_pajak_tahun" value="<?php echo htmlspecialchars($perhitungan_data_edit['periode_pajak_tahun']); ?>" required min="1900" max="<?php echo date("Y") + 5; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="status_verifikasi_data_user">Status Verifikasi Data User</label>
                                        <select id="status_verifikasi_data_user" name="status_verifikasi_data_user" required>
                                            <option value="belum_lengkap" <?php echo ($perhitungan_data_edit['status_verifikasi_data_user'] == 'belum_lengkap') ? 'selected' : ''; ?>>Belum Lengkap</option>
                                            <option value="perlu_revisi" <?php echo ($perhitungan_data_edit['status_verifikasi_data_user'] == 'perlu_revisi') ? 'selected' : ''; ?>>Perlu Revisi</option>
                                            <option value="lengkap" <?php echo ($perhitungan_data_edit['status_verifikasi_data_user'] == 'lengkap') ? 'selected' : ''; ?>>Lengkap</option>
                                            <option value="diverifikasi" <?php echo ($perhitungan_data_edit['status_verifikasi_data_user'] == 'diverifikasi') ? 'selected' : ''; ?>>Diverifikasi</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="njop_bangunan_per_meter">NJOP Bangunan / m² (Rp)</label>
                                        <input type="text" class="input-currency" id="njop_bangunan_per_meter" name="njop_bangunan_per_meter" value="<?php echo number_format($perhitungan_data_edit['njop_bangunan_per_meter'], 0, ',', '.'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="njop_tanah_per_meter">NJOP Tanah / m² (Rp)</label>
                                        <input type="text" class="input-currency" id="njop_tanah_per_meter" name="njop_tanah_per_meter" value="<?php echo number_format($perhitungan_data_edit['njop_tanah_per_meter'], 0, ',', '.'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="njoptkp">NJOPTKP (Rp)</label>
                                        <input type="text" class="input-currency" id="njoptkp" name="njoptkp" value="<?php echo number_format($perhitungan_data_edit['njoptkp'], 0, ',', '.'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="persentase_pbb">Persentase PBB (%)</label>
                                        <input type="text" id="persentase_pbb" name="persentase_pbb" value="<?php echo rtrim(rtrim(number_format($perhitungan_data_edit['persentase_pbb'] * 100, 2, ',', '.'), '0'), ','); ?>" required placeholder="Contoh: 0,5">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="status_perhitungan">Status Perhitungan</label>
                                        <select id="status_perhitungan" name="status_perhitungan" required>
                                            <option value="draft" <?php echo ($perhitungan_data_edit['status_perhitungan'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                            <option value="final" <?php echo ($perhitungan_data_edit['status_perhitungan'] == 'final') ? 'selected' : ''; ?>>Final</option>
                                            <option value="dikirim_ke_user" <?php echo ($perhitungan_data_edit['status_perhitungan'] == 'dikirim_ke_user') ? 'selected' : ''; ?>>Dikirim ke User</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="catatan_admin">Catatan Admin</label>
                                    <textarea id="catatan_admin" name="catatan_admin" rows="3"><?php echo htmlspecialchars($perhitungan_data_edit['catatan_admin'] ?? ''); ?></textarea>
                                </div>
                            </fieldset>

                            <div class="calculation-summary" id="calculation-summary-section-edit" style="display:none; margin-top:15px;">
                                <h4>Ringkasan Perhitungan Baru (Estimasi)</h4>
                                <p>Total NJOP Bangunan: Rp <span id="summary_total_njop_bangunan_edit">0</span></p>
                                <p>Total NJOP Tanah: Rp <span id="summary_total_njop_tanah_edit">0</span></p>
                                <p>NJOP Total Objek Pajak: Rp <span id="summary_njop_total_objek_pajak_edit">0</span></p>
                                <p>NJOP Kena Pajak (NJKP): Rp <span id="summary_njkp_edit">0</span></p>
                                <p><strong>Jumlah PBB Terutang: Rp <span id="summary_jumlah_pbb_terutang_edit">0</span></strong></p>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="button btn-info" id="btn-preview-calculation-edit">Preview Perubahan</button>
                                <button type="submit" name="update_perhitungan_pajak" class="button btn-primary">Simpan Perubahan</button>
                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php" class="button btn-secondary">Batal</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p>Tidak dapat memuat form karena data perhitungan tidak ditemukan atau ID tidak valid.</p>
                        <a href="<?php echo BASE_URL_ADMIN; ?>admin/tagihan_perhitungan.php" class="button btn-secondary">Kembali ke Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnPreviewEdit = document.getElementById('btn-preview-calculation-edit');
            const summarySectionEdit = document.getElementById('calculation-summary-section-edit');

            const luasBangunanCurrent = <?php echo $wajib_pajak_info && isset($wajib_pajak_info['luas_bangunan']) ? floatval($wajib_pajak_info['luas_bangunan']) : 0; ?>;
            const luasTanahCurrent = <?php echo $wajib_pajak_info && isset($wajib_pajak_info['luas_tanah']) ? floatval($wajib_pajak_info['luas_tanah']) : 0; ?>;

            document.querySelectorAll('.input-currency').forEach(function(input) {
                function formatCurrencyID(value) {
                    if (!value && value !== 0) return '';
                    let numStr = String(value).replace(/[^0-9]/g, '');
                    if (numStr === '') return '';
                    return parseInt(numStr, 10).toLocaleString('id-ID');
                }

                input.addEventListener('input', function(e) {
                    let originalCursorPos = e.target.selectionStart;
                    let oldValue = e.target.value;
                    let rawValue = String(e.target.value).replace(/[^0-9]/g, '');

                    e.target.value = formatCurrencyID(rawValue);
                    let newValue = e.target.value;

                    // Cursor positioning logic (simplified, might need further refinement for complex cases)
                    let diff = newValue.length - oldValue.length;
                    let newCursorPos = originalCursorPos + diff;

                    // Adjust if cursor was at the end of a number group
                    if (rawValue.length > 0 && oldValue.charAt(originalCursorPos - 1) === '.' && newValue.charAt(newCursorPos - 1) !== '.') {
                        // find next number after cursor in old value
                        let oldSub = oldValue.substring(originalCursorPos);
                        let oldNextNumberMatch = oldSub.match(/\d/);
                        if (oldNextNumberMatch) {
                            let oldNextNumberIndex = originalCursorPos + oldNextNumberMatch.index;
                            // find this number in new value
                            let numToFind = oldValue.charAt(oldNextNumberIndex);
                            let newIndexOfNum = newValue.indexOf(numToFind, newCursorPos > 0 ? newCursorPos - 1 : 0);
                            if (newIndexOfNum !== -1) newCursorPos = newIndexOfNum;
                        }
                    }
                    e.target.selectionStart = e.target.selectionEnd = Math.max(0, newCursorPos);
                });
                // Initial formatting
                input.value = formatCurrencyID(input.value);
            });

            if (btnPreviewEdit && summarySectionEdit) {
                btnPreviewEdit.addEventListener('click', function() {
                    const njopBangunanPerM = parseFloat(String(document.getElementById('njop_bangunan_per_meter').value).replace(/\./g, '').replace(',', '.')) || 0;
                    const njopTanahPerM = parseFloat(String(document.getElementById('njop_tanah_per_meter').value).replace(/\./g, '').replace(',', '.')) || 0;
                    const njoptkp = parseFloat(String(document.getElementById('njoptkp').value).replace(/\./g, '').replace(',', '.')) || 0;
                    const persentasePbbInput = String(document.getElementById('persentase_pbb').value).replace(',', '.');
                    const persentasePbb = parseFloat(persentasePbbInput) / 100 || 0.005; // Default 0.5%

                    const totalNjopBangunan = luasBangunanCurrent * njopBangunanPerM;
                    const totalNjopTanah = luasTanahCurrent * njopTanahPerM;
                    const njopTotalObjekPajak = totalNjopBangunan + totalNjopTanah;
                    let njkp = njopTotalObjekPajak - njoptkp;
                    if (njkp < 0) njkp = 0;
                    const jumlahPbbTerutang = njkp * persentasePbb;

                    const formatOptions = {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    };

                    document.getElementById('summary_total_njop_bangunan_edit').textContent = totalNjopBangunan.toLocaleString('id-ID', formatOptions);
                    document.getElementById('summary_total_njop_tanah_edit').textContent = totalNjopTanah.toLocaleString('id-ID', formatOptions);
                    document.getElementById('summary_njop_total_objek_pajak_edit').textContent = njopTotalObjekPajak.toLocaleString('id-ID', formatOptions);
                    document.getElementById('summary_njkp_edit').textContent = njkp.toLocaleString('id-ID', formatOptions);
                    document.getElementById('summary_jumlah_pbb_terutang_edit').textContent = jumlahPbbTerutang.toLocaleString('id-ID', formatOptions);

                    summarySectionEdit.style.display = 'block';
                });
            }
        });
    </script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>