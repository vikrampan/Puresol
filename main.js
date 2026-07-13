/* ============================================================
   PURESOL — "Alkaline Gold" — Interactions
   GSAP 3.12.5 + ScrollTrigger + Lenis 1.1.18
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  gsap.registerPlugin(ScrollTrigger);

  /* Start loading deferred video immediately — before preloader finishes */
  loadDeferredVideo();

  /* ----------------------------------------------------------
     1. CORE: LENIS SMOOTH SCROLL (Load First)
     Ensures smooth scrolling works immediately on all pages.
     ---------------------------------------------------------- */
  let lenis;
  function initLenis() {
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    lenis = new Lenis({
      duration:        isMobile ? 0.6 : 0.85,
      easing:          (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      orientation:     'vertical',
      smoothWheel:     true,
      wheelMultiplier: 0.9,
      touchMultiplier: 1.2,
      smoothTouch:     false,
      autoRaf:         false,   /* We drive Lenis via GSAP ticker for sync */
    });

    /* Drive Lenis from GSAP's RAF so they share a single animation frame */
    gsap.ticker.add((time) => { lenis.raf(time * 1000); });
    gsap.ticker.lagSmoothing(0);

    /* Batch ScrollTrigger refreshes — don't call update on every scroll event */
    lenis.on('scroll', () => { ScrollTrigger.update(); });
  }

  // Initialize smooth scrolling immediately unconditionally
  initLenis();


  /* ----------------------------------------------------------
     2. PRELOADER & INITIALIZATION BOOTSTRAP
     ---------------------------------------------------------- */
  const preloader        = document.querySelector('.preloader');
  const preloaderBrand   = document.querySelector('.preloader__brand');
  const preloaderLine    = document.querySelector('.preloader__line');
  const preloaderTagline = document.querySelector('.preloader__tagline');
  const mainContent      = document.querySelector('.main-content');
  let   siteInitialised  = false;

  /** Boot the rest of the site features that require the DOM to be ready */
  function initSite() {
    if (siteInitialised) return;
    siteInitialised = true;

    /* Kill any lingering preloader visuals */
    if (preloader) {
      preloader.style.display = 'none';
      preloader.style.pointerEvents = 'none';
    }
    if (mainContent) gsap.set(mainContent, { opacity: 1 });

    if (document.querySelector('.hero')) heroEntrance();
    initScrollAnimations();
    initNav();
    initMarquee();
    initSaltLab();
    initWellnessZones();
    if (document.querySelector('.global-reach')) initGlobalMap();
    if (document.querySelector('.faq-item__question')) initFaqAccordion();
  }

  /** Load deferred iframes — called immediately on DOMContentLoaded */
  function loadDeferredVideo() {
    document.querySelectorAll('iframe[data-src]').forEach(function(iframe) {
      iframe.src = iframe.getAttribute('data-src');
      iframe.removeAttribute('data-src');
    });
  }

  if (preloader && preloaderBrand) {
    const preloaderTL = gsap.timeline({ onComplete: initSite });

    preloaderTL
      .to(preloaderBrand,   { opacity: 1, duration: 0.6, ease: 'power2.out' })
      .to(preloaderLine,    { scaleX: 1,  duration: 0.5, ease: 'power2.inOut' }, '-=0.2')
      .to(preloaderTagline, { opacity: 1, duration: 0.4, ease: 'power2.out' }, '-=0.15')
      .to({}, { duration: 0.35 })
      .to(preloaderBrand,   { opacity: 0, y: -20, duration: 0.3, ease: 'power2.in' })
      .to([preloaderLine, preloaderTagline], { opacity: 0, duration: 0.25, ease: 'power2.in' }, '-=0.2')
      .to(preloader, { yPercent: -100, duration: 0.7, ease: 'power3.inOut' }, '-=0.1')
      .set(preloader, { display: 'none' })
      .to(mainContent, { opacity: 1, duration: 0.4, ease: 'power2.out' }, '-=0.3');

    /* Safety net: if GSAP animation stalls, force-init after 3s */
    setTimeout(initSite, 3000);
  } else {
    /* Sub-pages without preloader */
    initSite();
  }


  /* Handled at the top of file now for immediate startup */

  /* Fix scroll on sub-pages where Lenis initialises before layout is complete */
  window.addEventListener('load', () => {
    /* Double refresh ensures tricky fonts and GSAP markers are synced */
    ScrollTrigger.refresh();
    if (lenis) lenis.resize();
    setTimeout(() => { ScrollTrigger.refresh(); }, 500);
  });


  /* ----------------------------------------------------------
     3. HERO ENTRANCE
     ---------------------------------------------------------- */
  function heroEntrance() {
    const heroTL = gsap.timeline({ delay: 0.2 });
    heroTL
      .to('.hero__eyebrow',  { opacity: 1, y: 0,     duration: 0.8, ease: 'power3.out' })
      .to('.hero__title',    { opacity: 1,            duration: 1.0, ease: 'power3.out' }, '-=0.4')
      .to('.hero__divider',  { scaleX: 1,             duration: 0.7, ease: 'power2.inOut' }, '-=0.5')
      .to('.hero__subtitle', { opacity: 1, y: 0,     duration: 0.8, ease: 'power3.out' }, '-=0.3')
      .to('.hero__scroll',   { opacity: 1,            duration: 0.6, ease: 'power2.out' }, '-=0.2');
  }


  /* ----------------------------------------------------------
     4. NAV SCROLL BEHAVIOUR
     ---------------------------------------------------------- */
  function initNav() {
    const nav = document.querySelector('.nav');
    if (!nav) return;

    ScrollTrigger.create({
      start: 'top -80px',
      onUpdate: (self) => {
        if (self.direction === 1 && self.scroll() > 80) {
          nav.classList.add('scrolled');
        } else if (self.scroll() <= 80) {
          nav.classList.remove('scrolled');
        }
      }
    });

    /* For sub-pages without hero, start scrolled */
    if (!document.querySelector('.hero')) {
      nav.classList.add('scrolled');
    }

    const hamburger = document.querySelector('.nav__hamburger');
    const navLinks  = document.querySelector('.nav__links');
    if (hamburger) {
      hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        hamburger.classList.toggle('open');
      });
      /* Close menu on link click */
      navLinks.querySelectorAll('.nav__link').forEach(link => {
        link.addEventListener('click', () => {
          navLinks.classList.remove('active');
          hamburger.classList.remove('open');
        });
      });
    }
  }


  /* ----------------------------------------------------------
     5. SCROLL-TRIGGERED ANIMATIONS
     ---------------------------------------------------------- */
  function initScrollAnimations() {

    gsap.utils.toArray('.reveal-up').forEach((el) => {
      gsap.to(el, {
        scrollTrigger: { trigger: el, start: 'top 85%', toggleActions: 'play none none none' },
        opacity: 1, y: 0, duration: 1, ease: 'power3.out',
      });
    });

    gsap.utils.toArray('.reveal-left').forEach((el) => {
      gsap.to(el, {
        scrollTrigger: { trigger: el, start: 'top 85%', toggleActions: 'play none none none' },
        opacity: 1, x: 0, duration: 1, ease: 'power3.out',
      });
    });

    gsap.utils.toArray('.reveal-right').forEach((el) => {
      gsap.to(el, {
        scrollTrigger: { trigger: el, start: 'top 85%', toggleActions: 'play none none none' },
        opacity: 1, x: 0, duration: 1, ease: 'power3.out',
      });
    });

    gsap.utils.toArray('.reveal-scale').forEach((el) => {
      gsap.to(el, {
        scrollTrigger: { trigger: el, start: 'top 85%', toggleActions: 'play none none none' },
        opacity: 1, scale: 1, duration: 1, ease: 'power3.out',
      });
    });

    /* Intro choreography */
    const introSection = document.querySelector('.intro');
    if (introSection) {
      gsap.timeline({
        scrollTrigger: { trigger: introSection, start: 'top 70%', toggleActions: 'play none none none' }
      })
        .from('.intro__label',   { opacity: 0, y: 20, duration: 0.6, ease: 'power3.out' })
        .from('.intro__heading', { opacity: 0, y: 40, duration: 0.8, ease: 'power3.out' }, '-=0.3')
        .from('.intro__text',    { opacity: 0, y: 30, duration: 0.8, ease: 'power3.out' }, '-=0.4')
        .from('.intro__divider', { scaleX: 0,          duration: 0.6, ease: 'power2.inOut' }, '-=0.3');
    }

    /* Product cards stagger */
    const cards = gsap.utils.toArray('.product-card');
    if (cards.length) {
      gsap.from(cards, {
        scrollTrigger: { trigger: '.products__grid', start: 'top 75%', toggleActions: 'play none none none' },
        opacity: 0, y: 60, scale: 0.95,
        duration: 0.9, stagger: 0.2, ease: 'power3.out',
      });
    }

    /* Process timeline steps */
    gsap.utils.toArray('.process-step').forEach((step) => {
      const content = step.querySelector('.process-step__content');
      const visual  = step.querySelector('.process-step__visual');
      const node    = step.querySelector('.process-step__node');
      const isFlip  = step.classList.contains('process-step--flip');

      const tl = gsap.timeline({
        scrollTrigger: { trigger: step, start: 'top 80%', toggleActions: 'play none none none' }
      });

      if (content) {
        tl.from(content, { opacity: 0, x: isFlip ? 40 : -40, duration: 0.75, ease: 'power3.out' });
      }
      if (node) {
        tl.from(node, { opacity: 0, scale: 0.5, duration: 0.45, ease: 'back.out(1.7)' }, '-=0.45');
      }
      if (visual) {
        tl.from(visual, { opacity: 0, x: isFlip ? -40 : 40, scale: 0.92, duration: 0.8, ease: 'power3.out' }, '-=0.3');
      }
    });

    /* Comparison rows */
    const comparisonTable = document.querySelector('.comparison__table');
    if (comparisonTable) {
      const rows = gsap.utils.toArray('.comparison__table tbody tr');
      if (rows.length) {
        gsap.from(rows, {
          scrollTrigger: { trigger: comparisonTable, start: 'top 75%', toggleActions: 'play none none none' },
          opacity: 0, x: -30, duration: 0.6, stagger: 0.08, ease: 'power3.out',
        });
      }
    }

    /* Benefits strip */
    const strip = document.querySelector('.benefits-strip');
    if (strip) {
      gsap.from(strip, {
        scrollTrigger: { trigger: strip, start: 'top 90%', toggleActions: 'play none none none' },
        opacity: 0, duration: 0.8, ease: 'power2.out',
      });
    }

    /* Benefit cards stagger */
    const healthGrid = document.querySelector('.health-benefits__grid');
    if (healthGrid) {
      const benefitCards = gsap.utils.toArray('.benefit-card');
      if (benefitCards.length) {
        gsap.from(benefitCards, {
          scrollTrigger: { trigger: healthGrid, start: 'top 75%', toggleActions: 'play none none none' },
          opacity: 0, y: 40, duration: 0.8, stagger: 0.1, ease: 'power3.out',
        });
      }
    }

    /* Value cards stagger */
    const valuesGrid = document.querySelector('.values-grid');
    if (valuesGrid) {
      const valueCards = gsap.utils.toArray('.value-card');
      if (valueCards.length) {
        gsap.from(valueCards, {
          scrollTrigger: { trigger: valuesGrid, start: 'top 75%', toggleActions: 'play none none none' },
          opacity: 0, y: 40, duration: 0.8, stagger: 0.15, ease: 'power3.out',
        });
      }
    }

    /* Science feature blocks */
    gsap.utils.toArray('.science-feature-grid').forEach(grid => {
      const visual = grid.querySelector('.science-feature__visual');
      const content = grid.querySelector('.science-feature__content');
      if (visual && content) {
        gsap.timeline({ scrollTrigger: { trigger: grid, start: 'top 75%', toggleActions: 'play none none none' } })
          .from(visual, { opacity: 0, scale: 0.9, duration: 0.8, ease: 'power3.out' })
          .from(content, { opacity: 0, x: 40, duration: 0.8, ease: 'power3.out' }, '-=0.4');
      }
    });

    /* Page hero entrance */
    const pageHero = document.querySelector('.page-hero');
    if (pageHero) {
      gsap.timeline({ delay: 0.3 })
        .from('.page-hero__eyebrow', { opacity: 0, y: 20, duration: 0.6, ease: 'power3.out' })
        .from('.page-hero__title', { opacity: 0, y: 40, duration: 0.8, ease: 'power3.out' }, '-=0.3')
        .from('.page-hero__divider', { scaleX: 0, duration: 0.6, ease: 'power2.inOut' }, '-=0.3')
        .from('.page-hero__subtitle', { opacity: 0, y: 20, duration: 0.6, ease: 'power3.out' }, '-=0.2');
    }

    /* Footer */
    const footer = document.querySelector('.footer');
    if (footer) {
      const footerItems = gsap.utils.toArray('.footer__top > *');
      if (footerItems.length) {
        gsap.from(footerItems, {
          scrollTrigger: { trigger: footer, start: 'top 85%', toggleActions: 'play none none none' },
          opacity: 0, y: 30, duration: 0.7, stagger: 0.1, ease: 'power3.out',
        });
      }
    }
  }


    /* ----------------------------------------------------------
     6. MARQUEE DUPLICATION
     Duplicating the mixed img set natively via JS so the container perfectly loops.
     Safely wrapped so it only ever runs if the track actually exists on the page.
     ---------------------------------------------------------- */
  function initMarquee() {
    const track = document.querySelector('.benefits-strip__track');
    if (!track) return;
    track.innerHTML += track.innerHTML;
  }


  /* ----------------------------------------------------------
     8. INTERACTIVE SALT LAB
     ---------------------------------------------------------- */
  function initSaltLab() {
    const samples = document.querySelectorAll('.salt-sample');
    if (!samples.length) return;

    const saltData = {
      refined: {
        ph: 7.0,
        phPosition: 50,       // percentage on the scale
        minerals: 2,
        mineralBar: 4,        // percentage fill
        microplastics: { label: 'High', icon: '✗', class: 'danger' },
        additives: { label: 'Present', icon: '✗', class: 'danger' },
        bleaching: { label: 'Yes', icon: '✗', class: 'danger' },
        processing: { label: 'Industrial', icon: '⚙' , class: 'warning' },
        score: 28,
        verdict: 'Poor — Highly Processed',
        ringColor: '#ff4444',
        mineralTags: ['Sodium', 'Chloride'],
      },
      himalayan: {
        ph: 7.3,
        phPosition: 52.1,
        minerals: 40,
        mineralBar: 74,
        microplastics: { label: 'Moderate', icon: '⚠', class: 'warning' },
        additives: { label: 'Minimal', icon: '~', class: 'warning' },
        bleaching: { label: 'No', icon: '✓', class: '' },
        processing: { label: 'Mined', icon: '⛏', class: 'warning' },
        score: 62,
        verdict: 'Moderate — Better Choice',
        ringColor: '#ddaa44',
        mineralTags: ['Iron', 'Calcium', 'Potassium', 'Magnesium'],
      },
      puresol: {
        ph: 9.5,
        phPosition: 67.8,
        minerals: 54,
        mineralBar: 100,
        microplastics: { label: 'Zero', icon: '✓', class: '' },
        additives: { label: 'Zero', icon: '✓', class: '' },
        bleaching: { label: 'No', icon: '✓', class: '' },
        processing: { label: 'Sun-Dried', icon: '&#9728;', class: '' },
        score: 98,
        verdict: 'Exceptional Purity',
        ringColor: '#C7195A',
        mineralTags: ['Iron', 'Zinc', 'Selenium', 'Lithium', 'Copper', 'Chromium', 'Manganese', 'Boron', 'Molybdenum'],
      },
    };

    function updateLab(saltKey) {
      const data = saltData[saltKey];
      if (!data) return;

      /* Update active sample button */
      samples.forEach(s => s.classList.remove('salt-sample--active'));
      document.querySelector(`[data-salt="${saltKey}"]`)?.classList.add('salt-sample--active');

      /* pH Needle */
      const needle = document.getElementById('phNeedle');
      const phValue = document.getElementById('phValue');
      if (needle) needle.style.left = data.phPosition + '%';
      if (phValue) phValue.textContent = data.ph.toFixed(1);

      /* Mineral count — animate the number */
      const mineralCountEl = document.getElementById('mineralCount');
      if (mineralCountEl) {
        const startVal = parseInt(mineralCountEl.textContent) || 0;
        const endVal = data.minerals;
        animateNumber(mineralCountEl, startVal, endVal, 600);
      }

      /* Mineral bar */
      const mineralBar = document.getElementById('mineralBar');
      if (mineralBar) mineralBar.style.width = data.mineralBar + '%';

      /* Mineral tags */
      const mineralTagsEl = document.getElementById('mineralTags');
      if (mineralTagsEl) {
        mineralTagsEl.innerHTML = data.mineralTags
          .map(tag => `<span class="mineral-tag">${tag}</span>`)
          .join('');
      }

      /* Purity indicators */
      updatePurityItem('microplastic', data.microplastics);
      updatePurityItem('additive', data.additives);
      updatePurityItem('bleach', data.bleaching);
      updatePurityItem('process', data.processing);

      /* Wellness ring */
      const ring = document.getElementById('wellnessRing');
      const scoreEl = document.getElementById('wellnessScore');
      const verdictEl = document.getElementById('wellnessVerdict');

      if (ring) {
        const circumference = 326.73;
        const offset = circumference * (1 - data.score / 100);
        ring.style.strokeDashoffset = offset;
        ring.style.stroke = data.ringColor;
      }

      if (scoreEl) {
        const startScore = parseInt(scoreEl.textContent) || 0;
        animateNumber(scoreEl, startScore, data.score, 800);
      }

      if (verdictEl) verdictEl.textContent = data.verdict;
    }

    function updatePurityItem(prefix, itemData) {
      const icon = document.getElementById(prefix + 'Icon');
      const label = document.getElementById(prefix + 'Label');
      if (icon) {
        icon.innerHTML = itemData.icon;
        icon.className = 'purity-item__icon' + (itemData.class ? ' ' + itemData.class : '');
      }
      if (label) label.textContent = itemData.label;
    }

    function animateNumber(el, from, to, duration) {
      const start = performance.now();
      function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        const current = Math.round(from + (to - from) * eased);
        el.textContent = current;
        if (progress < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }

    samples.forEach(sample => {
      sample.addEventListener('click', () => {
        const saltKey = sample.dataset.salt;
        updateLab(saltKey);
      });
    });

    /* Salt Lab scroll-triggered entrance */
    const labSection = document.querySelector('.salt-lab');
    if (labSection) {
      gsap.from('.salt-lab__samples', {
        scrollTrigger: { trigger: labSection, start: 'top 70%', toggleActions: 'play none none none' },
        opacity: 0, y: 30, duration: 0.8, ease: 'power3.out',
      });

      gsap.from('.lab-result', {
        scrollTrigger: { trigger: '.salt-lab__results', start: 'top 80%', toggleActions: 'play none none none' },
        opacity: 0, y: 40, scale: 0.95, duration: 0.7, stagger: 0.1, ease: 'power3.out',
      });
    }
  }


  /* ----------------------------------------------------------
     9. WELLNESS ZONES (Interactive wellness selector)
     ---------------------------------------------------------- */
  function initWellnessZones() {
    const tabs = document.querySelectorAll('.wellness-tab');
    if (!tabs.length) return;

    const wellnessData = {
      energy: {
        emoji: 'I',
        title: 'Energy & Metabolism',
        desc: 'The B-vitamins in Puresol — B1, B2, B3, B6, B9, B12 — are not added in a factory. They are there because Dunaliella salina algae and Sambhar\'s ancient brine put them there. Alongside Manganese, Chromium, and Vanadium, they support every cell\'s ability to convert food into energy.',
        minerals: ['B1 Thiamine', 'B2 Riboflavin', 'B3 Niacin', 'B6 Pyridoxine', 'Manganese', 'Chromium', 'Vanadium', 'Cobalt'],
        callout: 'The B-complex that supplement companies sell in bottles — it is already in the lake.',
      },
      immunity: {
        emoji: 'II',
        title: 'Immunity & Cell Defense',
        desc: 'Zinc, Selenium, Vitamin C, Copper. These minerals are absent from refined salt because refining removes them. They are present in Puresol because nothing was removed. Selenium alone is missing from 90% of commercial salts worldwide.',
        minerals: ['Zinc', 'Selenium', 'Vitamin C', 'B6 Pyridoxine', 'B9 Folate', 'Copper', 'Cobalt', 'Iron'],
        callout: 'Selenium does not survive industrial refining. It survived Sambhar Lake.',
      },
      heart: {
        emoji: '♡',
        title: 'Heart & Circulation',
        desc: 'Balanced electrolytes — not processed sodium chloride. Natural Sodium, Magnesium, Calcium, Potassium in the ratios the body evolved to use. Folate and B12 keep homocysteine levels in check. pH 9+ means less acid burden on vessel walls.',
        minerals: ['Natural Sodium', 'Magnesium', 'Calcium', 'Potassium', 'B9 Folate', 'B12 Cobalamin', 'Molybdenum'],
        callout: 'Electrolytes in their natural ratios. Not engineered — inherited from four thousand years of geology.',
      },
      thyroid: {
        emoji: '◈',
        title: 'Thyroid & Hormones',
        desc: 'Dunaliella salina lives in the brine. It absorbs and concentrates iodine from the lake\'s mineral-rich water, embedding it into each crystal as it forms. No potassium iodate. No chemical fortification. Your thyroid receives iodine the way it was always meant to.',
        minerals: ['Natural Iodine', 'Selenium', 'Zinc', 'Molybdenum', 'Cobalt', 'B12 Cobalamin'],
        callout: 'Iodine from a living organism. Not the chemical KIO₃ sprayed onto table salt.',
      },
      digestion: {
        emoji: '◉',
        title: 'Digestion & Gut Health',
        desc: 'pH above 9 gently neutralises excess stomach acidity without medication. Nothing coats the gut lining — no anti-caking agents, no flow improvers, no industrial chemicals at any stage. Zinc and Manganese support the enzymes that break food down.',
        minerals: ['Natural Alkalinity (pH 9+)', 'Zinc', 'Manganese', 'B9 Folate', 'Chromium', 'Molybdenum'],
        callout: 'The only thing that reaches your gut is salt and minerals. Nothing industrial. Nothing added.',
      },
      vision: {
        emoji: '◎',
        title: 'Vision & Skin',
        desc: 'Every grain of Super 7 Salt carries beta-carotene from Dunaliella salina — the same pigment that gives the lake its rose hue. In the body, it becomes Vitamin A. For eyes, for skin, for every barrier your immune system maintains against the outside world.',
        minerals: ['Beta-Carotene (Pro-Vit A)', 'Zinc', 'Selenium', 'Vitamin C', 'B2 Riboflavin', 'Copper'],
        callout: '60mg of beta-carotene per 2g serving. From a lake in Rajasthan, not a supplement factory.',
      },
    };

    const panel      = document.querySelector('.wellness-panel');
    const emojiEl    = document.getElementById('wellnessEmoji');
    const titleEl    = document.getElementById('wellnessTitle');
    const descEl     = document.getElementById('wellnessDesc');
    const mineralsEl = document.getElementById('wellnessMinerals');
    const calloutEl  = document.getElementById('wellnessCallout');

    function updatePanel(zoneKey) {
      const data = wellnessData[zoneKey];
      if (!data || !panel) return;

      panel.classList.add('is-transitioning');

      setTimeout(() => {
        if (emojiEl)    emojiEl.textContent  = data.emoji;
        if (titleEl)    titleEl.textContent  = data.title;
        if (descEl)     descEl.textContent   = data.desc;
        if (mineralsEl) mineralsEl.innerHTML = data.minerals.map(m => `<span class="mineral-pill">${m}</span>`).join('');
        if (calloutEl)  calloutEl.textContent = data.callout;
        panel.classList.remove('is-transitioning');
      }, 220);

      tabs.forEach(t => t.classList.remove('wellness-tab--active'));
      const activeTab = document.querySelector(`.wellness-tab[data-zone="${zoneKey}"]`);
      if (activeTab) activeTab.classList.add('wellness-tab--active');
    }

    tabs.forEach(tab => {
      tab.addEventListener('click', () => updatePanel(tab.dataset.zone));
    });

  }


  /* ----------------------------------------------------------
     10. FAQ ACCORDION (contact.html and other pages)
     ---------------------------------------------------------- */
  function initFaqAccordion() {
    document.querySelectorAll('.faq-item__question').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.closest('.faq-item');
        var isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(function (o) {
          o.classList.remove('open');
        });
        if (!isOpen) item.classList.add('open');
      });
    });
  }


  /* ----------------------------------------------------------
     11. NEWSLETTER SUBSCRIPTION FORM
     ---------------------------------------------------------- */
  const newsletterForm = document.querySelector('.newsletter-form');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const emailInput = newsletterForm.querySelector('input[type="email"]');
      const submitBtn  = newsletterForm.querySelector('button[type="submit"]');
      if (!emailInput || !submitBtn) return;

      const email = emailInput.value.trim();
      if (!email) return;

      submitBtn.disabled = true;

      try {
        const res  = await fetch('api/subscribe.php', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ email }),
        });
        const json = await res.json();

        if (json.success || res.ok) {
          submitBtn.textContent = 'Subscribed!';
          emailInput.value = '';
        } else {
          submitBtn.disabled = false;
        }
      } catch (_) {
        submitBtn.disabled = false;
      }
    });
  }

  /* ----------------------------------------------------------
     GLOBAL REACH — interactive world map
     Two-way sync between map pins and the market list; tooltip
     follows the active pin. Works with hover, keyboard and tap.
     ---------------------------------------------------------- */
  function initGlobalMap() {
    const map     = document.getElementById('globalMap');
    const tooltip = document.getElementById('mapTooltip');
    if (!map || !tooltip) return;

    const pins  = Array.from(map.querySelectorAll('.gr-pin'));
    const items = Array.from(document.querySelectorAll('.gr-item'));
    const countries = Array.from(map.querySelectorAll('.gr-country'));

    const byLoc = (arr, loc) => arr.find(el => el.dataset.loc === loc);

    const LABELS = {
      india:       ['India', 'Sambhar Lake · Our Source'],
      netherlands: ['Netherlands', 'Amsterdam'],
      belgium:     ['Belgium', 'Brussels'],
      luxembourg:  ['Luxembourg', 'Luxembourg City'],
      kenya:       ['Kenya', 'Nairobi County'],
    };

    function positionTooltip(pin) {
      const mapRect = map.getBoundingClientRect();
      const pinRect = pin.getBoundingClientRect();
      const x = pinRect.left + pinRect.width / 2 - mapRect.left;
      const y = pinRect.top + pinRect.height / 2 - mapRect.top;
      tooltip.style.left = x + 'px';
      tooltip.style.top  = y + 'px';
    }

    function activate(loc) {
      const pin  = byLoc(pins, loc);
      const item = byLoc(items, loc);
      const land = byLoc(countries, loc);
      pins.forEach(p => p.classList.toggle('is-active', p === pin));
      items.forEach(i => i.classList.toggle('is-active', i === item));
      countries.forEach(c => c.classList.toggle('is-active', c === land));
      if (pin && LABELS[loc]) {
        tooltip.innerHTML = '<strong>' + LABELS[loc][0] + '</strong><span>' + LABELS[loc][1] + '</span>';
        positionTooltip(pin);
        tooltip.classList.add('is-visible');
      }
    }

    function clear() {
      pins.forEach(p => p.classList.remove('is-active'));
      items.forEach(i => i.classList.remove('is-active'));
      countries.forEach(c => c.classList.remove('is-active'));
      tooltip.classList.remove('is-visible');
    }

    [...pins, ...items].forEach(el => {
      const loc = el.dataset.loc;
      el.addEventListener('mouseenter', () => activate(loc));
      el.addEventListener('mouseleave', clear);
      el.addEventListener('focus', () => activate(loc));
      el.addEventListener('blur', clear);
      /* tap toggles on touch devices */
      el.addEventListener('click', (e) => {
        e.preventDefault();
        const active = el.classList.contains('is-active');
        clear();
        if (!active) activate(loc);
      });
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(loc); }
      });
    });

    /* keep tooltip aligned if the layout reflows */
    window.addEventListener('resize', () => {
      const active = pins.find(p => p.classList.contains('is-active'));
      if (active) positionTooltip(active);
    });
  }

}); /* end DOMContentLoaded */


/* ============================================================
   PAGE VISIBILITY — pause all CSS animations when tab is hidden.
   This is outside DOMContentLoaded so it registers immediately.
   ============================================================ */
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    document.documentElement.classList.add('tab-hidden');
  } else {
    document.documentElement.classList.remove('tab-hidden');
  }
});
