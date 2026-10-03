# overraide

The developmental weblog (40% of CIN506) for my experimental film, published as **overraide**. An Astro static site with a dark minimal theme, deployed to GitHub Pages.

> This repo is **public**. The film's private project folder (notes, tools, input logs, footage) stays in its own private folder and must never be copied in here.

## Write a post

```
npm run new -- proposal "Proposal"
npm run new -- technical-test "Gaussian splat test"
npm run new -- film-review "Man with a Movie Camera"
npm run new -- reading-notes "O'Pray: Avant-Garde Film"
npm run new -- shoot-day "Shoot day 1"
npm run new -- reflection "Final reflection"
```

Each command creates `src/content/posts/YYYY-MM-DD-<slug>.md` from `templates/`, dated today, as `draft: true`. Drafts show in `npm run dev` but are not published. Set `draft: false` when it's ready. The `date:` is the publish date shown on the post, so keep it honest.

| Type | Required fields (the build fails without them) |
|---|---|
| film-review | `film: { title, director, year, country, ref }` |
| reading-notes | `reading: <reference id>` |
| shoot-day | optional `day`, `setups` |

## References (Harvard)

All references live in **`src/data/references.yml`**, keyed by an id. Posts point at them:
- `refs: [robertson-2023, eye-machine]` lists them under the post
- `film.ref` / `reading` link the film or text being discussed

The **References** page is generated from the same file. The seeded entries come from the project notes and are marked `verified: false`. Check each one against the real source, fix it, then set `verified: true`. Unverified entries show a red ⚠ in `npm run dev` only.

## Images and video

Put files in `public/media/` and write paths starting with `/media/…`. They are fixed up automatically for the GitHub Pages sub-path.

```html
![Splat training](/media/splat-training.jpg)

<figure>
  <video src="/media/test.mp4" controls muted playsinline></video>
  <figcaption>Caption</figcaption>
</figure>

<div class="embed"><iframe src="https://player.vimeo.com/video/ID" allowfullscreen></iframe></div>
```
Keep big videos on Vimeo or YouTube rather than in the repo.

## Run locally

```
npm install
npm run dev        # http://localhost:4321
npm run build      # check it builds before pushing
```

## Publish (first time)

1. Create the GitHub repo `overraide`.
2. In this folder: `git init`, commit, add the remote, push to `main`.
3. On GitHub: Settings → Pages → Source: **GitHub Actions**.

Every push to `main` then builds and deploys via `.github/workflows/deploy.yml` to `https://kikis118.github.io/overraide/`.
