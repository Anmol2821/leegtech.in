// Build, check and deploy the site to Hostinger (leegtech.in), then purge the CDN.
// Usage: node scripts/deploy-hostinger.mjs
// Auth: the Hostinger API token is injected by the environment's network proxy for developers.hostinger.com,
// or set HOSTINGER_API_TOKEN when running elsewhere.
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';

const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const DOMAIN = 'leegtech.in';
const USERNAME = 'u784176160';
const TOKEN = process.env.HOSTINGER_API_TOKEN || 'proxy-injected';
const run = (cmd) => execSync(cmd, { cwd: ROOT, stdio: 'pipe' }).toString();

console.log(run('node build.mjs').trim());
const check = run('node scripts/qa/check-site.mjs').trim().split('\n').pop();
if (check !== 'NO PROBLEMS') { console.error('Site check failed:\n' + run('node scripts/qa/check-site.mjs')); process.exit(1); }

const archive = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'leegtech-')), 'site.tar.gz');
execSync(`tar -czf "${archive}" -C dist .`, { cwd: ROOT });
console.log('Archive', archive, fs.statSync(archive).size, 'bytes');

const transport = new StdioClientTransport({
  command: 'node',
  args: [path.join(ROOT, 'node_modules/hostinger-api-mcp/src/servers/hosting.js')],
  env: { ...process.env, HOSTINGER_API_TOKEN: TOKEN },
  stderr: 'ignore'
});
const client = new Client({ name: 'leegtech-deploy', version: '1.0.0' });
await client.connect(transport);
const res = await client.callTool({ name: 'execute', arguments: { operation: 'hosting_deploy', params: { domain: DOMAIN, type: 'static', path: archive } } }, undefined, { timeout: 900000 });
await client.close();
const text = res.content.map(c => c.text).join('\n');
console.log(text);
if (res.isError || !/"status":"deployed"/.test(text)) { console.error('Deploy failed'); process.exit(1); }

// curl respects the environment's HTTPS proxy (Node's fetch does not)
const purge = execSync(`curl -s --max-time 60 -X DELETE -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/json" https://developers.hostinger.com/api/hosting/v1/accounts/${USERNAME}/websites/${DOMAIN}/cdn/purge`).toString();
console.log('CDN purge:', purge);
console.log('Deployed to https://' + DOMAIN);

// IndexNow: tell Bing/Yandex (and engines that use their index) about all URLs in the sitemap.
try {
  const keyFile = fs.readdirSync(path.join(ROOT, 'src/static')).find(f => /^[0-9a-f]{32}\.txt$/.test(f));
  if (keyFile) {
    const key = keyFile.replace('.txt', '');
    const urls = [...fs.readFileSync(path.join(ROOT, 'dist/sitemap.xml'), 'utf8').matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
    const body = JSON.stringify({ host: DOMAIN, key, keyLocation: `https://${DOMAIN}/${keyFile}`, urlList: urls });
    const bodyFile = path.join(os.tmpdir(), 'indexnow.json'); fs.writeFileSync(bodyFile, body);
    const r = execSync(`curl -s -o /dev/null -w "%{http_code}" --max-time 60 -X POST -H "Content-Type: application/json; charset=utf-8" --data @${bodyFile} https://api.indexnow.org/indexnow`).toString();
    console.log(`IndexNow: submitted ${urls.length} URLs, HTTP ${r} (200/202 = accepted)`);
  }
} catch (e) { console.log('IndexNow skipped:', e.message); }
