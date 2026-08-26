// Reveal-on-scroll for elements marked [data-reveal]
(function () {
  var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
  if (!('IntersectionObserver' in window)) {
    nodes.forEach(function (n) { n.classList.add('is-visible'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        io.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
  nodes.forEach(function (n) { io.observe(n); });
})();

// Mobile nav toggle
(function () {
  var toggle = document.getElementById('navToggle');
  var header = document.querySelector('.site-header');
  if (!toggle || !header) return;
  toggle.addEventListener('click', function () {
    var open = header.classList.toggle('nav-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  header.querySelectorAll('.main-nav a').forEach(function (a) {
    a.addEventListener('click', function () {
      header.classList.remove('nav-open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });
})();

// Contact form wizard — one question per step, with a progress bar.
(function () {
  var form = document.getElementById('contactForm');
  if (!form || !form.classList.contains('wizard')) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('.wizard-step'));
  var segments = Array.prototype.slice.call(form.querySelectorAll('.wizard-progress .segment'));
  var stepNumEl = document.getElementById('wizardStepNum');
  var current = 0;

  function isStepValid(step) {
    var required = Array.prototype.slice.call(step.querySelectorAll('[required]'));
    return required.every(function (el) {
      if (el.type === 'radio') {
        return Array.prototype.some.call(step.querySelectorAll('input[name="' + el.name + '"]'), function (r) { return r.checked; });
      }
      return el.value.trim() !== '';
    });
  }

  function render() {
    steps.forEach(function (step, i) { step.classList.toggle('is-active', i === current); });
    segments.forEach(function (seg, i) { seg.classList.toggle('is-filled', i <= current); });
    if (stepNumEl) stepNumEl.textContent = current + 1;
  }

  form.querySelectorAll('.wizard-next').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var step = steps[current];
      if (!isStepValid(step)) {
        step.classList.remove('shake');
        void step.offsetWidth; // restart animation
        step.classList.add('shake');
        return;
      }
      if (current < steps.length - 1) { current++; render(); }
    });
  });

  form.querySelectorAll('.wizard-back').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (current > 0) { current--; render(); }
    });
  });

  render();
})();

// Contact form submit — sends via Web3Forms (api.web3forms.com), no backend needed.
// The access_key lives in the hidden input in index.html.
(function () {
  var form = document.getElementById('contactForm');
  var status = document.getElementById('formStatus');
  if (!form || !status) return;

  var submitBtn = form.querySelector('button[type="submit"]');
  var defaultLabel = submitBtn ? submitBtn.textContent : '';

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var accessKey = form.access_key ? form.access_key.value : '';
    if (!accessKey || accessKey.indexOf('PASTE_') === 0) {
      status.textContent = 'El formulario todavía no está conectado. Escribinos directo a holabaseocho@gmail.com.';
      return;
    }

    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Enviando…'; }
    status.textContent = '';

    fetch('https://api.web3forms.com/submit', {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: new FormData(form)
    })
      .then(function (res) { return res.json(); })
      .then(function (result) {
        if (result.success) {
          status.textContent = '¡Gracias! Te vamos a contestar a la brevedad.';
          status.classList.add('is-success');
          form.hidden = true;
        } else {
          status.textContent = 'Uy, no se pudo enviar. Escribinos directo a holabaseocho@gmail.com.';
        }
      })
      .catch(function () {
        status.textContent = 'Uy, no se pudo enviar. Escribinos directo a holabaseocho@gmail.com.';
      })
      .finally(function () {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = defaultLabel; }
      });
  });
})();
