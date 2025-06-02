// assets/js/admin_script.js
document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("admin-main-sidebar");
  const sidebarToggleButton = document.getElementById("admin-sidebar-toggle");
  const mainContent = document.getElementById("admin-main-content-area");
  const body = document.body;

  // Fungsi untuk toggle sidebar di mobile (slide in/out)
  if (sidebarToggleButton && sidebar) {
    sidebarToggleButton.addEventListener("click", function () {
      sidebar.classList.toggle("open"); // Untuk tampilan mobile slide in/out
      // body.classList.toggle('admin-sidebar-collapsed'); // Untuk tampilan desktop diciutkan

      const isExpanded =
        sidebar.classList.contains("open") ||
        !body.classList.contains("admin-sidebar-collapsed");
      this.setAttribute("aria-expanded", isExpanded.toString());

      // Animasi ikon hamburger
      const iconBars = this.querySelectorAll(".icon-bar");
      if (sidebar.classList.contains("open")) {
        // Cek class 'open' untuk mobile
        iconBars[0].style.transform = "rotate(-45deg) translate(-4px, 5px)";
        iconBars[1].style.opacity = "0";
        iconBars[2].style.transform = "rotate(45deg) translate(-4px, -5px)";
      } else {
        iconBars[0].style.transform = "none";
        iconBars[1].style.opacity = "1";
        iconBars[2].style.transform = "none";
      }
    });
  }

  // Menutup sidebar mobile saat area konten di klik
  if (mainContent && sidebar && window.innerWidth <= 992) {
    // Batas breakpoint tablet
    mainContent.addEventListener("click", function () {
      if (sidebar.classList.contains("open")) {
        sidebar.classList.remove("open");
        if (sidebarToggleButton) {
          sidebarToggleButton.setAttribute("aria-expanded", "false");
          const iconBars = sidebarToggleButton.querySelectorAll(".icon-bar");
          iconBars[0].style.transform = "none";
          iconBars[1].style.opacity = "1";
          iconBars[2].style.transform = "none";
        }
      }
    });
  }

  // Fungsi untuk toggle submenu
  const submenuToggles = document.querySelectorAll(
    ".admin-sidebar-nav .submenu-toggle"
  );
  submenuToggles.forEach((toggle) => {
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      const parentLi = this.closest("li.has-submenu");
      if (parentLi) {
        parentLi.classList.toggle("open");
      }
    });
  });

  // Log untuk memastikan script dimuat
  console.log("Admin script loaded.");

  // Contoh: Menandai menu aktif berdasarkan URL (sederhana)
  // Ini bisa lebih kompleks jika path URL lebih rumit
  const currentPath = window.location.pathname;
  const navLinks = document.querySelectorAll(".admin-sidebar-nav a");
  navLinks.forEach((link) => {
    // Mengambil bagian akhir dari href link dan URL untuk perbandingan yang lebih fleksibel
    const linkPathEnd = link.getAttribute("href").split("/").pop();
    const currentPathEnd = currentPath.split("/").pop();

    if (
      linkPathEnd === currentPathEnd &&
      linkPathEnd !== "" &&
      linkPathEnd !== "#"
    ) {
      link.closest("li").classList.add("active");

      // Jika item aktif ada di dalam submenu, buka submenu parentnya
      let parent = link.closest("ul.admin-submenu");
      while (parent) {
        const parentLiWithSubmenu = parent.closest("li.has-submenu");
        if (parentLiWithSubmenu) {
          parentLiWithSubmenu.classList.add("open", "active"); // Tambah active juga ke parent
        }
        parent = parentLiWithSubmenu
          ? parentLiWithSubmenu.parentElement.closest("ul.admin-submenu")
          : null;
      }
      // Juga buka parent utama jika ada
      const mainParentLi = link.closest("li.has-submenu:not(.open)");
      if (
        mainParentLi &&
        mainParentLi.contains(link.closest("ul.admin-submenu"))
      ) {
        // Tidak perlu, sudah dihandle di loop while
      }
    }
  });
  // Jika halaman dashboard, pastikan menu Dashboard aktif
  if (currentPath.includes("dashboard_admin.php")) {
    const dashboardLink = document.querySelector(
      '.admin-sidebar-nav a[href*="dashboard_admin.php"]'
    );
    if (dashboardLink) {
      dashboardLink.closest("li").classList.add("active");
    }
  }
});
