// npm run new -- <type> "Post title"
// Copies templates/<type>.md to src/content/posts/YYYY-MM-DD-<slug>.md with today's date.
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const types = readdirSync(join(root, "templates")).map((f) => f.replace(/\.md$/, ""));
const [type, ...words] = process.argv.slice(2);
const title = words.join(" ").trim();

if (!types.includes(type) || !title) {
  console.log(`usage: npm run new -- <type> "Title"\ntypes: ${types.join(", ")}`);
  process.exit(1);
}

const date = new Date().toISOString().slice(0, 10);
const slug = title.toLowerCase().normalize("NFKD").replace(/[^\w\s-]/g, "").trim().replace(/[\s_]+/g, "-").slice(0, 60);
const dir = join(root, "src", "content", "posts");
mkdirSync(dir, { recursive: true });
const file = join(dir, `${date}-${slug}.md`);
if (existsSync(file)) {
  console.log(`already exists: ${file}`);
  process.exit(1);
}
const body = readFileSync(join(root, "templates", `${type}.md`), "utf8")
  .replaceAll("{{title}}", title.replaceAll('"', '\\"'))
  .replaceAll("{{date}}", date);
writeFileSync(file, body);
console.log(`created ${file}`);
