document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("sidebar");
  const mainContent = document.getElementById("mainContent");
  const sidebarToggleBtn = document.getElementById("sidebarToggleBtn");
  const mobileNavToggle = document.getElementById("mobileNavToggle");
  const mobileNavOverlay = document.getElementById("mobileNavOverlay");
  const submenuToggles = document.querySelectorAll(
    ".sidebar-nav .has-submenu > a"
  );
  // const root = document.documentElement; // Tidak digunakan saat ini, bisa dihapus jika tidak ada rencana penggunaan

  const TABLET_BREAKPOINT = 992;
  const MOBILE_BREAKPOINT = 768;

  // Fungsi untuk toggle sidebar minimize (Desktop & Tablet)
  function toggleSidebarMinimize() {
    if (!sidebar) return; // Guard clause
    sidebar.classList.toggle("sidebar-minimized");
    const isMinimized = sidebar.classList.contains("sidebar-minimized");

    // Update ikon tombol minimize
    if (sidebarToggleBtn && sidebarToggleBtn.querySelector("i")) {
      sidebarToggleBtn.querySelector("i").className = isMinimized
        ? "fas fa-chevron-right"
        : "fas fa-chevron-left";
    }

    // Simpan preferensi ke localStorage
    localStorage.setItem("sidebarMinimized", isMinimized);

    // Tutup submenu yang terbuka jika sidebar diminimize
    if (isMinimized) {
      document
        .querySelectorAll(".sidebar-nav .has-submenu.open")
        .forEach((openSubmenu) => {
          openSubmenu.classList.remove("open");
        });
    }
    // Pengaturan padding mainContent sekarang sepenuhnya ditangani oleh CSS
    // berdasarkan class '.sidebar-minimized' pada elemen '.sidebar'
  }

  // Fungsi untuk toggle mobile navigation (Off-canvas)
  function toggleMobileNav() {
    if (!sidebar || !mobileNavToggle || !mobileNavToggle.querySelector("i"))
      return; // Guard clauses

    sidebar.classList.toggle("sidebar-mobile-active");
    document.body.classList.toggle("mobile-nav-active"); // Untuk overlay dan potentially no-scroll

    mobileNavToggle.querySelector("i").className = sidebar.classList.contains(
      "sidebar-mobile-active"
    )
      ? "fas fa-times"
      : "fas fa-bars";
  }

  // Event listener untuk tombol minimize sidebar
  if (sidebarToggleBtn) {
    sidebarToggleBtn.addEventListener("click", toggleSidebarMinimize);
  }

  // Event listener untuk tombol hamburger mobile
  if (mobileNavToggle) {
    mobileNavToggle.addEventListener("click", toggleMobileNav);
  }

  // Event listener untuk overlay (menutup mobile nav saat diklik)
  if (mobileNavOverlay) {
    mobileNavOverlay.addEventListener("click", toggleMobileNav);
  }

  // Event listener untuk submenu toggles
  submenuToggles.forEach((toggle) => {
    toggle.addEventListener("click", function (event) {
      event.preventDefault();
      if (!sidebar) return;

      // Jangan buka submenu jika sidebar minimized di desktop/tablet
      if (
        sidebar.classList.contains("sidebar-minimized") &&
        window.innerWidth > MOBILE_BREAKPOINT
      ) {
        // Pengguna harus meng-expand sidebar terlebih dahulu
        return;
      }
      // Toggle class 'open' pada elemen <li> parent dari link <a> yang diklik
      const parentLi = this.parentElement;
      if (parentLi && parentLi.classList.contains("has-submenu")) {
        parentLi.classList.toggle("open");
      }
    });
  });

  // Cek kondisi awal saat halaman dimuat
  function checkInitialSidebarState() {
    if (!sidebar || !sidebarToggleBtn || !mobileNavToggle) return; // Guard clauses
    if (
      !sidebarToggleBtn.querySelector("i") ||
      !mobileNavToggle.querySelector("i")
    )
      return;

    const screenWidth = window.innerWidth;

    // Mobile State
    if (screenWidth <= MOBILE_BREAKPOINT) {
      sidebar.classList.remove("sidebar-minimized"); // Hapus state minimized jika ada dari mode tablet/desktop
      // Pastikan sidebar mobile tertutup secara default kecuali ada state lain yang menyuruhnya terbuka
      if (sidebar.classList.contains("sidebar-mobile-active")) {
        mobileNavToggle.querySelector("i").className = "fas fa-times";
        document.body.classList.add("mobile-nav-active");
      } else {
        sidebar.classList.remove("sidebar-mobile-active");
        document.body.classList.remove("mobile-nav-active");
        mobileNavToggle.querySelector("i").className = "fas fa-bars";
      }
    }
    // Tablet State - Auto Minimize
    else if (screenWidth <= TABLET_BREAKPOINT) {
      sidebar.classList.add("sidebar-minimized"); // Selalu minimize di tablet
      sidebarToggleBtn.querySelector("i").className = "fas fa-chevron-right";
      // localStorage.setItem("sidebarMinimized", true); // Bisa juga di-set, tapi tablet akan selalu minimize on load
      // Pastikan mobile state tidak aktif
      sidebar.classList.remove("sidebar-mobile-active");
      document.body.classList.remove("mobile-nav-active");
    }
    // Desktop State - Cek LocalStorage
    else {
      const storedMinimized =
        localStorage.getItem("sidebarMinimized") === "true";
      if (storedMinimized) {
        sidebar.classList.add("sidebar-minimized");
        sidebarToggleBtn.querySelector("i").className = "fas fa-chevron-right";
      } else {
        sidebar.classList.remove("sidebar-minimized");
        sidebarToggleBtn.querySelector("i").className = "fas fa-chevron-left";
      }
      // Pastikan mobile state tidak aktif
      sidebar.classList.remove("sidebar-mobile-active");
      document.body.classList.remove("mobile-nav-active");
    }
  }

  checkInitialSidebarState(); // Panggil saat load
  window.addEventListener("resize", checkInitialSidebarState); // Panggil saat resize

  // Set active link dan open parent submenu
  const activeSubLink = document.querySelector(
    ".sidebar-nav .submenu a.active"
  );
  if (activeSubLink && sidebar) {
    // Tambah pengecekan sidebar
    const parentLi = activeSubLink.closest(".has-submenu");
    // Hanya buka jika tidak minimized dan bukan mode mobile (karena submenu di mobile mungkin berbeda)
    if (
      parentLi &&
      !sidebar.classList.contains("sidebar-minimized") &&
      window.innerWidth > MOBILE_BREAKPOINT
    ) {
      parentLi.classList.add("open");
    }
  }
});
