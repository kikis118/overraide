import { defineConfig } from "astro/config";

// On GitHub Pages the site lives at https://<user>.github.io/<repo>/.
// The deploy workflow sets SITE and BASE_PATH; locally both fall back to the root.
const base = process.env.BASE_PATH || "/";

// Lets posts write src="/media/clip.mp4" and still work under /<repo>/ on GitHub Pages.
function rehypeBasePath() {
  const prefix = base.replace(/\/$/, "");
  const fix = (v) =>
    typeof v === "string" && prefix && v.startsWith("/") && !v.startsWith("//") && !v.startsWith(prefix + "/") ? prefix + v : v;
  const walk = (node) => {
    if (node.type === "element" && node.properties) {
      for (const key of ["src", "href", "poster"]) node.properties[key] = fix(node.properties[key]);
    }
    node.children?.forEach(walk);
  };
  return walk;
}

export default defineConfig({
  site: process.env.SITE || "http://localhost:4321",
  base,
  trailingSlash: "ignore",
  markdown: {
    shikiConfig: { theme: "github-dark-dimmed" },
    rehypePlugins: [rehypeBasePath],
  },
});
