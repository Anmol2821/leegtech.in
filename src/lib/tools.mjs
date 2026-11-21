import { site } from '../data/site.mjs';
import { niceClasses } from '../data/nice-classes.mjs';
import { esc, icon } from './util.mjs';
import { breadcrumbs, secHead, faqList, ctaBand } from './components.mjs';
import { orgNode, websiteNode, crumbsNode, faqNode, pageNode, graph } from './schema.mjs';

const appNode = (name, path, description) => ({
  '@type': 'WebApplication', name, url: site.url + path, description, applicationCategory: 'BusinessApplication',
  operatingSystem: 'Any (web browser)', isAccessibleForFree: true, inLanguage: 'en-IN',
  offers: { '@type': 'Offer', price: '0', priceCurrency: 'INR' }, provider: { '@id': `${site.url}/#organization` }
});

export const tools = [
  { slug: 'trademark-class-finder', name: 'Trademark Class Finder', icon: 'tag', short: 'Type your product or service and see which of the 45 trademark classes it is likely to fall in.' },
  { slug: 'patent-deadline-calculator', name: 'Patent Deadline Calculator', icon: 'clock', short: 'Enter your priority date and see key Indian patent and PCT deadlines: 12, 18 and 31 months, FER reply, renewals and term.' }
];

export const toolCards = () => `<div class="tool-grid">${tools.map(t => `<a class="tool-card" href="/tools/${t.slug}/"><span class="svc-ico">${icon(t.icon)}</span><div><h3>${esc(t.name)}</h3><p>${esc(t.short)}</p><span class="svc-more">Open free tool ${icon('arrow')}</span></div></a>`).join('')}</div>`;

/* ---------------- HUB ---------------- */
export function toolsHub() {
  const path = '/tools/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Free tools', path }];
  const p = { path, title: 'Free IP Tools: Trademark Classes & Patent Deadlines', description: 'Free tools from Leegtech: find the right trademark class for your brand and calculate key Indian patent and PCT deadlines from your priority date.', section: 'tools' };
  p.schema = graph(orgNode(), websiteNode(), pageNode({ ...p, title: 'Free IP tools' }, 'CollectionPage'), crumbsNode(crumbs), tools.map(t => appNode(t.name, `/tools/${t.slug}/`, t.short)));
  const body = `
<section class="page-hero">
  <div class="container">
    ${breadcrumbs(crumbs)}
    <h1>Free IP Tools</h1>
    <p class="lead">Quick, free tools to help you plan trademark and patent filings in India. No sign-up needed.</p>
  </div>
</section>
<section class="section section-top0"><div class="container">${toolCards()}</div></section>
${ctaBand('Need expert help?', 'Our team can confirm classes, deadlines and the right filing strategy for your case. Free initial consultation.')}`;
  return { p, body };
}

