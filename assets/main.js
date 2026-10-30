// Mobile nav
var t = document.querySelector('.nav-toggle'), n = document.getElementById('nav');
if (t) t.addEventListener('click', function () {
  var open = n.classList.toggle('open');
  t.setAttribute('aria-expanded', open);
});

// Footer year
var y = document.getElementById('yr');
if (y) y.textContent = new Date().getFullYear();

// Joy Calendar filter
document.querySelectorAll('.cal-filter button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.cal-filter button').forEach(function (x) { x.setAttribute('aria-pressed', x === b); });
    var f = b.dataset.filter;
    document.querySelectorAll('.month li').forEach(function (li) {
      li.style.display = (f === 'all' || li.dataset.kind === f) ? '' : 'none';
    });
  });
});

// Contact form: sends to contact.php (emails edward@joystories.co on Hostinger).
// If that isn't available, falls back to opening the visitor's email app.
var form = document.getElementById('contact-form');
if (form) form.addEventListener('submit', function (e) {
  e.preventDefault();
  var d = new FormData(form), btn = form.querySelector('button[type=submit]'), status = document.getElementById('form-status');
  function mailto() {
    var body = ['Name: ' + d.get('name'), 'Email: ' + d.get('email'), 'Company: ' + d.get('company'),
      'Team size: ' + d.get('size'), 'Interested in: ' + d.get('interest'), '', d.get('message')].join('\n');
    location.href = 'mailto:' + form.dataset.email + '?subject=' + encodeURIComponent('Enquiry from ' + d.get('company')) +
      '&body=' + encodeURIComponent(body);
  }
  btn.disabled = true; btn.textContent = 'Sending...';
  fetch('contact.php', { method: 'POST', body: d })
    .then(function (r) { return r.ok ? r.json() : { ok: false }; })
    .then(function (res) {
      if (!res.ok) throw new Error('send failed');
      form.reset();
      status.hidden = false;
      status.textContent = 'Thank you. Your message is with Edward, and we\'ll come back within one working day.';
      btn.textContent = 'Sent';
    })
    .catch(function () { btn.disabled = false; btn.textContent = 'Send'; mailto(); });
});

var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
var COLORS = ['#ffd400', '#ff7a00', '#ff3d7f', '#2f6bff', '#00c48c'];

// Rotating hero word
document.querySelectorAll('.rotator').forEach(function (el) {
  if (reduce) return;
  var words = el.dataset.words.split(','), i = 0;
  setInterval(function () {
    el.classList.add('out');
    setTimeout(function () { i = (i + 1) % words.length; el.textContent = words[i]; el.classList.remove('out'); }, 350);
  }, 2400);
});

// Reveal on scroll
var revealEls = document.querySelectorAll('main h2, main .lead, .grid, .split > *, .journey, .media, .cal, .logos, .ticks, .lo-board, .mm-board');
if ('IntersectionObserver' in window && !reduce) {
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.12 });
  revealEls.forEach(function (el) {
    if (el.closest('.hero')) return;
    if (!el.classList.contains('journey')) el.classList.add('reveal');
    io.observe(el);
  });
} else {
  document.querySelectorAll('.journey').forEach(function (j) { j.classList.add('in'); });
}

// Colour burst wherever you click: we stay black & white, you bring the colour
if (!reduce) document.addEventListener('pointerdown', function (e) {
  for (var k = 0; k < 10; k++) {
    var d = document.createElement('span'), a = Math.random() * Math.PI * 2, r = 30 + Math.random() * 50;
    d.className = 'burst';
    d.style.left = (e.clientX - 5) + 'px'; d.style.top = (e.clientY - 5) + 'px';
    d.style.background = COLORS[k % COLORS.length];
    d.style.setProperty('--x', Math.cos(a) * r + 'px'); d.style.setProperty('--y', Math.sin(a) * r + 'px');
    document.body.appendChild(d);
    setTimeout(d.remove.bind(d), 800);
  }
});

// Case study film
var film = document.getElementById('film');
document.querySelectorAll('[data-film]').forEach(function (b) {
  b.addEventListener('click', function () {
    var v = film.querySelector('video');
    if (v.getAttribute('src') !== b.dataset.film) v.src = b.dataset.film;
    film.showModal(); v.play();
  });
});
if (film) {
  var stop = function () { film.querySelector('video').pause(); };
  film.querySelector('.film-close').addEventListener('click', function () { film.close(); });
  film.addEventListener('close', stop);
  film.addEventListener('click', function (e) { if (e.target === film) film.close(); });
}

// Highlight loops that only play while on screen
document.querySelectorAll('video.inview').forEach(function (v) {
  if (!('IntersectionObserver' in window) || reduce) return;
  new IntersectionObserver(function (entries) {
    entries.forEach(function (e) { if (e.isIntersecting) { var p = v.play(); if (p && p.catch) p.catch(function () {}); } else v.pause(); });
  }, { threshold: 0.25 }).observe(v);
});

// Friendly offline page for returning visitors (see sw.js)
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
  window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
}
