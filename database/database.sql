-- database.sql

-- Membuat database jika belum ada
CREATE DATABASE IF NOT EXISTS `db_pajak_bangunan` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_pajak_bangunan`;

-- Tabel untuk pengguna (termasuk admin dan user biasa)
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id_pengguna` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `nik` VARCHAR(16) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `no_telepon` VARCHAR(15) DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL, -- Akan menyimpan hash password
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `tanggal_registrasi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status_akun` ENUM('aktif', 'nonaktif', 'pending') DEFAULT 'aktif' -- Status default bisa diubah menjadi 'pending' jika perlu verifikasi admin
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel untuk data DJP pengguna (jika diperlukan data spesifik DJP per user)
CREATE TABLE IF NOT EXISTS `data_djp_user` (
    `id_data_djp` INT AUTO_INCREMENT PRIMARY KEY,
    `id_pengguna` INT NOT NULL,
    `npwp` VARCHAR(20) DEFAULT NULL UNIQUE, -- NPWP sebaiknya unik jika diisi
    `nama_pemilik_bangunan` VARCHAR(100) DEFAULT NULL,
    `alamat_objek_pajak` TEXT DEFAULT NULL,
    `luas_bangunan` DECIMAL(10,2) DEFAULT NULL, -- Dalam meter persegi
    `luas_tanah` DECIMAL(10,2) DEFAULT NULL, -- Dalam meter persegi
    `jenis_bangunan` VARCHAR(100) DEFAULT NULL,
    `data_tambahan` TEXT DEFAULT NULL, -- Untuk data lain yang relevan
    `tanggal_input` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `tanggal_update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna`(`id_pengguna`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Tabel untuk perhitungan pajak (dilakukan oleh admin)
CREATE TABLE IF NOT EXISTS `perhitungan_pajak` (
    `id_perhitungan` INT AUTO_INCREMENT PRIMARY KEY,
    `id_data_djp` INT NOT NULL, -- Merujuk ke data DJP user yang dihitung
    `id_admin_pereview` INT NOT NULL, -- Admin yang melakukan/mereview perhitungan
    `tanggal_perhitungan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `periode_pajak_tahun` YEAR NOT NULL,
    `njop_bangunan_per_meter` DECIMAL(15,2) DEFAULT 0.00,
    `njop_tanah_per_meter` DECIMAL(15,2) DEFAULT 0.00,
    -- Kolom generated untuk total NJOP bangunan dan tanah
    -- Perlu dipastikan bahwa data_djp_user memiliki luas_bangunan dan luas_tanah yang valid saat perhitungan
    -- Jika luas_bangunan atau luas_tanah NULL, hasilnya akan NULL. Perlu penanganan di aplikasi.
    `total_njop_bangunan` DECIMAL(18,2) DEFAULT 0.00, -- Akan dihitung oleh aplikasi
    `total_njop_tanah` DECIMAL(18,2) DEFAULT 0.00, -- Akan dihitung oleh aplikasi
    `njop_total_objek_pajak` DECIMAL(20,2) DEFAULT 0.00, -- Akan dihitung oleh aplikasi (total_njop_bangunan + total_njop_tanah)
    `njoptkp` DECIMAL(15,2) DEFAULT 0.00, -- NJOP Tidak Kena Pajak, bisa diatur oleh admin atau sistem
    `njkp` DECIMAL(20,2) DEFAULT 0.00, -- NJOP Kena Pajak (njop_total_objek_pajak - njoptkp), dihitung aplikasi
    `persentase_pbb` DECIMAL(5,4) DEFAULT 0.0050, -- Contoh 0.5%, bisa diatur
    `jumlah_pbb_terutang` DECIMAL(18,2) DEFAULT 0.00, -- (njkp * persentase_pbb), dihitung aplikasi
    `catatan_admin` TEXT DEFAULT NULL,
    `status_verifikasi_data_user` ENUM('lengkap', 'belum_lengkap', 'perlu_revisi', 'diverifikasi') DEFAULT 'belum_lengkap',
    `status_perhitungan` ENUM('draft', 'final', 'dikirim_ke_user') DEFAULT 'draft',
    FOREIGN KEY (`id_data_djp`) REFERENCES `data_djp_user`(`id_data_djp`) ON DELETE CASCADE,
    FOREIGN KEY (`id_admin_pereview`) REFERENCES `pengguna`(`id_pengguna`) -- Admin juga ada di tabel pengguna
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menghapus kolom generated dan menggantinya dengan kolom biasa yang diisi aplikasi
-- ALTER TABLE `perhitungan_pajak`
-- DROP COLUMN `total_njop_bangunan`,
-- DROP COLUMN `total_njop_tanah`,
-- DROP COLUMN `njop_total_objek_pajak`,
-- DROP COLUMN `njkp`,
-- DROP COLUMN `jumlah_pbb_terutang`;

-- ALTER TABLE `perhitungan_pajak`
-- ADD COLUMN `total_njop_bangunan` DECIMAL(18,2) DEFAULT 0.00 AFTER `njop_tanah_per_meter`,
-- ADD COLUMN `total_njop_tanah` DECIMAL(18,2) DEFAULT 0.00 AFTER `total_njop_bangunan`,
-- ADD COLUMN `njop_total_objek_pajak` DECIMAL(20,2) DEFAULT 0.00 AFTER `total_njop_tanah`,
-- ADD COLUMN `njkp` DECIMAL(20,2) DEFAULT 0.00 AFTER `njoptkp`,
-- ADD COLUMN `jumlah_pbb_terutang` DECIMAL(18,2) DEFAULT 0.00 AFTER `persentase_pbb`;


-- Tabel untuk Laporan Pajak (dibuat oleh Admin)
CREATE TABLE IF NOT EXISTS `laporan_pajak` (
 `id_laporan` INT AUTO_INCREMENT PRIMARY KEY,
 `judul_laporan` VARCHAR(255) NOT NULL,
 `jenis_laporan` ENUM('ringkasan_harian', 'ringkasan_mingguan', 'ringkasan_bulanan', 'rekapitulasi_periode') NOT NULL,
 `periode_mulai` DATE DEFAULT NULL, -- Untuk rekapitulasi periode
 `periode_akhir` DATE DEFAULT NULL, -- Untuk rekapitulasi periode
 `id_admin_pembuat` INT NOT NULL,
 `tanggal_pembuatan_laporan` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 `isi_laporan_json` JSON DEFAULT NULL, -- Menyimpan data laporan dalam format JSON (misal, daftar perhitungan, total, dll.)
 `catatan_laporan` TEXT DEFAULT NULL,
 FOREIGN KEY (`id_admin_pembuat`) REFERENCES `pengguna`(`id_pengguna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Indeks untuk optimasi query
CREATE INDEX idx_pengguna_username ON `pengguna`(`username`);
CREATE INDEX idx_pengguna_email ON `pengguna`(`email`);
CREATE INDEX idx_data_djp_user_pengguna ON `data_djp_user`(`id_pengguna`);
CREATE INDEX idx_perhitungan_pajak_data_djp ON `perhitungan_pajak`(`id_data_djp`);
CREATE INDEX idx_perhitungan_pajak_admin ON `perhitungan_pajak`(`id_admin_pereview`);
CREATE INDEX idx_perhitungan_pajak_tanggal ON `perhitungan_pajak`(`tanggal_perhitungan`);
CREATE INDEX idx_laporan_pajak_admin ON `laporan_pajak`(`id_admin_pembuat`);
CREATE INDEX idx_laporan_pajak_jenis ON `laporan_pajak`(`jenis_laporan`);

