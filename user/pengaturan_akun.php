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
// Untuk user, kita asumsikan tidak ada fitur upload foto profil, jadi selalu default
$foto_profil_user_session = BASE_URL_USER_ROOT . 'assets/images/default_avatar_user.png';

// Pengaturan untuk halaman ini
$page_title_user = "Pengaturan Akun Saya";
$current_page_user = 'pengaturan_akun_user';
$page_title_for_header = $page_title_user;

require_once '../php/db_connect.php';

$errors_profil_user = [];
$success_profil_user = "";
$errors_password_user = [];
$success_password_user = "";

// Ambil data pengguna saat ini dari database untuk form profil
$user_current_data_db = null;
$stmt_user_current = $conn->prepare("SELECT nama_lengkap, nik, email, no_telepon, username FROM pengguna WHERE id_pengguna = ?");
if ($stmt_user_current) {
    $stmt_user_current->bind_param("i", $id_user_logged_in);
    $stmt_user_current->execute();
    $result_user_current = $stmt_user_current->get_result();
    if ($result_user_current->num_rows === 1) {
        $user_current_data_db = $result_user_current->fetch_assoc();
    } else {
        $errors_profil_user[] = "Gagal memuat data profil Anda.";
    }
    $stmt_user_current->close();
} else {
    $errors_profil_user[] = "Gagal mempersiapkan query data profil: " . $conn->error;
}


// Proses Update Profil Pengguna
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profil_user'])) {
    $nama_lengkap_new_user = $conn->real_escape_string(trim($_POST['nama_lengkap_profil_user']));
    $email_new_user = $conn->real_escape_string(trim($_POST['email_profil_user']));
    $no_telepon_new_user = $conn->real_escape_string(trim($_POST['no_telepon_profil_user']));

    if (empty($nama_lengkap_new_user)) $errors_profil_user[] = "Nama Lengkap tidak boleh kosong.";
    if (empty($email_new_user)) $errors_profil_user[] = "Email tidak boleh kosong.";
    elseif (!filter_var($email_new_user, FILTER_VALIDATE_EMAIL)) $errors_profil_user[] = "Format email tidak valid.";

    if ($user_current_data_db && $email_new_user !== $user_current_data_db['email']) {
        $stmt_check_email_user = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE email = ? AND id_pengguna != ?");
        if ($stmt_check_email_user) {
            $stmt_check_email_user->bind_param("si", $email_new_user, $id_user_logged_in);
            $stmt_check_email_user->execute();
            if ($stmt_check_email_user->get_result()->num_rows > 0) {
                $errors_profil_user[] = "Email sudah digunakan oleh pengguna lain.";
            }
            $stmt_check_email_user->close();
        } else {
            $errors_profil_user[] = "Gagal memvalidasi email: " . $conn->error;
        }
    }

    if (empty($errors_profil_user)) {
        $sql_update_user_profil = "UPDATE pengguna SET nama_lengkap = ?, email = ?, no_telepon = ? WHERE id_pengguna = ?";
        $stmt_update_user_profil = $conn->prepare($sql_update_user_profil);
        if ($stmt_update_user_profil) {
            $stmt_update_user_profil->bind_param("sssi", $nama_lengkap_new_user, $email_new_user, $no_telepon_new_user, $id_user_logged_in);
            if ($stmt_update_user_profil->execute()) {
                $success_profil_user = "Profil berhasil diperbarui.";
                // Update session
                $_SESSION['nama_lengkap'] = $nama_lengkap_new_user;
                $_SESSION['email'] = $email_new_user; // Jika email disimpan di session
                // Ambil ulang data untuk ditampilkan
                $user_current_data_db['nama_lengkap'] = $nama_lengkap_new_user;
                $user_current_data_db['email'] = $email_new_user;
                $user_current_data_db['no_telepon'] = $no_telepon_new_user;
                // Update nama di sidebar juga
                $nama_user_session = $nama_lengkap_new_user;
            } else {
                $errors_profil_user[] = "Gagal memperbarui profil: " . $stmt_update_user_profil->error;
            }
            $stmt_update_user_profil->close();
        } else {
            $errors_profil_user[] = "Gagal mempersiapkan update profil: " . $conn->error;
        }
    }
}

// Proses Ubah Password Pengguna
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ubah_password_user'])) {
    $password_lama_user = trim($_POST['password_lama_user']);
    $password_baru_user = trim($_POST['password_baru_user']);
    $konfirmasi_password_baru_user = trim($_POST['konfirmasi_password_baru_user']);

    if (empty($password_lama_user)) $errors_password_user[] = "Password Lama tidak boleh kosong.";
    if (empty($password_baru_user)) $errors_password_user[] = "Password Baru tidak boleh kosong.";
    elseif (strlen($password_baru_user) < 8) $errors_password_user[] = "Password Baru minimal 8 karakter.";
    if ($password_baru_user !== $konfirmasi_password_baru_user) $errors_password_user[] = "Konfirmasi Password Baru tidak cocok.";

    if (empty($errors_password_user)) {
        $stmt_pass_user = $conn->prepare("SELECT password FROM pengguna WHERE id_pengguna = ?");
        if ($stmt_pass_user) {
            $stmt_pass_user->bind_param("i", $id_user_logged_in);
            $stmt_pass_user->execute();
            $result_pass_user = $stmt_pass_user->get_result();
            $current_user_pass_data = $result_pass_user->fetch_assoc();
            $stmt_pass_user->close();

            if ($current_user_pass_data && password_verify($password_lama_user, $current_user_pass_data['password'])) {
                $hashed_password_baru_user = password_hash($password_baru_user, PASSWORD_DEFAULT);
                $stmt_update_pass_user = $conn->prepare("UPDATE pengguna SET password = ? WHERE id_pengguna = ?");
                if ($stmt_update_pass_user) {
                    $stmt_update_pass_user->bind_param("si", $hashed_password_baru_user, $id_user_logged_in);
                    if ($stmt_update_pass_user->execute()) {
                        $success_password_user = "Password berhasil diubah.";
                    } else {
                        $errors_password_user[] = "Gagal mengubah password: " . $stmt_update_pass_user->error;
                    }
                    $stmt_update_pass_user->close();
                } else {
                    $errors_password_user[] = "Gagal mempersiapkan update password: " . $conn->error;
                }
            } else {
                $errors_password_user[] = "Password Lama salah.";
            }
        } else {
            $errors_password_user[] = "Gagal mengambil data password: " . $conn->error;
        }
    }
}

