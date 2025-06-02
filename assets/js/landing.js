// assets/js/script_landing.js
document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("landing-sidebar");
  const sidebarToggleButton = document.getElementById("sidebar-toggle");
  const mainContent = document.getElementById("main-content-area"); // Untuk menutup sidebar saat klik konten

  if (sidebarToggleButton && sidebar) {
    sidebarToggleButton.addEventListener("click", function () {
      sidebar.classList.toggle("open");
      const isExpanded = sidebar.classList.contains("open");
      this.setAttribute("aria-expanded", isExpanded.toString());

      // Animasi ikon hamburger (opsional, bisa disesuaikan)
      const iconBars = this.querySelectorAll(".icon-bar");
      if (isExpanded) {
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

  // Opsional: Menutup sidebar saat area konten di klik (khusus mobile)
  if (mainContent && sidebar && window.innerWidth <= 768) {
    mainContent.addEventListener("click", function () {
      if (sidebar.classList.contains("open")) {
        sidebar.classList.remove("open");
        sidebarToggleButton.setAttribute("aria-expanded", "false");
        const iconBars = sidebarToggleButton.querySelectorAll(".icon-bar");
        iconBars[0].style.transform = "none";
        iconBars[1].style.opacity = "1";
        iconBars[2].style.transform = "none";
      }
    });
  }

  // Smooth scroll untuk anchor links jika ada di masa depan (belum ada di layout ini)
  // const anchorLinks = document.querySelectorAll('a[href^="#main-content-area"]'); // Contoh jika ada link ke konten
  // anchorLinks.forEach(anchor => {
  //     anchor.addEventListener('click', function (e) {
  //         e.preventDefault();
  //         // ... logika smooth scroll ...
  //         // Tutup sidebar jika terbuka di mobile
  //         if (sidebar && sidebar.classList.contains('open') && window.innerWidth <= 768) {
  //             sidebar.classList.remove('open');
  //             sidebarToggleButton.setAttribute('aria-expanded', 'false');
  //             // Reset ikon hamburger
  //         }
  //     });
  // });

  console.log("Landing page script (sidebar version) loaded.");
});
// assets/js/script_landing.js
document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("landing-sidebar");
  const sidebarToggleButton = document.getElementById("sidebar-toggle");
  const mainContent = document.getElementById("main-content-area"); // Untuk menutup sidebar saat klik konten

  if (sidebarToggleButton && sidebar) {
    sidebarToggleButton.addEventListener("click", function () {
      sidebar.classList.toggle("open");
      const isExpanded = sidebar.classList.contains("open");
      this.setAttribute("aria-expanded", isExpanded.toString());

      // Animasi ikon hamburger (opsional, bisa disesuaikan)
      const iconBars = this.querySelectorAll(".icon-bar");
      if (isExpanded) {
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

  // Opsional: Menutup sidebar saat area konten di klik (khusus mobile)
  if (mainContent && sidebar && window.innerWidth <= 768) {
    mainContent.addEventListener("click", function () {
      if (sidebar.classList.contains("open")) {
        sidebar.classList.remove("open");
        sidebarToggleButton.setAttribute("aria-expanded", "false");
        const iconBars = sidebarToggleButton.querySelectorAll(".icon-bar");
        iconBars[0].style.transform = "none";
        iconBars[1].style.opacity = "1";
        iconBars[2].style.transform = "none";
      }
    });
  }

  // Smooth scroll untuk anchor links jika ada di masa depan (belum ada di layout ini)
  // const anchorLinks = document.querySelectorAll('a[href^="#main-content-area"]'); // Contoh jika ada link ke konten
  // anchorLinks.forEach(anchor => {
  //     anchor.addEventListener('click', function (e) {
  //         e.preventDefault();
  //         // ... logika smooth scroll ...
  //         // Tutup sidebar jika terbuka di mobile
  //         if (sidebar && sidebar.classList.contains('open') && window.innerWidth <= 768) {
  //             sidebar.classList.remove('open');
  //             sidebarToggleButton.setAttribute('aria-expanded', 'false');
  //             // Reset ikon hamburger
  //         }
  //     });
  // });

  console.log("Landing page script (sidebar version) loaded.");
});
