document.addEventListener('DOMContentLoaded', function () {
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
    if (formStartedAt) {
      formStartedAt.value = Math.floor(Date.now() / 1000).toString();
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
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
            'Accept': 'application/json'
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
