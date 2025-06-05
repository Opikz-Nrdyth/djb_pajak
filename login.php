<?php
// login.php
session_start(); // Mulai session di awal file

// Jika pengguna sudah login, redirect ke dashboard yang sesuai
if (isset($_SESSION['id_pengguna'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/"); // Sesuaikan path jika perlu
        exit();
    } else {
        header("Location: user/"); // Sesuaikan path jika perlu
        exit();
    }
}

require_once 'php/db_connect.php'; // Include file koneksi database
require_once("php/config.php");

$page_title = "Login - Sistem Informasi Pajak Bangunan";
$errors = []; // Untuk menampung pesan error

// Logika untuk proses login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil dan sanitasi data dari form
    $username_or_email = isset($_POST['username_or_email']) ? $conn->real_escape_string(trim($_POST['username_or_email'])) : '';
    $password_input = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validasi dasar
    if (empty($username_or_email)) {
        $errors[] = "Username atau Email tidak boleh kosong.";
    }
    if (empty($password_input)) {
        $errors[] = "Password tidak boleh kosong.";
    }

    if (empty($errors)) {
        // Cek ke database apakah username atau email ada
        $sql = "SELECT id_pengguna, username, password, role, status_akun FROM pengguna WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ss", $username_or_email, $username_or_email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password_input, $user['password'])) {
                    if ($user['status_akun'] === 'aktif') {
                        $_SESSION['id_pengguna'] = $user['id_pengguna'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        // Anda bisa menambahkan data lain ke session jika perlu, misal nama_lengkap $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

                        if ($user['role'] === 'admin') {
                            header("Location: admin/");
                            exit();
                        } else {
                            header("Location: user/");
                            exit();
                        }
                    } else if ($user['status_akun'] === 'nonaktif') {
                        $errors[] = "Akun Anda telah dinonaktifkan. Silakan hubungi administrator.";
                    } else if ($user['status_akun'] === 'pending') {
                        $errors[] = "Akun Anda masih dalam status pending. Silakan tunggu persetujuan administrator atau hubungi kami.";
                    } else {
                        $errors[] = "Status akun tidak diketahui. Silakan hubungi administrator.";
                    }
                } else {
                    $errors[] = "Username/Email atau Password salah.";
                }
            } else {
                $errors[] = "Username/Email atau Password salah.";
            }
            $stmt->close();
        } else {
            $errors[] = "Terjadi kesalahan pada sistem. Silakan coba lagi nanti. (Error: DB Prepare - " . $conn->error . ")";
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/icon.png">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/css/auth.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <img src="assets/images/icon text.png" alt="Logo DJP" class="auth-logo" onerror="this.onerror=null;this.src='';">
            <h1>Sistem Informasi Pajak Bangunan</h1>
            <h2>Login Akun</h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="auth-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="auth-form">
            <div class="form-group">
                <label for="username_or_email">Username/Gmail</label>
                <input type="text" id="username_or_email" name="username_or_email" required value="<?php echo isset($_POST['username_or_email']) ? htmlspecialchars($_POST['username_or_email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <button type="submit" class="button button-auth">Login</button>
            </div>
        </form>
        <div class="auth-footer">
            <p>Belum punya akun? <a href="register.php">Daftar Akun</a></p>
            <p><a href="index.php">Kembali ke Beranda</a></p>
        </div>
    </div>
</body>

</html>