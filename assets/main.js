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

// Contact form: opens the visitor's email app with the details filled in
var form = document.getElementById('contact-form');
if (form) form.addEventListener('submit', function (e) {
  e.preventDefault();
  var d = new FormData(form);
  var body = ['Name: ' + d.get('name'), 'Email: ' + d.get('email'), 'Company: ' + d.get('company'),
    'Team size: ' + d.get('size'), 'Interested in: ' + d.get('interest'), '', d.get('message')].join('\n');
  location.href = 'mailto:' + form.dataset.email + '?subject=' + encodeURIComponent('Enquiry from ' + d.get('company')) +
    '&body=' + encodeURIComponent(body);
});
