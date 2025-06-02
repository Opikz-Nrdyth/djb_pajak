clear
cls


# Inisialisasi repo Git (jika belum)
git init

# Tambahkan semua perubahan
git add .

# Minta pesan commit dari user
$commitMessage = Read-Host "Masukkan pesan commit"

# Commit dengan pesan dari user
git commit -m "$commitMessage"

# Push ke remote
git push origin main
