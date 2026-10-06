import { defineCollection, reference } from "astro:content";
import { file, glob } from "astro/loaders";
import { z } from "astro/zod";

export const POST_TYPES = {
  proposal: "Proposal",
  "technical-test": "Technical Test",
  "film-review": "Film Review",
  "reading-notes": "Reading Notes",
  "shoot-day": "Shoot Day",
  journal: "Journal",
  reflection: "Reflection",
} as const;

const posts = defineCollection({
  loader: glob({ pattern: "**/*.md", base: "./src/content/posts" }),
  schema: z
    .object({
      title: z.string(),
      type: z.enum(Object.keys(POST_TYPES) as [keyof typeof POST_TYPES, ...(keyof typeof POST_TYPES)[]]),
      date: z.coerce.date(),
      summary: z.string().optional(),
      draft: z.boolean().default(false),
      cover: z.string().optional(),
      // works discussed / cited: ids from src/data/references.yml
      refs: z.array(reference("references")).default([]),
      // Film Review
      film: z
        .object({
          title: z.string(),
          director: z.string(),
          year: z.number(),
          country: z.string(),
          ref: reference("references"),
        })
        .optional(),
      // Reading Notes
      reading: reference("references").optional(),
      // Shoot Day
      day: z.number().optional(),
      setups: z.array(z.string()).optional(),
    })
    .superRefine((p, ctx) => {
      if (p.type === "film-review" && !p.film)
        ctx.addIssue({ code: "custom", message: "film-review posts need a `film:` block (title, director, year, country, ref)" });
      if (p.type === "reading-notes" && !p.reading)
        ctx.addIssue({ code: "custom", message: "reading-notes posts need `reading: <reference id>`" });
    }),
});

const references = defineCollection({
  loader: file("src/data/references.yml"),
  schema: z.object({
    kind: z.enum(["book", "chapter", "article", "film", "web", "artwork"]),
    authors: z.array(z.string()).default([]), // "Surname, I." — for films: the director
    year: z.union([z.number(), z.string()]),
    title: z.string(),
    container: z.string().optional(), // journal / edited book / website
    editors: z.array(z.string()).optional(),
    volume: z.union([z.number(), z.string()]).optional(),
    issue: z.union([z.number(), z.string()]).optional(),
    pages: z.string().optional(),
    publisher: z.string().optional(),
    place: z.string().optional(),
    country: z.string().optional(),
    medium: z.string().optional(), // e.g. "Film", "Video installation"
    url: z.string().optional(),
    doi: z.string().optional(),
    accessed: z.string().optional(),
    verified: z.boolean().default(false), // flip to true once checked against the source
  }),
});

export const collections = { posts, references };
