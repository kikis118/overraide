# overraide: PHP edition (editable on the site)

The same weblog as the Astro site, served by PHP so you can edit it from the site itself.
Content lives as Markdown files in `app/content/` (same front matter as the Astro posts).

## What you get
- Visitors see the normal site. Drafts are invisible to them.
- **Sign in** at `/admin/` (footer link "Admin") with a PIN. Every page then has an admin bar:
  *Edit this page*, *New post*, *All content*, *Sign out*.
- Edit posts (title, type, date, summary, draft, references, text), the film / marking / about pages and the
  reference list. Upload images from the editor, preview before saving.
- Saves are validated like the Astro build (unknown reference ids, film reviews without a `film:` block and bad dates are refused).
- Every overwrite or delete keeps a copy in `app/content/.history/` (last 25 per file).
- Security: PIN stored only as a hash, sessions are HttpOnly + SameSite=Strict, CSRF token on every write,
  5 wrong PINs lock that IP for 15 minutes, uploads limited to real images.

## Run it locally
```
php setup.php                                     # choose a PIN (6+ characters)
php -S 127.0.0.1:8099 -t public public/index.php  # from this folder
php tests.php                                     # parser / renderer checks
```

## Put it on a host (PHP 8.0+, Apache/LiteSpeed such as Hostinger)
1. Upload `public/*` to the web root (`public_html/`, or a sub-folder) and the `app/` folder next to it
   (outside the web root is best; inside works too, `app/.htaccess` blocks downloads).
2. Make `app/content`, `app/data` and `public/media` writable (775).
3. Run `php setup.php` on the server, or run it locally and upload the generated `app/config.php`.
4. Point the domain at it and enable HTTPS.

## Relationship to the GitHub Pages site
The Astro site stays on GitHub Pages until the domain moves. After the move the PHP edition is the single source of
truth: don't edit in both. To go back, copy `app/content/posts/*.md`, `app/content/pages/*` and
`app/content/references.yml` into `src/content/posts/`, `src/pages/` and `src/data/`.
