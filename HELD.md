# Held back while the log is a work in progress

The public site currently shows a bare home page and the proposal (`/proposal/`), and asks search engines not to index it.
Everything else is built and kept in the repo, just not routed:

- `/film/`, `/marking/` and `/references/` are live but unlisted: not in any menu or link, and not indexed. Only people with the URL reach them.
- `src/pages/_held/` holds the full home page (post list) and About, which are not routed at all.
- Posts in `src/content/posts/` are drafts (`draft: true`), so none are published.

## To launch (tell Claude "go", or do it by hand)
1. Delete the holding `src/pages/index.astro`, then `git mv src/pages/_held/* src/pages/` and change `"../../` back to `"../` in the imports of index.astro and about.astro.
2. Set `LAUNCHED = true` in `src/site.config.ts` (shows the nav, removes noindex).
3. Delete `public/robots.txt`.
4. Set the posts you want public to `draft: false`, and check the references are verified.
