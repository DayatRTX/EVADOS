document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("sidebar");
  const mainContent = document.getElementById("mainContent");
  const sidebarToggle = document.getElementById("sidebarToggle");
  const body = document.body;

  // --- AWAL LOGIKA UNTUK MENCEGAH ANIMASI SIDEBAR SAAT LOAD ---
  // Tambahkan kelas untuk menonaktifkan transisi SEMENTARA di awal
  if (body) {
    // Pastikan body ada, relevan jika script ini di-load di halaman tanpa body utama
    body.classList.add("preload-no-transitions");
  }
  // --- AKHIR LOGIKA MENCEGAH ANIMASI SIDEBAR SAAT LOAD ---

  if (sidebar && mainContent && sidebarToggle) {
    let sidebarIsCollapsed;

    // Cek flag dari PHP untuk memaksa sidebar tertutup saat awal login dashboard
    // Variabel js_initial_sidebar_force_closed di-set oleh PHP di halaman HTML dashboard/penilaian
    if (
      typeof window.js_initial_sidebar_force_closed !== "undefined" &&
      window.js_initial_sidebar_force_closed === true
    ) {
      localStorage.setItem("sidebarCollapsed", "true"); // Paksa tertutup dan simpan state ini
      sidebarIsCollapsed = true;
    } else {
      // Untuk load halaman berikutnya atau jika tidak dipaksa, gunakan state dari localStorage
      sidebarIsCollapsed = localStorage.getItem("sidebarCollapsed") === "true";
    }

    // Terapkan state awal sidebar
    const icon = sidebarToggle.querySelector("i");
    if (sidebarIsCollapsed) {
      sidebar.classList.add("collapsed");
      mainContent.classList.add("sidebar-collapsed");
      if (icon) icon.className = "fas fa-bars"; // Ikon burger (tertutup)
    } else {
      sidebar.classList.remove("collapsed");
      mainContent.classList.remove("sidebar-collapsed");
      if (icon) icon.className = "fas fa-times"; // Ikon X (terbuka)
    }

    // Event listener untuk tombol toggle sidebar
    sidebarToggle.addEventListener("click", function () {
      sidebar.classList.toggle("collapsed");
      mainContent.classList.toggle("sidebar-collapsed");

      const currentIcon = sidebarToggle.querySelector("i");
      if (sidebar.classList.contains("collapsed")) {
        localStorage.setItem("sidebarCollapsed", "true");
        if (currentIcon) currentIcon.className = "fas fa-bars";
      } else {
        localStorage.setItem("sidebarCollapsed", "false");
        if (currentIcon) currentIcon.className = "fas fa-times";
      }
    });
  }

  // --- AWAL LOGIKA MENGHAPUS KELAS PENONAKTIF TRANSISI ---
  if (body) {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        body.classList.remove("preload-no-transitions");
      });
    });
  }
  // --- AKHIR LOGIKA MENGHAPUS KELAS PENONAKTIF TRANSISI ---

  // --- AWAL FITUR LIHAT PASSWORD DI login.php ---
  const passwordInput = document.getElementById("password");
  const togglePasswordButton = document.querySelector(".toggle-password");

  if (passwordInput && togglePasswordButton) {
    const eyeIcon = togglePasswordButton.querySelector("i");

    const showPass = function () {
      passwordInput.type = "text";
      if (eyeIcon) eyeIcon.classList.replace("fa-eye", "fa-eye-slash");
    };
    const hidePass = function () {
      passwordInput.type = "password";
      if (eyeIcon) eyeIcon.classList.replace("fa-eye-slash", "fa-eye");
    };

    togglePasswordButton.addEventListener("mousedown", showPass);
    togglePasswordButton.addEventListener("touchstart", function (e) {
      e.preventDefault();
      showPass();
    });

    togglePasswordButton.addEventListener("mouseup", hidePass);
    togglePasswordButton.addEventListener("mouseleave", function () {
      // Hanya sembunyikan jika tipenya masih text (artinya mouse masih ditahan lalu keluar)
      if (passwordInput.type === "text") {
        hidePass();
      }
    });
    togglePasswordButton.addEventListener("touchend", hidePass);
    togglePasswordButton.addEventListener("touchcancel", hidePass);
  }
  // --- AKHIR FITUR LIHAT PASSWORD ---

  // Efek hover halus pada tombol-tombol utama
  const buttons = document.querySelectorAll(
    ".btn-login, .btn-penilaian, .btn-kirim-penilaian"
  );
  buttons.forEach((button) => {
    button.addEventListener("mouseenter", () => {
      button.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
    });
    button.addEventListener("mouseleave", () => {
      button.style.boxShadow = "none";
    });
  });

  // Animasi pada input focus
  const inputs = document.querySelectorAll(
    ".input-group input, .input-group select, .komentar-section textarea"
  );
  inputs.forEach((input) => {
    input.addEventListener("focus", () => {
      input.style.borderColor = "var(--primary-color)"; // Pastikan variabel CSS ini ada
      input.style.boxShadow = "0 0 8px var(--tertiary-color)"; // Pastikan variabel CSS ini ada
    });
    input.addEventListener("blur", () => {
      input.style.borderColor = "var(--tertiary-color)"; // Pastikan variabel CSS ini ada
      input.style.boxShadow = "none";
    });
  });

  // Placeholder dinamis di halaman login (jika skrip ini juga dipakai di login.php)
  const kategoriUserSelect = document.getElementById("kategori_user");
  const identifierInput = document.getElementById("identifier");
  const identifierLabel = document.getElementById("identifier_label"); // Menggunakan ID untuk label

  if (kategoriUserSelect && identifierInput && identifierLabel) {
    kategoriUserSelect.addEventListener("change", function () {
      if (this.value === "mahasiswa") {
        identifierLabel.textContent = "Username";
        identifierInput.placeholder = "Masukkan Username Mahasiswa";
        identifierInput.type = "text";
      } else {
        // Dosen atau Kajur
        identifierLabel.textContent = "Email";
        identifierInput.placeholder = "Masukkan Email";
        identifierInput.type = "email";
      }
    });
    if (document.body.contains(kategoriUserSelect)) {
      // Hanya jalankan jika elemen ada di halaman
      kategoriUserSelect.dispatchEvent(new Event("change"));
    }
  }
}); // Akhir dari DOMContentLoaded

// Fungsi showContent (jika masih digunakan untuk navigasi konten di dashboard)
function showContent(contentId) {
  const allContentCards = document.querySelectorAll(
    ".main-content .content-card"
  );
  allContentCards.forEach((card) => {
    card.style.display = "none";
    card.style.animation = "";
  });

  const activeContentCard = document.getElementById(contentId + "-content");
  if (activeContentCard) {
    activeContentCard.style.display = "block";
    activeContentCard.style.animation = "slideUp 0.5s ease-out";
  }

  const menuItems = document.querySelectorAll(".sidebar-menu li a");
  menuItems.forEach((item) => {
    item.classList.remove("active");
    if (
      item.getAttribute("onclick") &&
      item.getAttribute("onclick").includes(`showContent('${contentId}')`)
    ) {
      item.classList.add("active");
    } else if (item.href && item.href.includes(`#${contentId}`)) {
      item.classList.add("active");
    }
  });
}
