document.addEventListener("DOMContentLoaded", function () {
     // ========== SMOOTH SCROLL ==========
     document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
          anchor.addEventListener("click", function (e) {
               e.preventDefault();
               const target = document.querySelector(this.getAttribute("href"));
               if (target) {
                    target.scrollIntoView({
                         behavior: "smooth",
                         block: "start",
                    });
               }
               // Fechar menu mobile se estiver aberto
               const mobileMenu = document.getElementById("mobileMenu");
               const mobileMenuOverlay = document.getElementById("mobileMenuOverlay");
               if (mobileMenu && mobileMenu.classList.contains("active")) {
                    mobileMenu.classList.remove("active");
                    mobileMenuOverlay.classList.remove("active");
               }
          });
     });

     // ========== MOBILE MENU ==========
     const mobileMenuToggle = document.getElementById("mobileMenuToggle");
     const mobileMenu = document.getElementById("mobileMenu");
     const mobileMenuClose = document.getElementById("mobileMenuClose");
     const mobileMenuOverlay = document.getElementById("mobileMenuOverlay");

     if (mobileMenuToggle && mobileMenu) {
          mobileMenuToggle.addEventListener("click", () => {
               mobileMenu.classList.add("active");
               mobileMenuOverlay.classList.add("active");
          });
     }

     if (mobileMenuClose && mobileMenu) {
          mobileMenuClose.addEventListener("click", () => {
               mobileMenu.classList.remove("active");
               mobileMenuOverlay.classList.remove("active");
          });
     }

     if (mobileMenuOverlay) {
          mobileMenuOverlay.addEventListener("click", () => {
               mobileMenu.classList.remove("active");
               mobileMenuOverlay.classList.remove("active");
          });
     }

     // ========== HEADER SCROLL EFFECT ==========
     window.addEventListener("scroll", () => {
          const header = document.querySelector(".header");
          if (header) {
               if (window.scrollY > 100) {
                    header.style.background = "rgba(13, 13, 13, 0.95)";
                    header.style.boxShadow = "none";
               }
          }
     });

     // ========== INTERSECTION OBSERVER PARA ANIMAÇÕES ==========
     const observerOptions = {
          threshold: 0.1,
          rootMargin: "0px 0px -50px 0px",
     };

     const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
               if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = "running";
               }
          });
     }, observerOptions);

     document.querySelectorAll(".fade-in").forEach((el) => {
          observer.observe(el);
     });

     // ========== CARROSSEL ==========
     const track = document.getElementById("carouselTrack");
     const slides = Array.from(track.children);
     const prevBtn = document.getElementById("prevBtn");
     const nextBtn = document.getElementById("nextBtn");
     const indicators = document.querySelectorAll(".indicator");

     let currentIndex = 0;
     let autoplayInterval;

     function updateCarousel(index) {
          currentIndex = index;
          const offset = -100 * index;
          track.style.transform = `translateX(${offset}%)`;

          slides.forEach((slide, i) => {
               slide.classList.toggle("active", i === index);
          });

          indicators.forEach((indicator, i) => {
               indicator.classList.toggle("active", i === index);
          });
     }

     function nextSlide() {
          const newIndex = (currentIndex + 1) % slides.length;
          updateCarousel(newIndex);
     }

     function prevSlide() {
          const newIndex = (currentIndex - 1 + slides.length) % slides.length;
          updateCarousel(newIndex);
     }

     function startAutoplay() {
          autoplayInterval = setInterval(nextSlide, 5000);
     }

     function stopAutoplay() {
          clearInterval(autoplayInterval);
     }

     nextBtn.addEventListener("click", () => {
          nextSlide();
          stopAutoplay();
          startAutoplay();
     });

     prevBtn.addEventListener("click", () => {
          prevSlide();
          stopAutoplay();
          startAutoplay();
     });

     indicators.forEach((indicator) => {
          indicator.addEventListener("click", () => {
               const index = parseInt(indicator.dataset.index);
               updateCarousel(index);
               stopAutoplay();
               startAutoplay();
          });
     });

     // Suporte para swipe em mobile
     let touchStartX = 0;
     let touchEndX = 0;

     track.addEventListener("touchstart", (e) => {
          touchStartX = e.changedTouches[0].screenX;
     });

     track.addEventListener("touchend", (e) => {
          touchEndX = e.changedTouches[0].screenX;
          handleSwipe();
     });

     function handleSwipe() {
          if (touchStartX - touchEndX > 50) {
               nextSlide();
               stopAutoplay();
               startAutoplay();
          }
          if (touchEndX - touchStartX > 50) {
               prevSlide();
               stopAutoplay();
               startAutoplay();
          }
     }

     // Pausar autoplay quando o mouse está sobre o carrossel
     track.addEventListener("mouseenter", stopAutoplay);
     track.addEventListener("mouseleave", startAutoplay);

     // Suporte para navegação por teclado
     document.addEventListener("keydown", (e) => {
          if (e.key === "ArrowLeft") {
               prevSlide();
               stopAutoplay();
               startAutoplay();
          }
          if (e.key === "ArrowRight") {
               nextSlide();
               stopAutoplay();
               startAutoplay();
          }
     });

     // Iniciar autoplay
     startAutoplay();

     // ========== OTIMIZAÇÕES PARA MOBILE ==========
     if (window.innerWidth <= 768) {
          // Reduzir animações em dispositivos móveis
          document.querySelectorAll(".floating-card").forEach((card) => {
               card.style.animation = "none";
          });
     }

     // Ajustar layout em mudanças de orientação
     window.addEventListener("orientationchange", () => {
          setTimeout(() => {
               const heroVisual = document.querySelector(".hero-visual");
               if (window.innerWidth <= 768 && heroVisual) {
                    heroVisual.style.height = "180px";
               }
          }, 100);
     });
});