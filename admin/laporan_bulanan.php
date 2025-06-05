<?php
// admin/laporan_bulanan.php
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
$page_title_admin = "Laporan Bulanan";
$current_page = 'laporan_bulanan';
$current_parent_page_sub = 'laporan_pajak';
$current_parent_page = 'pengelolaan_pajak';
$page_title_for_header = $page_title_admin;

require_once '../php/db_connect.php';

$errors = [];
$laporan_bulanan_results = [];
$selected_month = date("m");
$selected_year = date("Y");
$total_pbb_bulanan = 0;
$jumlah_transaksi_bulanan = 0;

$months = [
    "01" => "Januari",
    "02" => "Februari",
    "03" => "Maret",
    "04" => "April",
    "05" => "Mei",
    "06" => "Juni",
    "07" => "Juli",
    "08" => "Agustus",
    "09" => "September",
    "10" => "Oktober",
    "11" => "November",
    "12" => "Desember"
];

$available_years_report = [];
$current_year_for_select = date("Y");
// Mengambil tahun unik dari tanggal_perhitungan untuk filter tahun laporan
$year_query_report = $conn->query("SELECT DISTINCT YEAR(tanggal_perhitungan) AS tahun_laporan FROM perhitungan_pajak ORDER BY tahun_laporan DESC");
if ($year_query_report) {
    while ($yr_rep = $year_query_report->fetch_assoc()) {
        $available_years_report[] = $yr_rep['tahun_laporan'];
    }
}
// Pastikan tahun saat ini ada di daftar jika belum ada data untuk tahun tsb atau tidak ada data sama sekali
if (!in_array($current_year_for_select, $available_years_report)) {
    $available_years_report[] = $current_year_for_select; // Tambahkan tahun ini jika belum ada
}
if (empty($available_years_report)) { // Jika tabel kosong sama sekali, tambahkan tahun ini
    $available_years_report[] = $current_year_for_select;
}
rsort($available_years_report); // Urutkan descending setelah semua potensi tahun ditambahkan


if (isset($_GET['filter_bulan']) && isset($_GET['filter_tahun'])) {
    $selected_month_input = $_GET['filter_bulan'];
    $selected_year_input = $_GET['filter_tahun'];

    if (array_key_exists($selected_month_input, $months)) {
        $selected_month = $selected_month_input;
    } else {
        $errors[] = "Bulan tidak valid.";
    }
    if (is_numeric($selected_year_input) && $selected_year_input >= 1900 && $selected_year_input <= (date("Y") + 5)) {
        $selected_year = $selected_year_input;
    } else {
        $errors[] = "Tahun tidak valid.";
    }
}

