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

// Pricing: monthly / annual toggle
(function () {
  var btnMensual = document.getElementById('btnMensual');
  var btnAnual = document.getElementById('btnAnual');
  if (!btnMensual || !btnAnual) return;

  function setMode(annual) {
    btnMensual.classList.toggle('is-active', !annual);
    btnAnual.classList.toggle('is-active', annual);

    document.querySelectorAll('.price').forEach(function (el) {
      el.textContent = annual ? el.dataset.annual : el.dataset.monthly;
    });
    document.querySelectorAll('.plan-note').forEach(function (el) {
      el.textContent = annual ? el.dataset.annual : el.dataset.monthly;
    });
  }

  btnMensual.addEventListener('click', function () { setMode(false); });
  btnAnual.addEventListener('click', function () { setMode(true); });
})();

// Contact form (no backend wired up yet — swap the console.log below
// for a fetch() call to your form handler when you have one)
(function () {
  var form = document.getElementById('contactForm');
  var status = document.getElementById('formStatus');
  if (!form || !status) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var data = new FormData(form);
    console.log('Contact form submission:', Object.fromEntries(data.entries()));
    status.textContent = '¡Gracias! Te vamos a contestar a la brevedad.';
    form.reset();
  });
})();
