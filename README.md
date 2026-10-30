# Joy Stories website

Static site for [joystories.co](https://joystories.co). Plain HTML and CSS, no build step.

- Pages: `index.html`, `team-building.html`, `employee-engagement.html`, `events.html`, `joy-calendar.html`, `about.html`, `contact.html`
- Styles: `assets/style.css` (black & white, colour only as accent)
- Photos: put them in `assets/img/` and replace a `<div class="media">…</div>` placeholder with
  `<img class="photo selective" src="assets/img/name.jpg" alt="…">`.
  Use `selective` for photos already edited so only people's emotions are in colour; without it, photos show in black & white and bloom into colour on hover.
- Hosting: GitHub Pages from `main` (root). Preview: https://edwardeltham.github.io/joystories-website/
- Going live on joystories.co: switch DNS at Hostinger first, then add a `CNAME` file containing `joystories.co` (or set Custom domain in Settings → Pages).
- Journal: posts live in `posts.py` next to the generator (`/mnt/project-files/joystories-website-src/`). Add a dict to `POSTS` and re-run `gen.py`; it builds `journal.html` and `journal-<slug>.html`.
- Joy Calendar download: the PDF lives in `downloads/` (blocked from the web). `joy-calendar.php` swaps a visitor's email for a signed download link, emails them the link, saves them with the newsletter sign-ups and tells Edward. To update the calendar, replace `downloads/the-joy-calendar.pdf` (keep the name). Shared sign-up code is in `newsletter-lib.php`.
