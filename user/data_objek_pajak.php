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

require_once '../php/db_connect.php';

$errors = [];
$success_message = ""; // Akan digunakan untuk pesan sukses lokal di halaman ini
$djp_data_user = null; // Data objek pajak yang akan diedit atau data baru
$edit_mode = false;
$id_data_djp_edit = null;

// Cek apakah mode edit berdasarkan parameter GET 'id'
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $edit_mode = true;
    $id_data_djp_edit = intval($_GET['id']);

    // Ambil data objek pajak yang akan diedit, pastikan milik user yang login
    $stmt_get_djp = $conn->prepare("SELECT * FROM data_djp_user WHERE id_data_djp = ? AND id_pengguna = ?");
    if ($stmt_get_djp) {
        $stmt_get_djp->bind_param("ii", $id_data_djp_edit, $id_user_logged_in);
        $stmt_get_djp->execute();
        $result_djp = $stmt_get_djp->get_result();
        if ($result_djp->num_rows === 1) {
            $djp_data_user = $result_djp->fetch_assoc();
        } else {
            // Set error jika data tidak ditemukan atau bukan milik user
            $_SESSION['flash_message_djp_list'] = "Data objek pajak tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.";
            $_SESSION['flash_message_djp_list_type'] = "error";
            header("Location: " . BASE_URL_USER_ROOT . "user/daftar_objek_pajak.php"); // Redirect ke halaman daftar
            exit();
        }
        $stmt_get_djp->close();
    } else {
        $errors[] = "Gagal mengambil data objek pajak: " . $conn->error;
        // Tidak redirect, tampilkan error di form
    }
}

// Pengaturan judul halaman berdasarkan mode
$page_title_user = $edit_mode ? "Edit Data Objek Pajak" : "Tambah Data Objek Pajak Baru";
$current_page_user = 'daftar_objek_pajak'; // Menu sidebar yang aktif
$page_title_for_header = $page_title_user;


