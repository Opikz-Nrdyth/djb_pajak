<?php
// admin/kelola_wajib_pajak.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definisi BASE_URL_ADMIN
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
$id_admin = $_SESSION['id_pengguna'];
$nama_admin_session = isset($_SESSION['nama_lengkap']) ? htmlspecialchars($_SESSION['nama_lengkap']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin');
$foto_profil_admin_session = BASE_URL_ADMIN . 'assets/images/default_avatar.png'; // Menggunakan avatar default

// Pengaturan untuk halaman ini
$page_title_admin = "Manajemen Wajib Pajak";
$current_page = 'management_wajib_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php'; // Koneksi database

// Logika untuk mengambil data wajib pajak
$wajib_pajak_list = [];
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'aktif'; // Default filter

$sql_wp = "SELECT p.id_pengguna, p.nik, p.nama_lengkap, p.no_telepon, p.status_akun, 
                  GROUP_CONCAT(DISTINCT djp.alamat_objek_pajak SEPARATOR '; ') as alamat_objek, 
                  COUNT(DISTINCT djp.id_data_djp) as jumlah_objek_pajak
           FROM pengguna p
           LEFT JOIN data_djp_user djp ON p.id_pengguna = djp.id_pengguna
           WHERE p.role = 'user'";

if ($filter_status == 'aktif') {
    $sql_wp .= " AND p.status_akun = 'aktif'";
} elseif ($filter_status == 'nonaktif') {
    $sql_wp .= " AND p.status_akun = 'nonaktif'";
} elseif ($filter_status == 'pending') {
    $sql_wp .= " AND p.status_akun = 'pending'";
}

$sql_wp .= " GROUP BY p.id_pengguna ORDER BY p.nama_lengkap ASC";

$result_wp = $conn->query($sql_wp);
if ($result_wp && $result_wp->num_rows > 0) {
    while ($row = $result_wp->fetch_assoc()) {
        // Placeholder untuk Noit/No. Registrasi Internal
        $row['noit_display'] = "NOP-" . substr($row['nik'], 0, 7);
        $wajib_pajak_list[] = $row;
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
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-wajibpajak.css?v=<?php echo time(); ?>">
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
                    <li class="<?php echo ($current_page == 'dashboard_admin') ? 'active' : ''; ?>">
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
                            <li class="<?php echo ($current_page == 'management_wajib_pajak') ? 'active' : ''; ?>">
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
                <div class="admin-card">
                    <div class="page-header">
                        <h1>Manajemen Wajib Pajak</h1>
                    </div>

                    <div class="actions-bar">
                        <div class="search-filter-wp">
                            <input type="text" id="searchWajibPajak" placeholder="Cari Wajib Pajak berdasarkan NIK atau Nama..." onkeyup="filterTableWajibPajak()">
                        </div>
                    </div>

                    <div class="filter-tabs">
                        <button class="tab-item <?php echo ($filter_status == 'aktif') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php?status=aktif'">Aktif</button>
                        <button class="tab-item <?php echo ($filter_status == 'pending') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php?status=pending'">Pending</button>
                        <button class="tab-item <?php echo ($filter_status == 'tidak_aktif') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php?status=tidak_aktif'">Tidak Aktif</button>
                        <button class="tab-item <?php echo ($filter_status == 'semua') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo BASE_URL_ADMIN; ?>admin/kelola_wajib_pajak.php?status=semua'">Semua</button>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="tabelWajibPajak">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAllWajibPajak" /></th>
                                    <th>NIK</th>
                                    <th>Nama Wajib Pajak</th>
                                    <th>No. Telepon</th>
                                    <th>Alamat Utama Objek Pajak</th>
                                    <th>No. Registrasi Internal</th>
                                    <th>Jml Objek Pajak</th>
                                    <th>Status Akun</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($wajib_pajak_list)): ?>
                                    <?php foreach ($wajib_pajak_list as $wp): ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_wp[]" value="<?php echo $wp['id_pengguna']; ?>" /></td>
                                            <td><?php echo htmlspecialchars($wp['nik']); ?></td>
                                            <td><?php echo htmlspecialchars($wp['nama_lengkap']); ?></td>
                                            <td><?php echo htmlspecialchars($wp['no_telepon']); ?></td>
                                            <td><?php echo htmlspecialchars(!empty($wp['alamat_objek']) ? $wp['alamat_objek'] : 'Belum ada data'); ?></td>
                                            <td><?php echo htmlspecialchars($wp['noit_display']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($wp['jumlah_objek_pajak']); ?></td>
                                            <td class="status-<?php echo htmlspecialchars($wp['status_akun']); ?>"><?php echo ucfirst(htmlspecialchars($wp['status_akun'])); ?></td>
                                            <td class="action-buttons">
                                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/detail_wajib_pajak.php?id=<?php echo $wp['id_pengguna']; ?>" class="btn btn-view" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i> <span>Lihat Detail</span>
                                                </a>
                                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/edit_wajib_pajak.php?id=<?php echo $wp['id_pengguna']; ?>" class="btn btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i> <span>Edit</span>
                                                </a>
                                                <a href="<?php echo BASE_URL_ADMIN; ?>admin/hapus_wajib_pajak.php?id=<?php echo $wp['id_pengguna']; ?>" class="btn btn-delete" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus wajib pajak ini beserta semua data terkaitnya?');">
                                                    <i class="fas fa-trash"></i> <span>Hapus</span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Tidak ada data wajib pajak yang ditemukan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer-actions">
                        <select name="bulk_action" id="bulkActionWajibPajak">
                            <option value="">Aksi Massal</option>
                            <option value="aktifkan">Aktifkan Akun Terpilih</option>
                            <option value="nonaktifkan">Nonaktifkan Akun Terpilih</option>
                            <option value="hapus">Hapus Akun Terpilih</option>
                        </select>
                        <button class="btn btn-apply-bulk" onclick="applyBulkActionWajibPajak()">Terapkan</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllWajibPajak');
            const itemCheckboxes = document.querySelectorAll('input[name="selected_wp[]"]');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }
        });

        function filterTableWajibPajak() {
            let input, filter, table, tr, tdNama, tdNik, i, txtValueNama, txtValueNik;
            input = document.getElementById("searchWajibPajak");
            filter = input.value.toUpperCase();
            table = document.getElementById("tabelWajibPajak");
            tr = table.getElementsByTagName("tr");

            for (i = 1; i < tr.length; i++) {
                tdNik = tr[i].getElementsByTagName("td")[1];
                tdNama = tr[i].getElementsByTagName("td")[2];
                if (tdNik || tdNama) {
                    txtValueNik = tdNik ? tdNik.textContent || tdNik.innerText : "";
                    txtValueNama = tdNama ? tdNama.textContent || tdNama.innerText : "";
                    if (txtValueNik.toUpperCase().indexOf(filter) > -1 || txtValueNama.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        function applyBulkActionWajibPajak() {
            const action = document.getElementById('bulkActionWajibPajak').value;
            const selectedCheckboxes = document.querySelectorAll('input[name="selected_wp[]"]:checked');
            let selectedIds = [];
            selectedCheckboxes.forEach(checkbox => {
                selectedIds.push(checkbox.value);
            });

            if (!action) {
                alert('Pilih aksi yang ingin diterapkan.');
                return;
            }
            if (selectedIds.length === 0) {
                alert('Pilih setidaknya satu wajib pajak.');
                return;
            }

            if (confirm(`Apakah Anda yakin ingin ${action} ${selectedIds.length} wajib pajak terpilih? Proses ini mungkin tidak dapat diurungkan.`)) {
                console.log('Aksi:', action, 'untuk ID:', selectedIds.join(', '));
                alert(`Aksi "${action}" akan diterapkan pada ID: ${selectedIds.join(', ')}. (Fitur ini memerlukan implementasi backend)`);
            }
        }
    </script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>