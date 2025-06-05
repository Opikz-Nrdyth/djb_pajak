<?php
require_once("php/config.php");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Pajak Bangunan</title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/icon.png">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
</head>

<body>
    <div class="page-wrapper">
        <button class="sidebar-toggle-button" id="sidebar-toggle" aria-label="Toggle Sidebar" aria-expanded="false">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>

        <aside class="sidebar" id="landing-sidebar">
            <div class="sidebar-header">
                <img src="assets/images/icon.png" alt="Logo DJP" class="sidebar-logo" onerror="this.onerror=null;this.src='';">
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; <?php echo date("Y"); ?> InfoPajak</p>
            </div>
        </aside>

        <main class="main-content" id="main-content-area">
            <div class="container">
                <section class="welcome-section" data-aos="zoom-in">
                    <h1 class="main-title">SELAMAT DATANG DI<br>SISTEM INFORMASI PAJAK BANGUNAN</h1>
                    <p class="subtitle">Kenali dan pahami kewajiban pajak bangunan Anda dengan mudah bersama kami.</p>
                    <a href="https://online-pajak.com" target="_blank" rel="noopener noreferrer" class="button button-external">Kunjungi Online-Pajak.com</a>
                    <p class="button button-external">Isi email, NIK, Jenis pajak, dan Jumlah</p>
                </section>

                <section id="apa-itu-pajak" class="info-section" data-aos="fade-right">
                    <h2>Apa Itu Pajak?</h2>
                    <div class="info-content">
                        <img src="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/pajak.jpg" alt="Ilustrasi Pajak" class="info-image" onerror="this.onerror=null;this.src='https://placehold.co/300x200/cccccc/000000?text=Info+Pajak';">
                        <div class="text-content">
                            <p>Pajak adalah kontribusi wajib kepada negara yang terutang oleh orang pribadi atau badan yang bersifat memaksa berdasarkan Undang-Undang, dengan tidak mendapatkan imbalan secara langsung dan digunakan untuk keperluan negara bagi sebesar-besarnya kemakmuran rakyat. [cite: 2]</p>
                            <p>Pembayaran pajak merupakan perwujudan dari kewajiban kenegaraan dan peran serta Wajib Pajak untuk secara bersama-sama membiayai pengeluaran negara dan pembangunan nasional. [cite: 3] Dana dari pajak digunakan untuk membiayai berbagai fasilitas dan layanan publik. [cite: 4]</p>
                        </div>
                    </div>
                </section>

                <section id="tentang-djp" class="info-section info-section-alt" data-aos="fade-left">
                    <h2>Apa itu DJP (Direktorat Jenderal Pajak)?</h2>
                    <div class="info-content reverse">
                        <img src="<?php echo BASE_URL_ADMIN . $root_project; ?>assets/images/gedung-djb.jpeg" alt="Gedung DJP" class="info-image" onerror="this.onerror=null;this.src='https://placehold.co/300x200/cccccc/000000?text=Info+DJP';">
                        <div class="text-content">
                            <p>Direktorat Jenderal Pajak (DJP) adalah salah satu direktorat jenderal di bawah Kementerian Keuangan Republik Indonesia yang mempunyai tugas merumuskan serta melaksanakan kebijakan dan standardisasi teknis di bidang perpajakan. [cite: 5]</p>
                            <p>DJP memiliki tanggung jawab untuk mengadministrasikan penerimaan negara dari sektor perpajakan, yang merupakan sumber utama pendapatan negara. [cite: 6] Tugas utama DJP meliputi penyuluhan, pelayanan, pengawasan, dan penegakan hukum. [cite: 7]</p>
                        </div>
                    </div>
                </section>
                <section id="langkah-pengisian" class="steps-highlight-section" data-aos="fade-up">
                    <h2>Langkah Mudah Persiapan Data Anda</h2>
                    <div class="steps-container">
                        <div class="step-card">
                            <span class="step-icon">📧</span>
                            <h3>Data Email</h3>
                            <p>Siapkan alamat email aktif Anda.</p>
                        </div>
                        <div class="step-card">
                            <span class="step-icon">💳</span>
                            <h3>NIK</h3>
                            <p>Nomor Induk Kependudukan.</p>
                        </div>
                        <div class="step-card">
                            <span class="step-icon">📄</span>
                            <h3>Jenis Pajak</h3>
                            <p>Identifikasi jenis pajak bangunan Anda.</p>
                        </div>
                        <div class="step-card">
                            <span class="step-icon">💰</span>
                            <h3>Perkiraan Jumlah</h3>
                            <p>Estimasi awal jika ada.</p>
                        </div>
                    </div>
                    <p class="steps-note">Lengkapi data ini saat Anda login dan mengisi formulir untuk pengecekan pajak.</p>
                </section>
            </div>
        </main>
    </div>

    <script>
        AOS.init();
    </script>
    <script src="assets/js/script_landing.js?v=<?php echo time(); ?>"></script>
</body>

</html>