// The Play Room: three small team games. Nobody wins alone.
var PALETTE = ['#ffd400', '#ff7a00', '#ff3d7f', '#2f6bff', '#00c48c'];
function $(id) { return document.getElementById(id); }
function shuffle(a) { for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
function win(el, text) { el.textContent = text; el.hidden = false; }

/* ---------- 01 Light up the team (Lights Out, 4x4) ---------- */
(function () {
  var N = 4, board = $('lo-board'), cells = [], state = [], moves = 0, solution = [];
  for (var i = 0; i < N * N; i++) {
    var b = document.createElement('button');
    b.className = 'lo-cell'; b.style.setProperty('--c', PALETTE[i % PALETTE.length]);
    b.setAttribute('aria-label', 'Team member ' + (i + 1));
    b.addEventListener('click', press.bind(null, i, true));
    board.appendChild(b); cells.push(b);
  }
  function flip(i) {
    var r = Math.floor(i / N), c = i % N;
    [[r, c], [r - 1, c], [r + 1, c], [r, c - 1], [r, c + 1]].forEach(function (p) {
      if (p[0] >= 0 && p[0] < N && p[1] >= 0 && p[1] < N) state[p[0] * N + p[1]] ^= 1;
    });
  }
  function press(i, byPlayer) {
    flip(i);
    // pressing a cell twice cancels out, so the remaining solution is a toggle
    var k = solution.indexOf(i); if (k >= 0) solution.splice(k, 1); else solution.push(i);
    if (byPlayer) { moves++; render(); }
  }
  function render() {
    var lit = 0;
    cells.forEach(function (c, i) { c.classList.toggle('on', !!state[i]); c.classList.remove('hint'); lit += state[i]; c.setAttribute('aria-pressed', !!state[i]); });
    $('lo-moves').textContent = moves; $('lo-lit').textContent = lit;
    if (lit === N * N && moves) win($('lo-win'), 'The whole team is glowing in ' + moves + ' moves. Nobody lit up alone.');
  }
  function reset() {
    state = new Array(N * N).fill(1); solution = []; moves = 0; $('lo-win').hidden = true;
    var picks = shuffle(Array.from({ length: N * N }, function (_, i) { return i; })).slice(0, 5);
    picks.forEach(function (i) { press(i, false); });
    render();
  }
  $('lo-reset').addEventListener('click', reset);
  $('lo-hint').addEventListener('click', function () { if (solution.length) cells[solution[0]].classList.add('hint'); });
  reset();
})();

/* ---------- 02 Team Match (memory) ---------- */
(function () {
  var VALUES = [['🤝', 'Trust'], ['💬', 'Talk'], ['👂', 'Listen'], ['😂', 'Laugh'], ['🙌', 'Cheer'], ['🧩', 'Fit in'], ['🎯', 'Focus'], ['🚀', 'Grow']];
  var board = $('mm-board'), open = [], pairs = 0, moves = 0, start = 0, timer = null, lock = false;
  function reset() {
    board.innerHTML = ''; open = []; pairs = 0; moves = 0; start = 0; lock = false;
    clearInterval(timer); $('mm-time').textContent = '0s'; $('mm-win').hidden = true; update();
    shuffle(VALUES.concat(VALUES).map(function (v, i) { return { v: v, c: PALETTE[VALUES.indexOf(v) % PALETTE.length] }; })).forEach(function (card) {
      var b = document.createElement('button');
      b.className = 'mm-card'; b.dataset.k = card.v[1]; b.style.setProperty('--c', card.c);
      b.setAttribute('aria-label', 'Hidden card');
      b.innerHTML = '<div class="mm-inner"><div class="mm-face mm-back">J.</div><div class="mm-face mm-front"><span class="e">' + card.v[0] + '</span><span class="t">' + card.v[1] + '</span></div></div>';
      b.addEventListener('click', function () { pick(b); });
      board.appendChild(b);
    });
  }
  function update() { $('mm-moves').textContent = moves; $('mm-pairs').textContent = pairs; }
  function pick(b) {
    if (lock || b.classList.contains('flip') || b.classList.contains('done')) return;
    if (!start) { start = Date.now(); timer = setInterval(function () { $('mm-time').textContent = Math.round((Date.now() - start) / 1000) + 's'; }, 500); }
    b.classList.add('flip'); b.setAttribute('aria-label', b.dataset.k); open.push(b);
    if (open.length < 2) return;
    moves++; lock = true;
    var a = open[0], c = open[1]; open = [];
    if (a.dataset.k === c.dataset.k) {
      a.classList.add('done'); c.classList.add('done'); pairs++; lock = false; update();
      if (pairs === VALUES.length) { clearInterval(timer); win($('mm-win'), 'All 8 pairs in ' + moves + ' moves and ' + $('mm-time').textContent + '. Imagine what your whole team could do.'); }
    } else {
      update();
      setTimeout(function () { a.classList.remove('flip'); c.classList.remove('flip'); a.setAttribute('aria-label', 'Hidden card'); c.setAttribute('aria-label', 'Hidden card'); lock = false; }, 800);
    }
  }
  $('mm-reset').addEventListener('click', reset);
  reset();
})();

/* ---------- 03 Team Words (unscramble) ---------- */
(function () {
  var WORDS = shuffle([
    ['TEAM', 'Where I becomes We'], ['TRUST', 'The glue nobody can see'], ['HUDDLE', 'A quick get-together before the big play'],
    ['BONDING', 'What happens on a great offsite'], ['TOGETHER', 'Better than alone'], ['CHEER', 'What you do when a teammate wins'],
    ['UNITY', 'Many people, one direction'], ['SYNERGY', 'One plus one is more than two'], ['HIGHFIVE', 'A celebration with hands'],
    ['BELONG', 'How great culture makes you feel']
  ]);
  var idx = 0, score = 0, picked = [], tiles = [];
  $('tw-total').textContent = WORDS.length;
  function load() {
    var w = WORDS[idx][0], letters;
    do { letters = shuffle(w.split('')); } while (letters.join('') === w);
    $('tw-hint').textContent = 'Clue: ' + WORDS[idx][1];
    $('tw-tiles').innerHTML = ''; $('tw-answer').innerHTML = ''; $('tw-answer').className = 'tw-answer';
    picked = []; tiles = letters.map(function (l) {
      var b = document.createElement('button'); b.className = 'tw-tile'; b.textContent = l;
      b.addEventListener('click', function () { choose(b); });
      $('tw-tiles').appendChild(b); return b;
    });
  }
  function choose(b) {
    if (b.disabled) return;
    b.disabled = true; picked.push(b);
    var a = document.createElement('span'); a.className = 'tw-tile'; a.textContent = b.textContent; $('tw-answer').appendChild(a);
    if (picked.length === tiles.length) check();
  }
  function check() {
    var guess = picked.map(function (b) { return b.textContent; }).join(''), ans = $('tw-answer');
    if (guess === WORDS[idx][0]) {
      ans.classList.add('right'); score++; $('tw-score').textContent = score;
      setTimeout(next, 900);
    } else {
      ans.classList.add('wrong');
      setTimeout(function () { ans.classList.remove('wrong'); while (picked.length) undo(); }, 500);
    }
  }
  function undo() {
    var b = picked.pop(); if (!b) return;
    b.disabled = false; $('tw-answer').lastChild.remove();
  }
  function next() {
    idx++;
    if (idx >= WORDS.length) {
      $('tw-tiles').innerHTML = ''; $('tw-hint').textContent = '';
      win($('tw-win'), score + ' of ' + WORDS.length + ' words solved. That is a team that talks the talk.');
      return;
    }
    load();
  }
  $('tw-undo').addEventListener('click', undo);
  $('tw-skip').addEventListener('click', function () { if (idx < WORDS.length) next(); });
  load();
})();