/* ---------------- TRADEMARK CLASS FINDER ---------------- */
export function classFinder() {
  const path = '/tools/trademark-class-finder/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Free tools', path: '/tools/' }, { name: 'Trademark Class Finder', path }];
  const faqs = [
    ['How many trademark classes are there in India?', 'India follows the Nice Classification, which has 45 classes: Classes 1 to 34 cover goods and Classes 35 to 45 cover services.', '/blog/how-to-choose-trademark-class-india/'],
    ['Can one trademark application cover more than one class?', 'Yes. Indian law allows a single application covering multiple classes, with the official fee payable for each class. Separate applications per class are also common.', '/trademark-copyright/trademark-registration/'],
    ['Is this tool\'s result final?', 'No. It gives an indicative starting point. The exact class depends on how your goods or services are described, and the Trade Marks Registry decides classification. A professional search confirms classes and conflicts.', '/trademark-copyright/trademark-search/']
  ];
  const p = { path, title: 'Trademark Class Finder: Find Your Trademark Class (India)', description: 'Free tool: type your product or service and find which of the 45 Nice trademark classes it likely falls in. Includes the full list of classes with examples.', section: 'tools' };
  p.schema = graph(orgNode(), websiteNode(), pageNode({ ...p, title: 'Trademark Class Finder' }), crumbsNode(crumbs), appNode('Trademark Class Finder', path, p.description), faqNode(faqs, path), {
    '@type': 'ItemList', name: 'Nice Classification: 45 trademark classes',
    itemListElement: niceClasses.map(([n, t]) => ({ '@type': 'ListItem', position: n, name: `Class ${n}: ${t}`, url: `${site.url}${path}#class-${n}` }))
  });
  const card = ([n, t, items]) => `<article class="nc" id="class-${n}" data-n="${n}"><span class="nc-n${n >= 35 ? ' nc-s' : ''}">${n}</span><div><h3>Class ${n}: ${esc(t)}</h3><p>${esc(items)}</p></div></article>`;
  const body = `
<section class="page-hero">
  <div class="container narrow">
    ${breadcrumbs(crumbs)}
    <p class="eyebrow">Free tool</p>
    <h1>Trademark Class Finder</h1>
    <p class="lead">Type what you sell or the service you provide. We'll show the trademark classes it most likely falls in, based on the Nice Classification used in India.</p>
    <div class="tool-box">
      <label for="nc-q" class="tool-label">What does your brand sell or do?</label>
      <div class="tool-row"><input id="nc-q" type="search" placeholder="e.g. organic tea, mobile app, restaurant, clothing" autocomplete="off"><button class="btn btn-primary" id="nc-go" type="button">Find classes ${icon('arrow')}</button></div>
      <div id="nc-out" class="tool-out" aria-live="polite"></div>
      <p class="tool-note">${icon('shield')} Indicative only. The exact class depends on how goods and services are described, and the Trade Marks Registry decides classification. Official fees are charged per class at actuals.</p>
    </div>
  </div>
</section>

<section class="section section-top0">
  <div class="container narrow">
    <h2>All 45 trademark classes</h2>
    <p class="muted">Classes 1–34 are goods (orange). Classes 35–45 are services (green).</p>
    <div class="nc-list">${niceClasses.map(card).join('')}</div>
  </div>
</section>

<section class="section section-soft">
  <div class="container narrow">
    ${secHead('FAQ', 'Trademark classes: common questions')}
    ${faqList(faqs)}
  </div>
</section>
${ctaBand('Get your classes confirmed', 'We run a trademark search in the right classes and file your application. Free initial consultation.')}
<script>
(function(){
  var C=${JSON.stringify(niceClasses.map(([n, t, items, kw]) => [n, t, items, kw]))};
  var q=document.getElementById('nc-q'),out=document.getElementById('nc-out'),go=document.getElementById('nc-go');
  function norm(s){return s.toLowerCase().replace(/[^a-z0-9\\- ]+/g,' ');}
  function run(){
    var words=norm(q.value).split(/\\s+/).filter(function(w){return w.length>1&&['and','the','for','of','my','our','a','an','in','to','with','we','i'].indexOf(w)<0;});
    if(!words.length){out.innerHTML='';return;}
    var res=C.map(function(c){var hay=' '+norm(c[3]+' '+c[1]+' '+c[2])+' ';var s=0;words.forEach(function(w){var stem=w.replace(/(ies|es|s)$/,'');if(hay.indexOf(' '+w+' ')>-1)s+=3;else if(stem.length>3&&hay.indexOf(' '+stem)>-1)s+=2;});return [s,c];})
      .filter(function(r){return r[0]>0;}).sort(function(a,b){return b[0]-a[0];});
    if(res.length){var top=res[0][0];res=res.filter(function(r){return r[0]>=top*0.6;}).slice(0,5);}
    if(!res.length){out.innerHTML='<p>No direct match found. Try a simpler word (for example "clothing", "software", "tea") or browse the full list below, or <a href="/contact/">ask us on WhatsApp</a>.</p>';return;}
    out.innerHTML='<p class="tool-res-h">Likely classes for <strong>'+q.value.replace(/[<>&"]/g,'')+'</strong>:</p>'+res.map(function(r,i){var c=r[1];return '<a class="nc nc-hit" href="'+'#class-'+c[0]+'"><span class="nc-n'+(c[0]>=35?' nc-s':'')+'">'+c[0]+'</span><div><h3>Class '+c[0]+': '+c[1]+(i===0?' <em>best match</em>':'')+'</h3><p>'+c[2]+'</p></div></a>';}).join('')+'<p class="tool-res-f">Selling online? You may also need <a href="#class-35">Class 35</a> (retail services). Software businesses usually need both <a href="#class-9">Class 9</a> and <a href="#class-42">Class 42</a>.</p>';
    if(window.dataLayer)dataLayer.push({event:'tool_use',tool:'trademark_class_finder'});
  }
  go.addEventListener('click',run);q.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();run();}});
  var t;q.addEventListener('input',function(){clearTimeout(t);t=setTimeout(run,250);});
})();
</script>`;
  return { p, body };
}

