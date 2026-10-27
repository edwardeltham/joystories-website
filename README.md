# Joy Stories website

Static site for [joystories.co](https://joystories.co). Plain HTML and CSS, no build step.

- Pages: `index.html`, `team-building.html`, `employee-engagement.html`, `events.html`, `joy-calendar.html`, `about.html`, `contact.html`
- Styles: `assets/style.css` (black & white, colour only as accent)
- Photos: put them in `assets/img/` and replace a `<div class="media">…</div>` placeholder with
  `<img class="photo selective" src="assets/img/name.jpg" alt="…">`.
  Use `selective` for photos already edited so only people's emotions are in colour; without it, photos show in black & white and bloom into colour on hover.
- Hosting: GitHub Pages from `main` (root). `CNAME` points the site at joystories.co.