// Proses form submission untuk simpan (tambah baru) atau update (edit)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_data_objek_pajak'])) {
    // Ambil data dari form
    $npwp = isset($_POST['npwp']) ? $conn->real_escape_string(trim($_POST['npwp'])) : null;
    if (empty($npwp)) $npwp = null; // Pastikan NPWP kosong disimpan sebagai NULL

    $nama_pemilik_bangunan = isset($_POST['nama_pemilik_bangunan']) ? $conn->real_escape_string(trim($_POST['nama_pemilik_bangunan'])) : '';
    $alamat_objek_pajak = isset($_POST['alamat_objek_pajak']) ? $conn->real_escape_string(trim($_POST['alamat_objek_pajak'])) : '';
    $jenis_bangunan = isset($_POST['jenis_bangunan']) ? $conn->real_escape_string(trim($_POST['jenis_bangunan'])) : '';
    $data_tambahan = isset($_POST['data_tambahan']) ? $conn->real_escape_string(trim($_POST['data_tambahan'])) : null;

    // ID data DJP yang sedang diedit (jika mode edit)
    $id_data_djp_to_process = isset($_POST['id_data_djp']) ? intval($_POST['id_data_djp']) : null;

    // Penanganan dan validasi input Luas Bangunan
    $luas_bangunan_input = isset($_POST['luas_bangunan']) ? trim($_POST['luas_bangunan']) : '';
    $luas_bangunan = null;
    if ($luas_bangunan_input === '') {
        $errors[] = "Luas Bangunan wajib diisi.";
    } else {
        // Hapus pemisah ribuan (titik), ganti koma desimal dengan titik
        $luas_bangunan_sanitized = str_replace('.', '', str_replace(',', '.', $luas_bangunan_input));
        if (is_numeric($luas_bangunan_sanitized) && floatval($luas_bangunan_sanitized) > 0) {
            $luas_bangunan = floatval($luas_bangunan_sanitized);
        } else {
            $errors[] = "Format Luas Bangunan tidak valid atau harus lebih dari 0. Gunakan angka (misal 100 atau 100.50).";
        }
    }

    // Penanganan dan validasi input Luas Tanah
    $luas_tanah_input = isset($_POST['luas_tanah']) ? trim($_POST['luas_tanah']) : '';
    $luas_tanah = null;
    if ($luas_tanah_input === '') {
        $errors[] = "Luas Tanah wajib diisi.";
    } else {
        $luas_tanah_sanitized = str_replace('.', '', str_replace(',', '.', $luas_tanah_input));
        if (is_numeric($luas_tanah_sanitized) && floatval($luas_tanah_sanitized) > 0) {
            $luas_tanah = floatval($luas_tanah_sanitized);
        } else {
            $errors[] = "Format Luas Tanah tidak valid atau harus lebih dari 0. Gunakan angka (misal 100 atau 100.50).";
        }
    }

    // Validasi dasar lainnya
    if (empty($nama_pemilik_bangunan)) $errors[] = "Nama Pemilik Bangunan wajib diisi.";
    if (empty($alamat_objek_pajak)) $errors[] = "Alamat Objek Pajak wajib diisi.";
    if (empty($jenis_bangunan)) $errors[] = "Jenis Bangunan wajib diisi.";

    // Jika tidak ada error validasi
    if (empty($errors)) {
        if ($edit_mode && $id_data_djp_to_process) { // Mode Edit
            $sql_process_djp = "UPDATE data_djp_user SET npwp = ?, nama_pemilik_bangunan = ?, alamat_objek_pajak = ?, luas_bangunan = ?, luas_tanah = ?, jenis_bangunan = ?, data_tambahan = ?, tanggal_update = NOW() WHERE id_data_djp = ? AND id_pengguna = ?";
            $stmt_process_djp = $conn->prepare($sql_process_djp);
            if ($stmt_process_djp) {
                $stmt_process_djp->bind_param("sssddssii", $npwp, $nama_pemilik_bangunan, $alamat_objek_pajak, $luas_bangunan, $luas_tanah, $jenis_bangunan, $data_tambahan, $id_data_djp_to_process, $id_user_logged_in);
                if ($stmt_process_djp->execute()) {
                    // Set flash message untuk ditampilkan di halaman daftar objek pajak
                    $_SESSION['flash_message_djp_list'] = "Data objek pajak (ID: " . $id_data_djp_to_process . ") berhasil diperbarui.";
                    $_SESSION['flash_message_djp_list_type'] = "success";
                    header("Location: " . BASE_URL_USER_ROOT . "user/daftar_objek_pajak.php");
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
                $stmt_process_djp->bind_param("isssddss", $id_user_logged_in, $npwp, $nama_pemilik_bangunan, $alamat_objek_pajak, $luas_bangunan, $luas_tanah, $jenis_bangunan, $data_tambahan);
                if ($stmt_process_djp->execute()) {
                    $_SESSION['flash_message_djp_list'] = "Data objek pajak baru berhasil disimpan.";
                    $_SESSION['flash_message_djp_list_type'] = "success";
                    header("Location: " . BASE_URL_USER_ROOT . "user/daftar_objek_pajak.php");
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
    // Jika ada error, $djp_data_user diisi kembali dari POST untuk repopulate form
    if (!empty($errors)) {
        $djp_data_user = $_POST;
        // Pastikan luas_bangunan dan luas_tanah tetap string agar tidak error di number_format jika validasi gagal
        $djp_data_user['luas_bangunan'] = $_POST['luas_bangunan'];
        $djp_data_user['luas_tanah'] = $_POST['luas_tanah'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - InfoPajak'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL_ADMIN; ?>assets/images/icon.png">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user-data-objek-pajak.css?v=<?php echo time(); ?>">
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
                        <a href="<?php echo BASE_URL_USER_ROOT; ?>user/daftar_objek_pajak.php"> <!-- Ini akan menjadi halaman daftar nantinya -->
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
                <div class="admin-card user-form-card">
                    <div class="admin-card-header">
                        <?php echo $edit_mode ? 'Edit Data Objek Pajak (ID: ' . htmlspecialchars($id_data_djp_edit) . ')' : 'Tambah Data Objek Pajak Baru'; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="form-message error">
                            <strong>Terjadi Kesalahan:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): // Pesan sukses akan ditampilkan di sini jika tidak redirect 
                    ?>
                        <div class="form-message success">
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <form action="data_objek_pajak.php<?php echo $edit_mode ? '?id=' . $id_data_djp_edit : ''; ?>" method="POST" class="user-form">
                        <?php if ($edit_mode && $id_data_djp_edit): ?>
                            <input type="hidden" name="id_data_djp" value="<?php echo $id_data_djp_edit; ?>">
                        <?php endif; ?>
                        <fieldset>
                            <legend>Detail Objek Pajak</legend>
                            <div class="form-group">
                                <label for="npwp">NPWP (Nomor Pokok Wajib Pajak)</label>
                                <input type="text" id="npwp" name="npwp" value="<?php echo htmlspecialchars($djp_data_user['npwp'] ?? ''); ?>" placeholder="Jika ada, contoh: 00.000.000.0-000.000">
                                <small>Opsional, isi jika objek pajak terdaftar atas NPWP ini.</small>
                            </div>
                            <div class="form-group">
                                <label for="nama_pemilik_bangunan">Nama Pemilik Bangunan (sesuai PBB)</label>
                                <input type="text" id="nama_pemilik_bangunan" name="nama_pemilik_bangunan" value="<?php echo htmlspecialchars($djp_data_user['nama_pemilik_bangunan'] ?? $nama_user_session); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="alamat_objek_pajak">Alamat Lengkap Objek Pajak</label>
                                <textarea id="alamat_objek_pajak" name="alamat_objek_pajak" rows="3" required><?php echo htmlspecialchars($djp_data_user['alamat_objek_pajak'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="luas_bangunan">Luas Bangunan (m²)</label>
                                    <input type="text" id="luas_bangunan" name="luas_bangunan" value="<?php echo isset($djp_data_user['luas_bangunan']) ? number_format(floatval($djp_data_user['luas_bangunan']), 2, ',', '.') : (isset($_POST['luas_bangunan']) ? htmlspecialchars($_POST['luas_bangunan']) : ''); ?>" required placeholder="Contoh: 100,50">
                                </div>
                                <div class="form-group">
                                    <label for="luas_tanah">Luas Tanah (m²)</label>
                                    <input type="text" id="luas_tanah" name="luas_tanah" value="<?php echo isset($djp_data_user['luas_tanah']) ? number_format(floatval($djp_data_user['luas_tanah']), 2, ',', '.') : (isset($_POST['luas_tanah']) ? htmlspecialchars($_POST['luas_tanah']) : ''); ?>" required placeholder="Contoh: 200,75">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="jenis_bangunan">Jenis Bangunan</label>
                                <input type="text" id="jenis_bangunan" name="jenis_bangunan" value="<?php echo htmlspecialchars($djp_data_user['jenis_bangunan'] ?? ''); ?>" required placeholder="Contoh: Rumah Tinggal, Ruko, Gudang">
                            </div>
                            <div class="form-group">
                                <label for="data_tambahan">Data Tambahan (jika ada)</label>
                                <textarea id="data_tambahan" name="data_tambahan" rows="3"><?php echo htmlspecialchars($djp_data_user['data_tambahan'] ?? ''); ?></textarea>
                                <small>Contoh: Nomor Blok, Kavling, NOP (Nomor Objek Pajak) jika diketahui.</small>
                            </div>
                        </fieldset>

                        <div class="form-actions">
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>user/daftar_objek_pajak.php" class="button btn-secondary">Kembali ke Daftar Objek</a>
                            <button type="submit" name="simpan_data_objek_pajak" class="button btn-primary">
                                <i class="fas fa-save"></i> <?php echo $edit_mode ? 'Simpan Perubahan' : 'Tambah Data Objek'; ?>
                            </button>
                        </div>
                    </form>
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

            document.querySelectorAll('input[name="luas_bangunan"], input[name="luas_tanah"]').forEach(function(input) {
                // Fungsi untuk memformat angka dengan pemisah ribuan Indonesia saat input
                function formatNumberInput(value) {
                    if (!value) return '';
                    // Hapus semua karakter non-digit kecuali koma untuk desimal awal
                    let numStr = String(value).replace(/[^0-9,]/g, '');
                    // Ganti koma dengan titik untuk pemrosesan internal
                    numStr = numStr.replace(',', '.');
                    // Hapus semua titik kecuali yang terakhir sebagai pemisah desimal
                    numStr = numStr.replace(/\.(?![^.]*$)/g, '');

                    if (isNaN(parseFloat(numStr))) return ''; // Jika bukan angka setelah dibersihkan

                    // Format ke tampilan Indonesia (titik ribuan, koma desimal)
                    let parts = numStr.split('.');
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, "."); // Tambah titik ribuan
                    return parts.join(',');
                }

                // Fungsi untuk mendapatkan nilai numerik bersih (koma jadi titik untuk float)
                function getCleanNumericForSubmit(formattedValue) {
                    if (!formattedValue) return '';
                    return String(formattedValue).replace(/\./g, '').replace(',', '.');
                }

                input.addEventListener('input', function(e) {
                    let rawValue = String(e.target.value).replace(/[^0-9,]/g, ''); // Hanya angka dan koma
                    // Simpan posisi kursor
                    let cursorPos = e.target.selectionStart;
                    let originalLength = e.target.value.length;

                    // Hapus semua titik ribuan yang mungkin sudah ada
                    let noThousands = rawValue.replace(/\./g, '');

                    // Format dengan titik ribuan (Indonesia)
                    let parts = noThousands.split(',');
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    e.target.value = parts.join(',');

                    // Kembalikan posisi kursor
                    let newLength = e.target.value.length;
                    cursorPos += (newLength - originalLength);
                    // Hindari posisi negatif atau di luar batas
                    cursorPos = Math.max(0, Math.min(cursorPos, newLength));
                    if (e.inputType !== 'deleteContentBackward' && e.inputType !== 'deleteContentForward') {
                        // Hanya set jika bukan operasi hapus untuk menghindari lompatan kursor
                        // Namun ini masih bisa kurang sempurna untuk semua kasus edit
                    }
                });

                // Format nilai awal saat halaman dimuat
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
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ?>
</body>

</html>