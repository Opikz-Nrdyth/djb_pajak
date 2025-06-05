// assets/js/user_script.js
// Skrip untuk fungsionalitas layout User Dashboard

document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("user-main-sidebar"); // ID sidebar user
  const sidebarToggleButton = document.getElementById("user-sidebar-toggle"); // ID tombol toggle
  const mainContent = document.getElementById("user-main-content-area"); // ID area konten utama user

  // Fungsi untuk toggle sidebar di mobile (slide in/out)
  if (sidebarToggleButton && sidebar) {
    sidebarToggleButton.addEventListener("click", function () {
      sidebar.classList.toggle("open");
      const isExpanded = sidebar.classList.contains("open");
      this.setAttribute("aria-expanded", isExpanded.toString());

      // Animasi ikon hamburger (opsional, bisa disesuaikan atau menggunakan class CSS)
      const iconBars = this.querySelectorAll(".icon-bar");
      if (sidebar.classList.contains("open")) {
        iconBars[0].style.transform = "rotate(-45deg) translate(-5px, 5px)";
        iconBars[1].style.opacity = "0";
        iconBars[2].style.transform = "rotate(45deg) translate(-4px, -5px)";
      } else {
        iconBars[0].style.transform = "none";
        iconBars[1].style.opacity = "1";
        iconBars[2].style.transform = "none";
      }
    });
  }

  // Opsional: Menutup sidebar mobile saat area konten di klik
  if (mainContent && sidebar && window.innerWidth <= 992) {
    // Sesuai breakpoint di CSS
    mainContent.addEventListener("click", function (event) {
      // Pastikan klik bukan pada tombol toggle itu sendiri atau di dalam sidebar
      if (
        sidebarToggleButton &&
        !sidebarToggleButton.contains(event.target) &&
        !sidebar.contains(event.target)
      ) {
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
      }
    });
  }

  // Fungsi untuk toggle submenu di sidebar user (jika ada)
  const userSubmenuToggles = document.querySelectorAll(
    ".user-sidebar-nav .submenu-toggle"
  ); // Gunakan class yang sesuai
  userSubmenuToggles.forEach((toggle) => {
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      const parentLi = this.closest("li.has-submenu"); // Gunakan class yang sesuai
      if (parentLi) {
        parentLi.classList.toggle("open");
        // Rotasi panah jika menggunakan Font Awesome atau SVG terpisah
        const arrowIcon = this.querySelector(".submenu-arrow i");
        if (arrowIcon) {
          arrowIcon.style.transform = parentLi.classList.contains("open")
            ? "rotate(-180deg)"
            : "rotate(0deg)";
        }
      }
    });
  });

  // Log untuk memastikan script dimuat
  console.log("User layout script loaded.");
});
