"""Spell-check visible text in dist/. Usage: python3 scripts/qa/spellcheck.py [path-filter]
Words in scripts/qa/words.txt (proper nouns, legal terms, Indian/British spellings) are ignored.
Requires: pip install pyspellchecker"""
import re, glob, html, os, sys, collections
from spellchecker import SpellChecker
HERE = os.path.dirname(os.path.abspath(__file__))
D = os.path.join(HERE, '..', '..', 'dist')
sp = SpellChecker()
sp.word_frequency.load_words([w.strip().lower() for w in open(os.path.join(HERE, 'words.txt')) if w.strip()])
flt = sys.argv[1] if len(sys.argv) > 1 else ''
cnt = collections.Counter(); where = {}
for f in glob.glob(D + '/**/*.html', recursive=True):
    if flt and flt not in f: continue
    if re.search(r'google[0-9a-f]+\.html$', f): continue  # search-console verification file
    s = open(f).read()
    s = re.sub(r'<script[\s\S]*?</script>|<style[\s\S]*?</style>|<svg[\s\S]*?</svg>|<header class="site-header"[\s\S]*?</header>|<footer class="site-footer"[\s\S]*?</footer>', ' ', s)
    t = html.unescape(re.sub(r'<[^>]+>', ' ', s))
    for w in re.findall(r"[A-Za-z][A-Za-z'’-]*[A-Za-z]", t):
        for part in re.split(r"[-’']", w):
            if len(part) < 3 or part.isupper() or part.lower() in sp: continue
            cnt[part] += 1; where.setdefault(part, os.path.relpath(f, D))
for w, n in sorted(cnt.items(), key=lambda x: -x[1]): print(n, w, where[w])
print('UNKNOWN WORDS:', len(cnt))
