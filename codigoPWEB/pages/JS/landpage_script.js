document.addEventListener('DOMContentLoaded', function () {
     const carouselTrack = document.getElementById('carouselTrack');
     const prevBtn = document.getElementById('prevBtn');
     const nextBtn = document.getElementById('nextBtn');
     const indicators = document.querySelectorAll('.indicator');
     const slides = document.querySelectorAll('.carousel-slide');
     let currentIndex = 0;
     const totalSlides = slides.length;

     function updateCarousel() {
          const offset = -currentIndex * 100;
          carouselTrack.style.transform = `translateX(${offset}%)`;

          slides.forEach((slide, index) => {
               slide.classList.toggle('active', index === currentIndex);
          });

          indicators.forEach((indicator, index) => {
               indicator.classList.toggle('active', index === currentIndex);
          });
     }

     function nextSlide() {
          currentIndex = (currentIndex + 1) % totalSlides;
          updateCarousel();
     }

     function prevSlide() {
          currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
          updateCarousel();
     }

     nextBtn.addEventListener('click', nextSlide);
     prevBtn.addEventListener('click', prevSlide);

     indicators.forEach((indicator, index) => {
          indicator.addEventListener('click', () => {
               currentIndex = index;
               updateCarousel();
          });
     });

     setInterval(nextSlide, 5000);

     const fadeElements = document.querySelectorAll('.fade-in');
     const observer = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
               if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
               }
          });
     }, { threshold: 0.1 });

     fadeElements.forEach(el => observer.observe(el));

     const mobileMenuToggle = document.getElementById('mobileMenuToggle');
     const mobileMenu = document.getElementById('mobileMenu');
     const mobileMenuClose = document.getElementById('mobileMenuClose');
     const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

     function openMobileMenu() {
          mobileMenu.classList.add('active');
          mobileMenuOverlay.classList.add('active');
          document.body.style.overflow = 'hidden';
     }

     function closeMobileMenu() {
          mobileMenu.classList.remove('active');
          mobileMenuOverlay.classList.remove('active');
          document.body.style.overflow = '';
     }

     mobileMenuToggle.addEventListener('click', openMobileMenu);
     mobileMenuClose.addEventListener('click', closeMobileMenu);
     mobileMenuOverlay.addEventListener('click', closeMobileMenu);

     const mobileNavLinks = document.querySelectorAll('.mobile-nav-links a');
     mobileNavLinks.forEach(link => {
          link.addEventListener('click', closeMobileMenu);
     });

     const qrButton = document.getElementById('qrButton');
     const qrPopupOverlay = document.getElementById('qrPopupOverlay');
     const qrPopupClose = document.getElementById('qrPopupClose');

     function openQrPopup() {
          qrPopupOverlay.classList.add('active');
          document.body.style.overflow = 'hidden';
     }

     function closeQrPopup() {
          qrPopupOverlay.classList.remove('active');
          document.body.style.overflow = '';
     }

     qrButton.addEventListener('click', openQrPopup);
     qrPopupClose.addEventListener('click', closeQrPopup);

     qrPopupOverlay.addEventListener('click', function (e) {
          if (e.target === qrPopupOverlay) {
               closeQrPopup();
          }
     });

     document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') {
               closeQrPopup();
               closeMobileMenu();
          }
     });

     setTimeout(openQrPopup, 2000);
});