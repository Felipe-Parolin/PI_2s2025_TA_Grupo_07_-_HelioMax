document.addEventListener("DOMContentLoaded", function () {
  // ========================================
  // MOBILE MENU
  // ========================================
  const mobileMenuToggle = document.getElementById("mobileMenuToggle");
  const mobileMenu = document.getElementById("mobileMenu");
  const mobileMenuClose = document.getElementById("mobileMenuClose");
  const mobileMenuOverlay = document.getElementById("mobileMenuOverlay");

  // Abrir menu mobile
  if (mobileMenuToggle && mobileMenu) {
    mobileMenuToggle.addEventListener("click", () => {
      mobileMenu.classList.add("active");
      mobileMenuOverlay.classList.add("active");
      document.body.style.overflow = "hidden";
    });
  }

  // Fechar menu mobile
  function closeMobileMenu() {
    if (mobileMenu && mobileMenuOverlay) {
      mobileMenu.classList.remove("active");
      mobileMenuOverlay.classList.remove("active");
      document.body.style.overflow = "";
    }
  }

  if (mobileMenuClose) {
    mobileMenuClose.addEventListener("click", closeMobileMenu);
  }

  if (mobileMenuOverlay) {
    mobileMenuOverlay.addEventListener("click", closeMobileMenu);
  }

  // Fechar menu ao clicar em um link
  const mobileNavLinks = document.querySelectorAll(".mobile-nav-links a");
  mobileNavLinks.forEach((link) => {
    link.addEventListener("click", closeMobileMenu);
  });

  // ========================================
  // HEADER SCROLL EFFECT
  // ========================================
  const header = document.querySelector(".header");
  let lastScroll = 0;

  window.addEventListener("scroll", () => {
    const currentScroll = window.pageYOffset;

    if (currentScroll > 100) {
      header.style.background = "rgba(13, 13, 13, 0.95)";
      header.style.boxShadow = "0 5px 20px rgba(0, 0, 0, 0.3)";
    } else {
      header.style.background = "rgba(13, 13, 13, 0.8)";
      header.style.boxShadow = "none";
    }

    lastScroll = currentScroll;
  });

  // ========================================
  // VEHICLE CARDS ANIMATIONS
  // ========================================
  const vehicleCards = document.querySelectorAll(".vehicle-card");

  vehicleCards.forEach((card) => {
    // Efeito de tilt/parallax ao mover o mouse
    card.addEventListener("mousemove", (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;

      const centerX = rect.width / 2;
      const centerY = rect.height / 2;

      const rotateX = (y - centerY) / 20;
      const rotateY = (centerX - x) / 20;

      card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px)`;
    });

    // Resetar transformação ao sair
    card.addEventListener("mouseleave", () => {
      card.style.transform = "";
    });

    // Adicionar efeito de pulse ao clicar
    card.addEventListener("click", (e) => {
      // Não executar se clicou no botão de deletar
      if (e.target.closest(".btn-delete")) return;

      card.style.animation = "pulse 0.4s ease";
      setTimeout(() => {
        card.style.animation = "";
      }, 400);
    });
  });

  // ========================================
  // DELETE VEHICLE FUNCTIONALITY
  // ========================================
  const deleteButtons = document.querySelectorAll(".btn-delete");

  deleteButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.stopPropagation(); // Prevenir propagação para o card

      const card = btn.closest(".vehicle-card");
      const vehicleName =
        card.querySelector(".vehicle-name").textContent.trim();

      // Confirmação
      if (
        confirm(
          `Tem certeza que deseja excluir o veículo "${vehicleName}"?\n\nEsta ação não pode ser desfeita.`
        )
      ) {
        // Animação de saída
        card.style.animation = "fadeOut 0.4s ease forwards";

        setTimeout(() => {
          card.remove();

          // Verificar se ainda há veículos
          const remainingCards =
            document.querySelectorAll(".vehicle-card").length;
          if (remainingCards === 0) {
            showEmptyState();
          }

          // Mostrar mensagem de sucesso (opcional)
          showNotification("Veículo excluído com sucesso!", "success");
        }, 400);
      }
    });
  });

  // ========================================
  // EMPTY STATE
  // ========================================
  function showEmptyState() {
    const vehiclesGrid = document.getElementById("vehiclesGrid");
    const emptyState = document.getElementById("emptyState");

    if (vehiclesGrid && emptyState) {
      vehiclesGrid.style.display = "none";
      emptyState.style.display = "block";
      emptyState.style.animation = "fadeIn 0.6s ease forwards";
    }
  }

  // ========================================
  // NOTIFICATION SYSTEM
  // ========================================
  function showNotification(message, type = "success") {
    // Remover notificação existente
    const existingNotification = document.querySelector(".notification");
    if (existingNotification) {
      existingNotification.remove();
    }

    // Criar nova notificação
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    // Estilos inline para a notificação
    Object.assign(notification.style, {
      position: "fixed",
      top: "100px",
      right: "20px",
      padding: "1rem 1.5rem",
      background: type === "success" ? "#00ff88" : "#ff4757",
      color: "#0d0d0d",
      borderRadius: "12px",
      fontWeight: "600",
      fontSize: "0.95rem",
      zIndex: "9999",
      boxShadow: "0 10px 30px rgba(0, 0, 0, 0.3)",
      animation: "slideInRight 0.4s ease, fadeOut 0.4s ease 2.6s forwards",
    });

    document.body.appendChild(notification);

    // Remover após 3 segundos
    setTimeout(() => {
      notification.remove();
    }, 3000);
  }

  // ========================================
  // INTERSECTION OBSERVER (Fade In Animations)
  // ========================================
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

  // Observar elementos com fade-in
  document.querySelectorAll(".fade-in").forEach((el) => {
    observer.observe(el);
  });

  // ========================================
  // SMOOTH SCROLL
  // ========================================
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      const href = this.getAttribute("href");
      if (href !== "#" && href !== "") {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        }
      }
    });
  });

  // ========================================
  // ADICIONAR ANIMAÇÕES CSS DINAMICAMENTE
  // ========================================
  const style = document.createElement("style");
  style.textContent = `
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.02); }
    }

    @keyframes fadeOut {
      from {
        opacity: 1;
        transform: translateY(0);
      }
      to {
        opacity: 0;
        transform: translateY(-20px);
      }
    }

    @keyframes slideInRight {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
  `;
  document.head.appendChild(style);

  // ========================================
  // PERFORMANCE: Reduzir animações em mobile
  // ========================================
  if (window.innerWidth <= 768) {
    // Desabilitar efeito de tilt em dispositivos móveis
    vehicleCards.forEach((card) => {
      card.removeEventListener("mousemove", () => {});
      card.addEventListener("touchstart", () => {
        card.style.transform = "scale(0.98)";
      });
      card.addEventListener("touchend", () => {
        card.style.transform = "";
      });
    });
  }

  // ========================================
  // AJUSTAR LAYOUT EM MUDANÇA DE ORIENTAÇÃO
  // ========================================
  window.addEventListener("orientationchange", () => {
    setTimeout(() => {
      // Recalcular estilos após mudança de orientação
      window.scrollTo(0, window.scrollY);
    }, 100);
  });

  // ========================================
  // KEYBOARD NAVIGATION
  // ========================================
  // Permitir navegação por teclado nos cards
  vehicleCards.forEach((card, index) => {
    card.setAttribute("tabindex", "0");
    card.setAttribute("role", "article");

    card.addEventListener("keypress", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        card.click();
      }
    });
  });

  // ========================================
  // VERIFICAR ESTADO INICIAL
  // ========================================
  // Se não houver veículos, mostrar empty state
  const initialCards = document.querySelectorAll(".vehicle-card");
  if (initialCards.length === 0) {
    showEmptyState();
  }

  // ========================================
  // LOG DE INICIALIZAÇÃO
  // ========================================
  console.log("✅ Meus Veículos - Sistema inicializado com sucesso!");
  console.log(`📊 Total de veículos: ${initialCards.length}`);
});