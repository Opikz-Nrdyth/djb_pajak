<?php
// admin/laporan_harian.php
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
$foto_profil_admin_session = isset($_SESSION['foto_profil']) ? htmlspecialchars($_SESSION['foto_profil']) : BASE_URL_ADMIN . 'assets/images/default_avatar.png';

// Pengaturan untuk halaman ini
$page_title_admin = "Laporan Harian";
$current_page = 'laporan_harian';
$current_parent_page_sub = 'laporan_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$laporan_harian_results = [];
$selected_date = date("Y-m-d");
$total_pbb_harian = 0;
$jumlah_transaksi_harian = 0;

if (isset($_GET['tanggal_laporan'])) {
    $selected_date_input = $_GET['tanggal_laporan'];
    if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $selected_date_input)) {
        $selected_date = $selected_date_input;
    } else {
        $errors[] = "Format tanggal tidak valid. Gunakan format YYYY-MM-DD.";
    }
}

if (empty($errors)) {
    $sql_laporan_harian = "SELECT pp.*, p.nama_lengkap AS nama_wajib_pajak, p.nik AS nik_wajib_pajak, admin_rev.nama_lengkap AS nama_admin_pereview
                           FROM perhitungan_pajak pp
                           JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
                           JOIN pengguna p ON du.id_pengguna = p.id_pengguna
                           LEFT JOIN pengguna admin_rev ON pp.id_admin_pereview = admin_rev.id_pengguna
                           WHERE DATE(pp.tanggal_perhitungan) = ? 
                           ORDER BY pp.tanggal_perhitungan DESC";

    $stmt = $conn->prepare($sql_laporan_harian);
    if ($stmt) {
        $stmt->bind_param("s", $selected_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $laporan_harian_results[] = $row;
                $total_pbb_harian += floatval($row['jumlah_pbb_terutang']);
                $jumlah_transaksi_harian++;
            }
        }
        $stmt->close();
    } else {
        $errors[] = "Gagal mengambil data laporan harian: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - Admin Panel InfoPajak'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/icon.png">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/css/admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/css/admin-laporan-harian.css?v=<?php echo time(); ?>">
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
                    <?php // Fitur search di header dihapus 
                    ?>
                </div>
                <div class="header-right">
                    <?php // Fitur notifikasi di header dihapus 
                    ?>
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
                        Filter Laporan Harian
                    </div>
                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form action="<?php echo BASE_URL_ADMIN; ?>admin/laporan_harian.php" method="GET" class="filter-form-inline">
                        <div class="form-group">
                            <label for="tanggal_laporan">Pilih Tanggal:</label>
                            <input type="date" id="tanggal_laporan" name="tanggal_laporan" value="<?php echo htmlspecialchars($selected_date); ?>" required>
                        </div>
                        <button type="submit" class="button btn-primary btn-sm"><i class="fas fa-filter"></i> Tampilkan Laporan</button>
                    </form>
                </div>

                <?php if (isset($_GET['tanggal_laporan']) && empty($errors)): ?>
                    <div class="admin-card" id="hasil-laporan-harian">
                        <div class="admin-card-header">
                            <span>Laporan Harian untuk Tanggal: <?php echo htmlspecialchars(date('d F Y', strtotime($selected_date))); ?></span>
                            <div class="header-actions">
                                <button onclick="exportTableToCSV('laporan_harian_<?php echo $selected_date; ?>.csv', 'tabelLaporanHarian')" class="button btn-success btn-sm"><i class="fas fa-file-csv"></i> Export CSV</button>
                                <button onclick="printLaporanHarian()" class="button btn-print btn-sm"><i class="fas fa-print"></i> Cetak</button>
                            </div>
                        </div>

                        <div class="report-summary">
                            <p><strong>Total Transaksi Perhitungan:</strong> <?php echo $jumlah_transaksi_harian; ?></p>
                            <p><strong>Total PBB Terutang:</strong> Rp <?php echo number_format($total_pbb_harian, 2, ',', '.'); ?></p>
                        </div>

                        <?php if (!empty($laporan_harian_results)): ?>
                            <div class="table-container">
                                <table class="data-table" id="tabelLaporanHarian">
                                    <thead>
                                        <tr>
                                            <th>ID Hitung</th>
                                            <th>Wajib Pajak (NIK)</th>
                                            <th class="text-center">Tahun Pajak</th>
                                            <th>Jam Perhitungan</th>
                                            <th class="text-right">Jml. PBB Terutang</th>
                                            <th>Status Perhitungan</th>
                                            <th>Admin Pereview</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($laporan_harian_results as $result): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($result['id_perhitungan']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($result['nama_wajib_pajak']); ?><br>
                                                    <small>(NIK: <?php echo htmlspecialchars($result['nik_wajib_pajak']); ?>)</small>
                                                </td>
                                                <td class="text-center"><?php echo htmlspecialchars($result['periode_pajak_tahun']); ?></td>
                                                <td><?php echo htmlspecialchars(date('H:i:s', strtotime($result['tanggal_perhitungan']))); ?></td>
                                                <td class="text-right">Rp <?php echo number_format($result['jumlah_pbb_terutang'], 2, ',', '.'); ?></td>
                                                <td class="status-<?php echo htmlspecialchars(str_replace('_', '-', $result['status_perhitungan'])); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($result['status_perhitungan']))); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($result['nama_admin_pereview'] ?? 'N/A'); ?></td>
                                                <td class="action-buttons">
                                                    <a href="<?php echo BASE_URL_ADMIN; ?>admin/detail_perhitungan.php?id=<?php echo $result['id_perhitungan']; ?>" class="button btn-view btn-sm" title="Lihat Detail Perhitungan">
                                                        <i class="fas fa-eye"></i> <span>Detail</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center" style="margin-top:15px; padding: 10px;">Tidak ada data perhitungan pajak yang ditemukan untuk tanggal <?php echo htmlspecialchars(date('d F Y', strtotime($selected_date))); ?>.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <script>
        function exportTableToCSV(filename, tableId) {
            let csv = [];
            const rows = document.querySelectorAll("#" + tableId + " tr");

            for (let i = 0; i < rows.length; i++) {
                let row = [],
                    cols = rows[i].querySelectorAll("td, th");

                for (let j = 0; j < cols.length; j++) {
                    if (cols[j].classList.contains('action-buttons')) continue; // Skip kolom aksi

                    let cellText = cols[j].innerText.trim();
                    if (cols[j].querySelector('small')) { // Membersihkan NIK dari kolom Wajib Pajak
                        cellText = cols[j].firstChild.textContent.trim();
                    }
                    row.push('"' + cellText.replace(/"/g, '""') + '"');
                }
                if (row.length > 0) { // Hanya push jika ada data (setelah skip aksi)
                    csv.push(row.join(","));
                }
            }
            downloadCSV(csv.join("\n"), filename);
        }

        function downloadCSV(csv, filename) {
            let csvFile;
            let downloadLink;

            csvFile = new Blob(["\uFEFF" + csv], {
                type: "text/csv;charset=utf-8;"
            }); // Tambah BOM untuk Excel
            downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        function printLaporanHarian() {
            const selectedDateFormatted = "<?php echo htmlspecialchars(date('d F Y', strtotime($selected_date))); ?>";
            const title = `Laporan Harian Perhitungan Pajak - ${selectedDateFormatted}`;

            let tableHtml = document.getElementById("tabelLaporanHarian").outerHTML;

            // Buat ringkasan untuk print
            const totalTransaksi = "<?php echo $jumlah_transaksi_harian; ?>";
            const totalPBB = "Rp <?php echo number_format($total_pbb_harian, 2, ',', '.'); ?>";
            const summaryHtml = `
                <div style="margin-top: 20px; margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
                    <p><strong>Total Transaksi Perhitungan:</strong> ${totalTransaksi}</p>
                    <p><strong>Total PBB Terutang:</strong> ${totalPBB}</p>
                </div>
            `;

            // Hapus kolom aksi untuk print
            let tempTable = document.createElement('table');
            tempTable.innerHTML = tableHtml;
            const rows = tempTable.querySelectorAll("tr");
            rows.forEach(row => {
                if (row.cells.length > 0) { // Pastikan ada cell
                    const actionCellIndex = Array.from(row.parentElement.children[0].cells).findIndex(th => th.innerText.trim().toLowerCase() === 'aksi');
                    if (actionCellIndex !== -1 && row.cells[actionCellIndex]) {
                        row.deleteCell(actionCellIndex);
                    }
                }
            });
            tableHtml = tempTable.outerHTML;


            let printWindow = window.open('', '_blank', 'height=600,width=800');
            printWindow.document.write('<html><head><title>' + title + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 15px; }');
            printWindow.document.write('th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 0.9em;}');
            printWindow.document.write('th { background-color: #f2f2f2; }');
            printWindow.document.write('.text-center { text-align: center; } .text-right { text-align: right; }');
            printWindow.document.write('small { font-size:0.8em; color:#555; display:block; }');
            printWindow.document.write('@media print { .no-print { display: none; } }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<div style="text-align:center; margin-bottom:20px;">');
            // Ganti dengan path logo yang benar atau hapus jika tidak ada
            // printWindow.document.write('<img src="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/logo_djp_admin.png" alt="Logo" style="height:50px; margin-bottom:10px;">');
            printWindow.document.write('<h2>' + title + '</h2><hr></div>');
            printWindow.document.write(summaryHtml);
            printWindow.document.write(tableHtml);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();

            // Tambahkan sedikit delay sebelum print untuk memastikan konten di-load
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
    </script>
    <?php
    if ($conn) {
        $conn->close();
    }
    ?>
</body>

</html>