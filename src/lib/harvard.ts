// Harvard (Cite Them Right style) formatting. Returns HTML (titles in italics).

export type Ref = {
  kind: "book" | "chapter" | "article" | "film" | "web" | "artwork";
  authors: string[]; year: number | string; title: string;
  container?: string; editors?: string[]; volume?: number | string; issue?: number | string;
  pages?: string; publisher?: string; place?: string; country?: string; medium?: string;
  url?: string; doi?: string; accessed?: string; verified?: boolean;
};

const esc = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

export function names(list: string[] = []): string {
  if (list.length === 0) return "";
  if (list.length === 1) return esc(list[0]);
  if (list.length > 3) return `${esc(list[0])} <i>et al.</i>`;
  return list.slice(0, -1).map(esc).join(", ") + " and " + esc(list[list.length - 1]);
}

export function harvard(r: Ref): string {
  const who = names(r.authors);
  const yr = `(${esc(String(r.year))})`;
  const pub = [r.place, r.publisher].filter(Boolean).map((s) => esc(s!)).join(": ");
  const link = r.doi ? ` doi:${esc(r.doi)}.` : r.url ? ` Available at: ${esc(r.url)}${r.accessed ? ` (Accessed: ${esc(r.accessed)})` : ""}.` : "";
  switch (r.kind) {
    case "book":
      return `${who} ${yr} <i>${esc(r.title)}</i>.${pub ? ` ${pub}.` : ""}${link}`;
    case "chapter":
      return `${who} ${yr} '${esc(r.title)}', in ${r.editors ? `${names(r.editors)} (ed${r.editors.length > 1 ? "s" : ""}.) ` : ""}<i>${esc(r.container ?? "")}</i>.${pub ? ` ${pub}` : ""}${r.pages ? `, pp. ${esc(r.pages)}` : ""}.${link}`;
    case "article": {
      const vol = r.volume ? `, ${esc(String(r.volume))}${r.issue ? `(${esc(String(r.issue))})` : ""}` : "";
      return `${who} ${yr} '${esc(r.title)}', <i>${esc(r.container ?? "")}</i>${vol}${r.pages ? `, pp. ${esc(r.pages)}` : ""}.${link}`;
    }
    case "film":
      return `<i>${esc(r.title)}</i> ${yr} Directed by ${names(r.authors.map(forename))}. [${esc(r.medium ?? "Film")}]${r.country ? ` ${esc(r.country)}` : ""}${r.publisher ? `: ${esc(r.publisher)}` : ""}.${link}`;
    case "artwork":
      return `${who} ${yr} <i>${esc(r.title)}</i> [${esc(r.medium ?? "Artwork")}].${r.container ? ` ${esc(r.container)}.` : ""}${link}`;
    case "web":
      return `${who || `<i>${esc(r.container ?? "")}</i>`} ${yr} <i>${esc(r.title)}</i>.${link}`;
  }
}

// "Vertov, D." -> "D. Vertov" (films credit the director forename-first)
export function forename(n: string): string {
  const [surname, initials] = n.split(",").map((x) => x.trim());
  return initials ? `${initials} ${surname}` : n;
}

// In-text citation, e.g. (Robertson et al., 2023)
export function citeKey(r: Ref): string {
  const a = r.authors;
  const surname = (s: string) => s.split(",")[0];
  const who = a.length === 0 ? r.title : a.length === 1 ? surname(a[0]) : a.length === 2 ? `${surname(a[0])} and ${surname(a[1])}` : `${surname(a[0])} et al.`;
  return `${who}, ${r.year}`;
}

export function sortKey(r: Ref): string {
  return ((r.authors[0] ?? r.title) + " " + r.year).toLowerCase();
}
