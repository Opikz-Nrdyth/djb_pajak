<?php
// register.php
session_start(); // Mulai session di awal file

// Jika pengguna sudah login, redirect ke dashboard
if (isset($_SESSION['id_pengguna'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard_admin.php");
        exit();
    } else {
        header("Location: user/dashboard_user.php");
        exit();
    }
}

require_once 'php/db_connect.php'; // Include file koneksi database
require_once("php/config.php");

$page_title = "Register Akun - Sistem Informasi Pajak Bangunan";
$errors = []; // Untuk menampung pesan error
$success_message = ""; // Untuk pesan sukses

// Logika untuk proses registrasi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil dan sanitasi data dari form
    $nama_lengkap = isset($_POST['nama_lengkap']) ? $conn->real_escape_string(trim($_POST['nama_lengkap'])) : '';
    $nik = isset($_POST['nik']) ? $conn->real_escape_string(trim($_POST['nik'])) : '';
    $email = isset($_POST['email']) ? $conn->real_escape_string(trim($_POST['email'])) : '';
    $no_telepon = isset($_POST['no_telepon']) ? $conn->real_escape_string(trim($_POST['no_telepon'])) : '';
    $username = isset($_POST['username']) ? $conn->real_escape_string(trim($_POST['username'])) : '';
    $password_input = isset($_POST['password']) ? trim($_POST['password']) : '';
    $konfirmasi_password = isset($_POST['konfirmasi_password']) ? trim($_POST['konfirmasi_password']) : '';

    // Validasi data
    if (empty($nama_lengkap)) {
        $errors[] = "Nama Lengkap tidak boleh kosong.";
    }
    if (empty($nik)) {
        $errors[] = "NIK tidak boleh kosong.";
    } elseif (!preg_match("/^\d{16}$/", $nik)) {
        $errors[] = "NIK harus terdiri dari 16 digit angka.";
    }
    if (empty($email)) {
        $errors[] = "Email tidak boleh kosong.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    if (empty($no_telepon)) {
        $errors[] = "Nomor Telepon tidak boleh kosong.";
    } elseif (!preg_match("/^[0-9\s\-\+\(\)]+$/", $no_telepon)) { // Memperbolehkan beberapa karakter umum di nomor telepon
        $errors[] = "Format Nomor Telepon tidak valid.";
    }
    if (empty($username)) {
        $errors[] = "Username tidak boleh kosong.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]{5,20}$/", $username)) {
        $errors[] = "Username hanya boleh berisi huruf, angka, dan underscore, dengan panjang 5-20 karakter.";
    }
    if (empty($password_input)) {
        $errors[] = "Password tidak boleh kosong.";
    } elseif (strlen($password_input) < 8) {
        $errors[] = "Password minimal harus 8 karakter.";
    }
    if ($password_input !== $konfirmasi_password) {
        $errors[] = "Konfirmasi Password tidak cocok dengan Password.";
    }

    // Jika tidak ada error validasi dasar, cek keunikan NIK, Email, dan Username
    if (empty($errors)) {
        // Cek NIK
        $stmt_check_nik = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE nik = ?");
        if ($stmt_check_nik) {
            $stmt_check_nik->bind_param("s", $nik);
            $stmt_check_nik->execute();
            $result_check_nik = $stmt_check_nik->get_result();
            if ($result_check_nik->num_rows > 0) {
                $errors[] = "NIK sudah terdaftar.";
            }
            $stmt_check_nik->close();
        } else {
            $errors[] = "Gagal memeriksa NIK. Silakan coba lagi.";
        }


        // Cek Email (hanya jika tidak ada error sebelumnya)
        if (empty($errors)) {
            $stmt_check_email = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE email = ?");
            if ($stmt_check_email) {
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                $result_check_email = $stmt_check_email->get_result();
                if ($result_check_email->num_rows > 0) {
                    $errors[] = "Email sudah terdaftar.";
                }
                $stmt_check_email->close();
            } else {
                $errors[] = "Gagal memeriksa Email. Silakan coba lagi.";
            }
        }

        // Cek Username (hanya jika tidak ada error sebelumnya)
        if (empty($errors)) {
            $stmt_check_username = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE username = ?");
            if ($stmt_check_username) {
                $stmt_check_username->bind_param("s", $username);
                $stmt_check_username->execute();
                $result_check_username = $stmt_check_username->get_result();
                if ($result_check_username->num_rows > 0) {
                    $errors[] = "Username sudah digunakan.";
                }
                $stmt_check_username->close();
            } else {
                $errors[] = "Gagal memeriksa Username. Silakan coba lagi.";
            }
        }
    }

    // Jika semua validasi lolos dan data unik
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);

        // Set role default dan status akun
        $role = 'user';
        $status_akun = 'pending'; // Atau 'aktif' jika tidak perlu verifikasi admin

        // Masukkan data ke database menggunakan prepared statement
        $sql_insert = "INSERT INTO pengguna (nama_lengkap, nik, email, no_telepon, username, password, role, status_akun) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        if ($stmt_insert) {
            $stmt_insert->bind_param("ssssssss", $nama_lengkap, $nik, $email, $no_telepon, $username, $hashed_password, $role, $status_akun);
            if ($stmt_insert->execute()) {
                $success_message = "Registrasi berhasil! Akun Anda sedang menunggu persetujuan admin. Anda akan diarahkan ke halaman login dalam 5 detik.";
                // Kosongkan input form setelah sukses (opsional)
                $_POST = array(); // Ini akan mengosongkan nilai yang di-repopulate di form

                // Redirect ke halaman login setelah beberapa detik
                header("Refresh: 5; url=login.php");
            } else {
                $errors[] = "Registrasi gagal. Terjadi kesalahan pada sistem. Silakan coba lagi nanti. (Error: DB Execute - " . $stmt_insert->error . ")";
            }
            $stmt_insert->close();
        } else {
            $errors[] = "Registrasi gagal. Terjadi kesalahan pada sistem. Silakan coba lagi nanti. (Error: DB Prepare - " . $conn->error . ")";
        }
    }
    $conn->close(); // Tutup koneksi database
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/icon.png">
    <link rel="stylesheet" href="assets/css/auth.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="auth-page">
    <div class="auth-container register-container">
        <div class="auth-header">
            <img src="assets/images/icon text.png" alt="Logo DJP" class="auth-logo" onerror="this.onerror=null;this.src='';">
            <h1>Sistem Informasi Pajak Bangunan</h1>
            <h2>Buat Akun Baru</h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="auth-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="auth-success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($success_message)): // Hanya tampilkan form jika belum ada pesan sukses 
        ?>
            <form action="register.php" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" required value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="nik">NIK (Nomor Induk Kependudukan)</label>
                    <input type="text" id="nik" name="nik" required pattern="\d{16}" title="NIK harus terdiri dari 16 digit angka" value="<?php echo isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="no_telepon">Nomor Telepon</label>
                    <input type="tel" id="no_telepon" name="no_telepon" required pattern="[0-9\s\-\+\(\)]+" title="Format nomor telepon tidak valid" value="<?php echo isset($_POST['no_telepon']) ? htmlspecialchars($_POST['no_telepon']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required pattern="^[a-zA-Z0-9_]{5,20}$" title="Username 5-20 karakter (huruf, angka, underscore)" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password</label>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="button button-auth">Register</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="auth-footer">
            <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
            <p><a href="index.php">Kembali ke Beranda</a></p>
        </div>
    </div>
</body>

</html>