if (empty($errors)) {
    $sql_laporan_bulanan = "SELECT pp.*, p.nama_lengkap AS nama_wajib_pajak, p.nik AS nik_wajib_pajak, admin_rev.nama_lengkap AS nama_admin_pereview
                           FROM perhitungan_pajak pp
                           JOIN data_djp_user du ON pp.id_data_djp = du.id_data_djp
                           JOIN pengguna p ON du.id_pengguna = p.id_pengguna
                           LEFT JOIN pengguna admin_rev ON pp.id_admin_pereview = admin_rev.id_pengguna
                           WHERE MONTH(pp.tanggal_perhitungan) = ? AND YEAR(pp.tanggal_perhitungan) = ?
                           ORDER BY pp.tanggal_perhitungan DESC";

    $stmt = $conn->prepare($sql_laporan_bulanan);
    if ($stmt) {
        $stmt->bind_param("ss", $selected_month, $selected_year);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $laporan_bulanan_results[] = $row;
                $total_pbb_bulanan += floatval($row['jumlah_pbb_terutang']);
                $jumlah_transaksi_bulanan++;
            }
        }
        $stmt->close();
    } else {
        $errors[] = "Gagal mengambil data laporan bulanan: " . $conn->error;
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
    <link rel="stylesheet" href="<?php echo BASE_URL_ADMIN; ?>assets/css/admin-laporan-bulanan.css?v=<?php echo time(); ?>">
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
                        Filter Laporan Bulanan
                    </div>
                    <?php if (!empty($errors)): ?>
                        <div class="auth-errors" style="margin-bottom: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form action="<?php echo BASE_URL_ADMIN; ?>admin/laporan_bulanan.php" method="GET" class="filter-form-inline">
                        <div class="form-group">
                            <label for="filter_bulan">Pilih Bulan:</label>
                            <select name="filter_bulan" id="filter_bulan">
                                <?php foreach ($months as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo ($selected_month == $num) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_tahun">Pilih Tahun:</label>
                            <select name="filter_tahun" id="filter_tahun">
                                <?php foreach ($available_years_report as $year_opt): ?>
                                    <option value="<?php echo $year_opt; ?>" <?php echo ($selected_year == $year_opt) ? 'selected' : ''; ?>>
                                        <?php echo $year_opt; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="button btn-primary btn-sm"><i class="fas fa-filter"></i> Tampilkan Laporan</button>
                    </form>
                </div>

                <?php if ((isset($_GET['filter_bulan']) && isset($_GET['filter_tahun']) && empty($errors)) || (!isset($_GET['filter_bulan']) && !isset($_GET['filter_tahun']) && empty($errors))): // Tampilkan jika filter diterapkan atau halaman baru dimuat (default bulan tahun ini)
                ?>
                    <div class="admin-card" id="hasil-laporan-bulanan">
                        <div class="admin-card-header">
                            <span>Laporan Bulanan untuk: <?php echo htmlspecialchars($months[$selected_month]) . " " . htmlspecialchars($selected_year); ?></span>
                            <div class="header-actions">
                                <button onclick="exportTableToCSV('laporan_bulanan_<?php echo $selected_month . '_' . $selected_year; ?>.csv', 'tabelLaporanBulanan')" class="button btn-success btn-sm"><i class="fas fa-file-csv"></i> Export CSV</button>
                                <button onclick="printLaporan('hasil-laporan-bulanan', 'Laporan Bulanan Perhitungan Pajak', '<?php echo htmlspecialchars($months[$selected_month]) . " " . htmlspecialchars($selected_year); ?>')" class="button btn-print btn-sm"><i class="fas fa-print"></i> Cetak</button>
                            </div>
                        </div>

                        <div class="report-summary">
                            <p><strong>Total Transaksi Perhitungan:</strong> <?php echo $jumlah_transaksi_bulanan; ?></p>
                            <p><strong>Total PBB Terutang:</strong> Rp <?php echo number_format($total_pbb_bulanan, 2, ',', '.'); ?></p>
                        </div>

                        <?php if (!empty($laporan_bulanan_results)): ?>
                            <div class="table-container">
                                <table class="data-table" id="tabelLaporanBulanan">
                                    <thead>
                                        <tr>
                                            <th>ID Hitung</th>
                                            <th>Wajib Pajak (NIK)</th>
                                            <th>Tgl. Perhitungan</th>
                                            <th class="text-right">Jml. PBB Terutang</th>
                                            <th>Status Perhitungan</th>
                                            <th>Admin Pereview</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($laporan_bulanan_results as $result): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($result['id_perhitungan']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($result['nama_wajib_pajak']); ?><br>
                                                    <small>(NIK: <?php echo htmlspecialchars($result['nik_wajib_pajak']); ?>)</small>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($result['tanggal_perhitungan']))); ?></td>
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
                            <p class="text-center" style="margin-top:15px; padding: 10px;">Tidak ada data perhitungan pajak yang ditemukan untuk periode <?php echo htmlspecialchars($months[$selected_month]) . " " . htmlspecialchars($selected_year); ?>.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL_ADMIN; ?>assets/js/admin_script.js?v=<?php echo time(); ?>"></script>
    <script>
        function exportTableToCSV(filename, tableId) {
            let csv = [];
            const table = document.getElementById(tableId);
            if (!table) return;
            const rows = table.querySelectorAll("tr");

            for (let i = 0; i < rows.length; i++) {
                let row = [],
                    cols = rows[i].querySelectorAll("td, th");

                for (let j = 0; j < cols.length; j++) {
                    if (cols[j].classList.contains('action-buttons')) continue;

                    let cellText = cols[j].innerText.trim();
                    if (cols[j].querySelector('small')) {
                        cellText = cols[j].firstChild.textContent.trim();
                    }
                    row.push('"' + cellText.replace(/"/g, '""') + '"');
                }
                if (row.length > 0) {
                    csv.push(row.join(","));
                }
            }
            downloadCSV(csv.join("\n"), filename);
        }

        function downloadCSV(csv, filename) {
            let csvFile = new Blob(["\uFEFF" + csv], {
                type: "text/csv;charset=utf-8;"
            });
            let downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        function printLaporan(elementId, reportTitle, reportPeriod) {
            const reportContentElement = document.getElementById(elementId);
            if (!reportContentElement) return;

            const tableToPrint = reportContentElement.querySelector('.data-table');
            const summaryToPrint = reportContentElement.querySelector('.report-summary');

            let tableHtml = "";
            if (tableToPrint) {
                let tempTable = document.createElement('table');
                tempTable.innerHTML = tableToPrint.outerHTML;
                const rows = tempTable.querySelectorAll("tr");
                rows.forEach(row => {
                    if (row.cells.length > 0) {
                        const actionHeader = Array.from(row.parentElement.children[0].cells).find(th => th.innerText.trim().toLowerCase() === 'aksi');
                        if (actionHeader) {
                            const actionCellIndex = actionHeader.cellIndex;
                            if (row.cells[actionCellIndex]) {
                                row.deleteCell(actionCellIndex);
                            }
                        }
                    }
                });
                tableHtml = tempTable.outerHTML;
            }

            const summaryHtml = summaryToPrint ? summaryToPrint.outerHTML : "";

            let printWindow = window.open('', '_blank', 'height=600,width=800');
            printWindow.document.write('<html><head><title>' + reportTitle + ' - ' + reportPeriod + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 15px;}');
            printWindow.document.write('th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }');
            printWindow.document.write('th { background-color: #f2f2f2; }');
            printWindow.document.write('.text-right { text-align: right; } .text-center { text-align: center; }');
            printWindow.document.write('small { font-size:0.8em; color:#555; display:block; }');
            printWindow.document.write('.report-summary { margin-bottom: 15px; padding: 10px; border: 1px solid #eee; background-color: #f9f9f9;}');
            printWindow.document.write('.report-summary p { margin: 5px 0; font-size: 0.95em; }');
            printWindow.document.write('@media print { .no-print { display: none; } .header-actions { display: none !important; } }'); // Sembunyikan header-actions saat print
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<div style="text-align:center; margin-bottom:20px;">');
            // printWindow.document.write('<img src="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/logo_djp_admin.png" alt="Logo" style="height:50px; margin-bottom:10px;">');
            printWindow.document.write('<h2>' + reportTitle + '</h2>');
            printWindow.document.write('<p>Periode: ' + reportPeriod + '</p><hr style="margin-top:15px;"></div>');
            printWindow.document.write(summaryHtml);
            printWindow.document.write(tableHtml);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
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