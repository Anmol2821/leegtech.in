// Link + SEO checks over dist/
import fs from 'node:fs'; import path from 'node:path';
const D = process.argv[2] || 'dist'; const files = [];
const walk = d => fs.readdirSync(d, { withFileTypes: true }).forEach(f => { const p = path.join(d, f.name); f.isDirectory() ? walk(p) : p.endsWith('.html') && files.push(p); });
walk(D);
for (let i = files.length - 1; i >= 0; i--) if (/[\/]google[0-9a-f]+\.html$/.test(files[i])) files.splice(i, 1); // search-console verification files
const exists = (u) => { u = u.split('#')[0].split('?')[0]; if (!u) return true; const p = path.join(D, u); return fs.existsSync(p) && (fs.statSync(p).isFile() || fs.existsSync(path.join(p, 'index.html'))); };
const problems = []; const titles = {}; const descs = {}; const ids = new Map();
for (const f of files) {
  const h = fs.readFileSync(f, 'utf8'); const rel = '/' + path.relative(D, f).replace(/index\.html$/, '');
  for (const m of h.matchAll(/(?:href|src)="(\/[^"]*)"/g)) if (!exists(m[1])) problems.push(`broken ${rel} -> ${m[1]}`);
  for (const m of h.matchAll(/srcset="([^"]*)"/g)) for (const part of m[1].split(',')) { const u = part.trim().split(' ')[0]; if (!exists(u)) problems.push(`broken srcset ${rel} -> ${u}`); }
  for (const m of h.matchAll(/href="#([^"]+)"/g)) if (!h.includes(`id="${m[1]}"`)) problems.push(`anchor ${rel} #${m[1]}`);
  const t = (h.match(/<title>([^<]*)<\/title>/) || [])[1]; const d = (h.match(/name="description" content="([^"]*)"/) || [])[1];
  const h1 = (h.match(/<h1/g) || []).length; if (h1 !== 1) problems.push(`h1 count ${h1} ${rel}`);
  if (!h.includes('noindex')) { (titles[t] ||= []).push(rel); (descs[d] ||= []).push(rel); if (t.length > 70) problems.push(`long title (${t.length}) ${rel}: ${t}`); if (d.length > 165 || d.length < 70) problems.push(`desc len ${d.length} ${rel}`); }
  for (const m of h.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) { try { JSON.parse(m[1]); } catch (e) { problems.push('bad jsonld ' + rel); } }
  const idl = [...h.matchAll(/ id="([^"]+)"/g)].map(x => x[1]); const dup = idl.filter((x, i) => idl.indexOf(x) !== i); if (dup.length) problems.push(`dup ids ${rel}: ${[...new Set(dup)]}`);
  for (const m of h.matchAll(/<img [^>]*>/g)) if (!/ alt="/.test(m[0])) problems.push(`img no alt ${rel}`);
}
for (const [t, l] of Object.entries(titles)) if (l.length > 1) problems.push(`dup title "${t}": ${l}`);
for (const [t, l] of Object.entries(descs)) if (l.length > 1) problems.push(`dup desc: ${l}`);
console.log(files.length + ' html files'); console.log(problems.join('\n') || 'NO PROBLEMS');
