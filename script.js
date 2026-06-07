document.addEventListener('DOMContentLoaded', function () {
  const siteNav = document.querySelector('.site-nav');
  const navToggle = document.querySelector('.site-nav__toggle');
  const navMenu = document.getElementById('siteNavigation');
  if (siteNav && navToggle && navMenu) {
    const closeNavigation = function () {
      siteNav.classList.remove('is-open');
      navToggle.setAttribute('aria-expanded', 'false');
      navToggle.setAttribute('aria-label', 'Navigation öffnen');
    };

    navToggle.addEventListener('click', function () {
      const isOpen = siteNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      navToggle.setAttribute('aria-label', isOpen ? 'Navigation schließen' : 'Navigation öffnen');
    });

    navMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', closeNavigation);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeNavigation();
      }
    });
  }

  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach(link => {
    link.addEventListener('click', function (event) {
      const targetId = this.getAttribute('href').substring(1);
      const target = document.getElementById(targetId);
      if (target) {
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  const gallery = document.querySelector('[data-gallery-slider]');
  if (gallery) {
    const gallerySection = document.querySelector('[data-gallery-section]');
    const galleryLinks = Array.from(document.querySelectorAll('[data-gallery-link]'));
    const dotsContainer = document.querySelector('[data-gallery-dots]');
    const prevButton = document.querySelector('[data-gallery-prev]');
    const nextButton = document.querySelector('[data-gallery-next]');
    let slides = [];
    let dots = [];
    let activeIndex = 0;
    let autoplayTimer = null;

    const setGalleryVisibility = function (isVisible) {
      if (gallerySection) {
        gallerySection.hidden = !isVisible;
      }

      galleryLinks.forEach(link => {
        link.hidden = !isVisible;
      });
    };

    const createSlide = function (item, index) {
      const figure = document.createElement('figure');
      figure.className = 'gallery-slide';
      if (index === 0) {
        figure.classList.add('is-active');
      }

      const image = document.createElement('img');
      image.src = item.src;
      image.alt = item.alt || '';
      image.loading = 'lazy';

      const caption = document.createElement('figcaption');
      caption.textContent = item.caption || '';

      figure.append(image, caption);
      return figure;
    };

    const createDot = function (index) {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.dataset.galleryDot = String(index);
      dot.setAttribute('aria-label', `Bild ${index + 1} anzeigen`);
      if (index === 0) {
        dot.classList.add('is-active');
      }

      return dot;
    };

    const showSlide = function (index) {
      if (!slides.length) {
        return;
      }

      activeIndex = (index + slides.length) % slides.length;
      slides.forEach((slide, slideIndex) => {
        slide.classList.toggle('is-active', slideIndex === activeIndex);
      });

      dots.forEach((dot, dotIndex) => {
        dot.classList.toggle('is-active', dotIndex === activeIndex);
      });
    };

    const setControlsVisibility = function () {
      const isEnabled = slides.length > 1;
      if (prevButton) {
        prevButton.hidden = !isEnabled;
      }

      if (nextButton) {
        nextButton.hidden = !isEnabled;
      }

      if (dotsContainer) {
        dotsContainer.hidden = !isEnabled;
      }
    };

    const stopAutoplay = function () {
      if (autoplayTimer) {
        window.clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
    };

    const startAutoplay = function () {
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduceMotion || slides.length < 2) {
        return;
      }

      autoplayTimer = window.setInterval(function () {
        showSlide(activeIndex + 1);
      }, 6500);
    };

    const initGallery = function (items) {
      const galleryItems = items.filter(item => item && item.src && item.enabled !== false);
      if (!galleryItems.length) {
        setGalleryVisibility(false);
        setControlsVisibility();
        return;
      }

      setGalleryVisibility(true);
      gallery.innerHTML = '';
      if (dotsContainer) {
        dotsContainer.innerHTML = '';
      }

      galleryItems.forEach((item, index) => {
        gallery.appendChild(createSlide(item, index));
        if (dotsContainer) {
          dotsContainer.appendChild(createDot(index));
        }
      });

      slides = Array.from(gallery.querySelectorAll('.gallery-slide'));
      dots = dotsContainer ? Array.from(dotsContainer.querySelectorAll('[data-gallery-dot]')) : [];
      setControlsVisibility();

      if (prevButton) {
        prevButton.addEventListener('click', function () {
          stopAutoplay();
          showSlide(activeIndex - 1);
        });
      }

      if (nextButton) {
        nextButton.addEventListener('click', function () {
          stopAutoplay();
          showSlide(activeIndex + 1);
        });
      }

      dots.forEach(dot => {
        dot.addEventListener('click', function () {
          stopAutoplay();
          showSlide(Number(this.dataset.galleryDot));
        });
      });

      gallery.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') {
          stopAutoplay();
          showSlide(activeIndex - 1);
        }

        if (event.key === 'ArrowRight') {
          stopAutoplay();
          showSlide(activeIndex + 1);
        }
      });

      showSlide(0);
      startAutoplay();
    };

    fetch('gallery.json', { cache: 'no-cache' })
      .then(response => {
        if (!response.ok) {
          throw new Error('gallery.json konnte nicht geladen werden.');
        }

        return response.json();
      })
      .then(items => {
        if (!Array.isArray(items)) {
          throw new Error('gallery.json muss eine Liste enthalten.');
        }

        initGallery(items);
      })
      .catch(() => {
        setGalleryVisibility(false);
        setControlsVisibility();
      });
  }

  const mapFrame = document.querySelector('.map-frame');
  const mapOverlay = document.querySelector('.map-frame__overlay');
  if (mapFrame && mapOverlay) {
    mapOverlay.addEventListener('click', function () {
      mapFrame.classList.add('is-active');
      mapOverlay.setAttribute('aria-hidden', 'true');
      mapOverlay.setAttribute('tabindex', '-1');
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        mapFrame.classList.remove('is-active');
        mapOverlay.removeAttribute('aria-hidden');
        mapOverlay.removeAttribute('tabindex');
      }
    });
  }

  const renderFixtures = async function () {
    const teamCards = document.querySelectorAll('.team-card[data-team-id]');
    if (!teamCards.length) {
      return;
    }

    const setFixtureContent = function (fixtureBox, items) {
      fixtureBox.textContent = '';
      items.forEach(item => {
        const element = document.createElement('p');
        element.className = item.className;
        element.textContent = item.text;
        fixtureBox.appendChild(element);
      });
    };

    try {
      const response = await fetch('fixtures.json', { cache: 'no-store' });
      if (!response.ok) {
        throw new Error('fixtures.json konnte nicht geladen werden');
      }

      const data = await response.json();
      const fixtures = data.teams || {};

      teamCards.forEach(card => {
        const teamId = card.dataset.teamId;
        const fixture = fixtures[teamId];
        const fixtureBox = card.querySelector('.team-fixture');
        if (!fixtureBox) {
          return;
        }

        if (!fixture) {
          fixtureBox.dataset.fixtureStatus = 'empty';
          setFixtureContent(fixtureBox, [
            { className: 'team-fixture__label', text: 'Nächstes Spiel' },
            { className: 'team-fixture__text', text: 'Aktuell kein Termin eingetragen.' }
          ]);
          return;
        }

        const date = new Date(fixture.datetime);
        const dateText = new Intl.DateTimeFormat('de-DE', {
          weekday: 'short',
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        }).format(date);

        fixtureBox.dataset.fixtureStatus = 'loaded';
        const items = [
          { className: 'team-fixture__label', text: 'Nächstes Spiel' },
          { className: 'team-fixture__date', text: dateText },
          { className: 'team-fixture__text', text: `${fixture.home} - ${fixture.away}` }
        ];

        if (fixture.location) {
          items.push({ className: 'team-fixture__location', text: fixture.location });
        }

        setFixtureContent(fixtureBox, items);
      });
    } catch (error) {
      teamCards.forEach(card => {
        const fixtureBox = card.querySelector('.team-fixture');
        if (fixtureBox) {
          fixtureBox.dataset.fixtureStatus = 'error';
          setFixtureContent(fixtureBox, [
            { className: 'team-fixture__label', text: 'Nächstes Spiel' },
            { className: 'team-fixture__text', text: 'Termine momentan nicht verfügbar.' }
          ]);
        }
      });
    }
  };

  renderFixtures();

  const form = document.getElementById('contactForm');
  const status = document.getElementById('formStatus');
  if (form && status) {
    const formStartedAt = document.getElementById('formStartedAt');
    const formToken = document.getElementById('formToken');
    const ensureFormToken = function () {
      if (formStartedAt && !formStartedAt.value) {
        formStartedAt.value = Math.floor(Date.now() / 1000).toString();
      }

      if (formStartedAt && formToken) {
        formToken.value = `scg-contact-${formStartedAt.value}`;
      }
    };

    ensureFormToken();

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      ensureFormToken();
      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Senden...';
      }

      status.textContent = 'Nachricht wird gesendet...';
      status.className = 'form-status';

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: {
            'Accept': 'application/json',
            'X-SCG-Form': 'contact'
          }
        });

        if (response.ok) {
          let message = 'Danke! Deine Nachricht wurde gesendet.';
          try {
            const data = await response.json();
            if (data.message) {
              message = data.message;
            }
          } catch (parseError) {
            // The PHP endpoint should return JSON, but keep the success path tolerant.
          }

          status.textContent = message;
          status.classList.add('success');
          form.reset();
        } else {
          let message = 'Fehler beim Senden. Bitte versuche es später.';
          try {
            const data = await response.json();
            if (data.message) {
              message = data.message;
            }
          } catch (parseError) {
            // Some form providers return HTML or an empty response for errors.
          }

          status.textContent = message;
          status.classList.add('error');
        }
      } catch (error) {
        status.textContent = 'Senden fehlgeschlagen. Bitte versuche es später.';
        status.classList.add('error');
      }

      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Nachricht senden';
      }
    });
  }
});
