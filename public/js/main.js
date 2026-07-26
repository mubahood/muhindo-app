// =======================================
// DEVROOTS ACADEMY – SITE INTERACTIONS
// =======================================
document.addEventListener("DOMContentLoaded", () => {

  // ------------------------------
  // UTILITY FUNCTIONS
  // ------------------------------
  const qs  = (sel, ctx = document) => ctx.querySelector(sel);
  const qsa = (sel, ctx = document) => ctx.querySelectorAll(sel);

  // ------------------------------
  // BACK TO TOP BUTTON
  // ------------------------------
  const backToTop = qs("#back-to-top");

  if (backToTop) {
    const toggleBackToTop = () => {
      backToTop.style.display = window.scrollY > 300 ? "flex" : "none";
    };
    window.addEventListener("scroll", toggleBackToTop);
    toggleBackToTop();
    backToTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
  }

  // ------------------------------
  // LIVE CHAT TOGGLE & BOT SIMULATION
  // ------------------------------
  const chatBtn   = qs("#live-chat .chat-btn");
  const chatBox   = qs("#live-chat .chat-box");
  const sendBtn   = qs("#send-btn");
  const chatInput = qs("#chat-input");
  const chatMsgs  = qs("#chat-messages");

  if (chatBtn && chatBox && sendBtn && chatInput && chatMsgs) {
    const addMsg = (text, isBot = false) => {
      const p       = document.createElement("p");
      p.textContent = text;
      p.style.margin     = "0.3rem 0";
      p.style.padding    = isBot ? "0.4rem 0.6rem" : "0.5rem 0.8rem";
      p.style.fontStyle  = isBot ? "italic" : "normal";
      p.style.textAlign  = isBot ? "left" : "right";
      p.style.background = isBot ? "#f0f0f0" : "#FFEBEE";
      chatMsgs.appendChild(p);
      chatMsgs.scrollTop = chatMsgs.scrollHeight;
    };

    const sendMessage = () => {
      const msg = chatInput.value.trim();
      if (!msg) return;
      addMsg(msg);
      chatInput.value = "";
      setTimeout(() => addMsg("DevRoots: Thanks! We'll get back to you soon.", true), 800);
    };

    chatBtn.addEventListener("click", () => {
      const isOpen = chatBox.style.display === "flex";
      chatBox.style.display       = isOpen ? "none" : "flex";
      chatBox.style.flexDirection = "column";
    });

    sendBtn.addEventListener("click", sendMessage);
    chatInput.addEventListener("keypress", e => { if (e.key === "Enter") sendMessage(); });
  }

  // ------------------------------
  // TESTIMONIALS SLIDER
  // ------------------------------
  const slides  = qsa(".testimonial-slide");
  const prevBtn = qs(".testimonial-controls .prev");
  const nextBtn = qs(".testimonial-controls .next");
  const dots    = qsa(".testimonial-dots .dot");
  let current   = 0;
  let interval;

  const showSlide = (idx) => {
    slides.forEach((s, i) => s.classList.toggle("active", i === idx));
    dots.forEach((d, i)   => d.classList.toggle("active", i === idx));
  };

  const goNext = () => { current = (current + 1) % slides.length; showSlide(current); };
  const goPrev = () => { current = (current - 1 + slides.length) % slides.length; showSlide(current); };

  const startAuto = () => { interval = setInterval(goNext, 5000); };
  const stopAuto  = () => clearInterval(interval);

  if (slides.length) {
    nextBtn?.addEventListener("click", () => { goNext(); stopAuto(); startAuto(); });
    prevBtn?.addEventListener("click", () => { goPrev(); stopAuto(); startAuto(); });
    dots.forEach((d, i) => d.addEventListener("click", () => {
      current = i; showSlide(i); stopAuto(); startAuto();
    }));
    showSlide(current);
    startAuto();
  }

  // ------------------------------
  // SCROLL-TRIGGERED FADE-IN
  // ------------------------------
  const scrollEls = qsa(".course-card, .why-item, .partner-logo-card");
  if (scrollEls.length && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity   = "1";
          entry.target.style.transform = "translateY(0)";
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    scrollEls.forEach(el => {
      el.style.opacity    = "0";
      el.style.transform  = "translateY(14px)";
      el.style.transition = "opacity 0.4s ease, transform 0.4s ease";
      observer.observe(el);
    });
  }

});