$active_tab_user = isset($_GET['tab']) ? $_GET['tab'] : 'profil_user';
if (isset($_POST['update_profil_user'])) $active_tab_user = 'profil_user';
if (isset($_POST['ubah_password_user'])) $active_tab_user = 'keamanan_user';

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_for_header) . ' - InfoPajak'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user-pengaturan-akun.css?v=<?php echo time(); ?>">

    <link rel="stylesheet" href="<?php echo BASE_URL_USER_ROOT; ?>assets/css/user-pengaturan-akun-content.css?v=<?php echo time(); ?>">
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
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>user/pengaturan_akun.php?tab=profil_user">Profil Saya</a>
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>user/pengaturan_akun.php?tab=keamanan_user">Ubah Password</a>
                            <hr>
                            <a href="<?php echo BASE_URL_USER_ROOT; ?>logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="user-content-inner">
                <div class="user-card settings-card">
                    <div class="settings-tabs">
                        <a href="?tab=profil_user" class="tab-link <?php echo ($active_tab_user == 'profil_user') ? 'active' : ''; ?>">Profil Saya</a>
                        <a href="?tab=keamanan_user" class="tab-link <?php echo ($active_tab_user == 'keamanan_user') ? 'active' : ''; ?>">Ubah Password</a>
                    </div>

                    <div class="settings-tab-content <?php echo ($active_tab_user == 'profil_user') ? 'active' : ''; ?>" id="profil-user-content">
                        <div class="user-card-header no-border-bottom">
                            <h3>Informasi Profil Anda</h3>
                        </div>
                        <?php if (!empty($errors_profil_user)): ?>
                            <div class="flash-message error">
                                <?php foreach ($errors_profil_user as $error): ?><p><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_profil_user)): ?>
                            <div class="flash-message success">
                                <p><?php echo htmlspecialchars($success_profil_user); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($user_current_data_db): ?>
                            <form action="pengaturan_akun.php?tab=profil_user" method="POST" class="user-form" style="padding-top:10px;">
                                <div class="form-group">
                                    <label for="nama_lengkap_profil_user">Nama Lengkap</label>
                                    <input type="text" id="nama_lengkap_profil_user" name="nama_lengkap_profil_user" value="<?php echo htmlspecialchars($user_current_data_db['nama_lengkap']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="nik_profil_user">NIK</label>
                                    <input type="text" id="nik_profil_user" name="nik_profil_user_display" value="<?php echo htmlspecialchars($user_current_data_db['nik']); ?>" readonly disabled>
                                    <small>NIK tidak dapat diubah.</small>
                                </div>
                                <div class="form-group">
                                    <label for="email_profil_user">Email</label>
                                    <input type="email" id="email_profil_user" name="email_profil_user" value="<?php echo htmlspecialchars($user_current_data_db['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="no_telepon_profil_user">Nomor Telepon</label>
                                    <input type="tel" id="no_telepon_profil_user" name="no_telepon_profil_user" value="<?php echo htmlspecialchars($user_current_data_db['no_telepon'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="username_profil_user">Username</label>
                                    <input type="text" id="username_profil_user" name="username_profil_user_display" value="<?php echo htmlspecialchars($user_current_data_db['username']); ?>" readonly disabled>
                                    <small>Username tidak dapat diubah.</small>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_profil_user" class="button btn-primary">Simpan Perubahan Profil</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <p>Gagal memuat data profil Anda.</p>
                        <?php endif; ?>
                    </div>

                    <div class="settings-tab-content <?php echo ($active_tab_user == 'keamanan_user') ? 'active' : ''; ?>" id="keamanan-user-content">
                        <div class="user-card-header no-border-bottom">
                            <h3>Ubah Password Anda</h3>
                        </div>
                        <?php if (!empty($errors_password_user)): ?>
                            <div class="flash-message error">
                                <?php foreach ($errors_password_user as $error): ?><p><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_password_user)): ?>
                            <div class="flash-message success">
                                <p><?php echo htmlspecialchars($success_password_user); ?></p>
                            </div>
                        <?php endif; ?>
                        <form action="pengaturan_akun.php?tab=keamanan_user" method="POST" class="user-form" style="padding-top:10px;">
                            <div class="form-group">
                                <label for="password_lama_user">Password Lama</label>
                                <input type="password" id="password_lama_user" name="password_lama_user" required>
                            </div>
                            <div class="form-group">
                                <label for="password_baru_user">Password Baru</label>
                                <input type="password" id="password_baru_user" name="password_baru_user" required minlength="8">
                                <small>Minimal 8 karakter.</small>
                            </div>
                            <div class="form-group">
                                <label for="konfirmasi_password_baru_user">Konfirmasi Password Baru</label>
                                <input type="password" id="konfirmasi_password_baru_user" name="konfirmasi_password_baru_user" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="ubah_password_user" class="button btn-primary">Ubah Password</button>
                            </div>
                        </form>
                    </div>
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