/* ---------------- PATENT DEADLINE CALCULATOR ---------------- */
export function patentCalc() {
  const path = '/tools/patent-deadline-calculator/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Free tools', path: '/tools/' }, { name: 'Patent Deadline Calculator', path }];
  const faqs = [
    ['When must the complete specification be filed after a provisional application?', 'Within 12 months from the date of filing the provisional application (Section 9(1), Patents Act, 1970). Otherwise the application is treated as abandoned.', '/patent/provisional-patent-application/'],
    ['What is the deadline for the request for examination in India?', 'Under the Patents (Amendment) Rules, 2024, the request for examination is due within 31 months from the priority date. Applications filed before 15 March 2024 were subject to a 48-month period; check the rule that applies to your application.', '/patent/patent-registration/'],
    ['When is the PCT national phase deadline for India?', 'National phase entry in India is due within 31 months from the priority date. Most other countries have a 30- or 31-month deadline.', '/patent/pct-application/'],
    ['How long do I have to reply to a First Examination Report?', 'Six months from the date of the FER, extendable by up to three months on request.', '/patent/fer-response/']
  ];
  const p = { path, title: 'Patent Deadline Calculator (India & PCT)', description: 'Free calculator: enter your priority date to see key Indian patent and PCT deadlines, from complete specification and examination to national phase and term.', section: 'tools' };
  p.schema = graph(orgNode(), websiteNode(), pageNode({ ...p, title: 'Patent Deadline Calculator' }), crumbsNode(crumbs), appNode('Patent Deadline Calculator', path, p.description), faqNode(faqs, path));
  const body = `
<section class="page-hero">
  <div class="container narrow">
    ${breadcrumbs(crumbs)}
    <p class="eyebrow">Free tool</p>
    <h1>Patent Deadline Calculator</h1>
    <p class="lead">Enter your dates to see the key deadlines for an Indian patent application and international (PCT/Paris Convention) filings.</p>
    <div class="tool-box">
      <div class="tool-fields">
        <div class="field"><label for="pd-pri">Priority date <span class="opt">(your first filing, e.g. Indian provisional)</span></label><input id="pd-pri" type="date"></div>
        <div class="field"><label for="pd-file">Date of filing of the Indian application <span class="opt">(optional: only if different from the priority date, e.g. a convention application; for a PCT application, the international filing date)</span></label><input id="pd-file" type="date"></div>
        <div class="field"><label for="pd-fer">Date of First Examination Report <span class="opt">(optional)</span></label><input id="pd-fer" type="date"></div>
      </div>
      <button class="btn btn-primary" id="pd-go" type="button">Calculate deadlines ${icon('arrow')}</button>
      <div id="pd-out" class="tool-out" aria-live="polite"></div>
      <p class="tool-note">${icon('shield')} Indicative only and not legal advice. Deadlines depend on the facts of your application and the rules in force; some periods can be extended and others cannot. If a deadline falls on a day the office is closed, special rules may apply. Always confirm with a professional before relying on a date.</p>
    </div>
  </div>
</section>

<section class="section section-top0">
  <div class="container narrow prose">
    <h2>How the deadlines are worked out</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Deadline</th><th>Period</th><th>Basis</th></tr></thead>
      <tbody>
        <tr><td>Complete specification after a provisional</td><td>12 months from the provisional filing date</td><td>Section 9(1), Patents Act, 1970</td></tr>
        <tr><td>Filing abroad with priority (Paris Convention or PCT)</td><td>12 months from the priority date</td><td>Paris Convention; PCT</td></tr>
        <tr><td>Publication (ordinary)</td><td>18 months from the priority date, or earlier on request</td><td>Section 11A</td></tr>
        <tr><td>Request for examination</td><td>31 months from the priority date</td><td>Patents (Amendment) Rules, 2024</td></tr>
        <tr><td>PCT national phase in India</td><td>31 months from the priority date</td><td>PCT; Patents Rules</td></tr>
        <tr><td>Reply to First Examination Report</td><td>6 months from the FER, extendable by up to 3 months</td><td>Patents Rules, 2003 (as amended)</td></tr>
        <tr><td>Patent term</td><td>20 years from the date of filing of the application (for an application that began as a provisional, the provisional filing date; for a PCT application, the international filing date)</td><td>Section 53</td></tr>
        <tr><td>First renewal (3rd year)</td><td>Due before the end of the 2nd year from the date of patent; arrears become payable after grant</td><td>Patents Rules, 2003</td></tr>
      </tbody>
    </table></div>
    <p>Residents of India must also comply with Section 39 (foreign filing permission) before filing abroad. Learn more about <a href="/patent/pct-application/">PCT applications</a> and <a href="/patent/provisional-patent-application/">provisional applications</a>.</p>
  </div>
</section>

<section class="section section-soft">
  <div class="container narrow">
    ${secHead('FAQ', 'Patent deadlines: common questions')}
    ${faqList(faqs)}
  </div>
</section>
${ctaBand('Never miss a patent deadline', 'We docket every statutory date and handle filings end to end. Free initial consultation.')}
<script>
(function(){
  function parse(v){if(!v)return null;var p=v.split('-');return new Date(Date.UTC(+p[0],+p[1]-1,+p[2]));}
  function addM(d,m){var y=d.getUTCFullYear(),mo=d.getUTCMonth()+m,day=d.getUTCDate();var last=new Date(Date.UTC(y,mo+1,0)).getUTCDate();return new Date(Date.UTC(y,mo,Math.min(day,last)));}
  function fmt(d){return d.toLocaleDateString('en-IN',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'});}
  var today=new Date();today=new Date(Date.UTC(today.getFullYear(),today.getMonth(),today.getDate()));
  function row(label,d,note){var past=d<today;return '<tr class="'+(past?'pd-past':'')+'"><td>'+label+'</td><td><strong>'+fmt(d)+'</strong>'+(past?' <span class="pd-tag">passed</span>':'')+'</td><td>'+note+'</td></tr>';}
  document.getElementById('pd-go').addEventListener('click',function(){
    var pri=parse(document.getElementById('pd-pri').value),fil=parse(document.getElementById('pd-file').value),fer=parse(document.getElementById('pd-fer').value),out=document.getElementById('pd-out');
    if(!pri){out.innerHTML='<p class="form-status bad">Please enter the priority date.</p>';return;}
    var base=fil||pri,r='';
    r+=row('Complete specification (if the priority filing is an Indian provisional)',addM(pri,12),'12 months; cannot be extended');
    r+=row('Last date to file abroad claiming priority (Paris Convention / PCT)',addM(pri,12),'12 months from priority');
    r+=row('Ordinary publication',addM(pri,18),'Or earlier if early publication is requested');
    r+=row('Request for examination',addM(pri,31),'31 months (applications filed before 15 March 2024: 48 months)');
    r+=row('PCT national phase: India',addM(pri,31),'Most other countries: 30 or 31 months');
    if(fer){r+=row('Reply to FER',addM(fer,6),'Extendable up to '+fmt(addM(fer,9))+' on request');}
    r+=row('First renewal (3rd year) falls due',addM(base,24),'Payable once granted; earlier years become payable after grant');
    r+=row('Patent term ends (if granted and renewed)',addM(base,240),'20 years from the date of filing of the Indian application'+(fil?'':' (taken as the priority date)'));
    out.innerHTML='<div class="table-wrap"><table class="pd-table"><thead><tr><th>Deadline</th><th>Date</th><th>Note</th></tr></thead><tbody>'+r+'</tbody></table></div><p class="tool-res-f">Want these dates tracked for you? <a href="/contact/">Talk to us on WhatsApp</a>.</p>';
    if(window.dataLayer)dataLayer.push({event:'tool_use',tool:'patent_deadline_calculator'});
  });
})();
</script>`;
  return { p, body };
}
