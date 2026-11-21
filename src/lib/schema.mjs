import { site } from '../data/site.mjs';
import { allServices } from '../data/categories.mjs';

const ORG = `${site.url}/#organization`;
const WEB = `${site.url}/#website`;
const FOUNDER = `${site.url}/about/#founder`;

export function orgNode(full = false) {
  const sameAs = Object.values(site.social).filter(Boolean);
  const node = {
    '@type': ['Organization', 'ProfessionalService'],
    '@id': ORG,
    name: site.name,
    legalName: site.legalName,
    alternateName: 'LeegTech',
    url: site.url + '/',
    logo: { '@type': 'ImageObject', url: `${site.url}/icon-512.png`, width: 512, height: 512 },
    image: `${site.url}/og/default.jpg`,
    description: site.description,
    slogan: site.slogan,
    email: site.email,
    telephone: site.phone,
    address: {
      '@type': 'PostalAddress',
      streetAddress: site.address.street,
      addressLocality: site.address.city,
      addressRegion: site.address.region,
      postalCode: site.address.postalCode,
      addressCountry: site.address.country
    },
    areaServed: { '@type': 'Country', name: 'India' },
    founder: { '@id': FOUNDER },
    contactPoint: [{
      '@type': 'ContactPoint', telephone: site.phone, email: site.email, contactType: 'customer service',
      areaServed: 'IN', availableLanguage: ['English']
    }],
    knowsAbout: ['Patents', 'Trademarks', 'Copyright', 'Industrial design registration', 'PCT applications', 'Madrid Protocol', 'Company registration in India', 'GST registration', 'Startup India', 'Legal agreements'],
  };
  if (full) node.hasOfferCatalog = {
    '@type': 'OfferCatalog', name: 'IP, legal and business services',
    itemListElement: allServices.map(s => ({ '@type': 'Offer', itemOffered: { '@type': 'Service', name: s.name, url: site.url + s.url } }))
  };
  if (site.hours) node.openingHours = site.hours;
  if (sameAs.length) node.sameAs = sameAs;
  return node;
}

export const websiteNode = () => ({
  '@type': 'WebSite', '@id': WEB, url: site.url + '/', name: site.name, inLanguage: 'en-IN', publisher: { '@id': ORG }
});

export const founderNode = () => ({
  '@type': 'Person', '@id': FOUNDER, name: site.founder.name, jobTitle: 'Founder', worksFor: { '@id': ORG },
  image: `${site.url}/assets/img/founder.webp`
});

export const crumbsNode = (items) => ({
  '@type': 'BreadcrumbList',
  itemListElement: items.map((it, i) => ({ '@type': 'ListItem', position: i + 1, name: it.name, item: site.url + it.path }))
});

export const faqNode = (faqs, path) => ({
  '@type': 'FAQPage', '@id': `${site.url}${path}#faq`,
  mainEntity: faqs.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } }))
});

export const pageNode = (p, type = 'WebPage') => ({
  '@type': type, '@id': `${site.url}${p.path}#webpage`, url: site.url + p.path, name: p.title || site.name,
  description: p.description, isPartOf: { '@id': WEB }, about: { '@id': ORG }, inLanguage: 'en-IN',
  ...(p.dateModified ? { dateModified: p.dateModified } : {}),
  ...(p.primaryImage ? { primaryImageOfPage: { '@type': 'ImageObject', url: site.url + p.primaryImage } } : {})
});

export const serviceNode = (s, cat) => ({
  '@type': 'Service', '@id': `${site.url}${s.url}#service`, name: s.name, serviceType: s.name,
  description: s.metaDesc, url: site.url + s.url, provider: { '@id': ORG }, areaServed: { '@type': 'Country', name: 'India' },
  category: cat.name, audience: { '@type': 'BusinessAudience', name: 'Startups, businesses, inventors and creators' }
});

export const articleNode = (post) => ({
  '@type': 'BlogPosting', '@id': `${site.url}/blog/${post.slug}/#article`, headline: post.title, description: post.description,
  datePublished: post.date, dateModified: post.updated, inLanguage: 'en-IN', url: `${site.url}/blog/${post.slug}/`,
  mainEntityOfPage: { '@id': `${site.url}/blog/${post.slug}/#webpage` }, author: { '@id': ORG }, publisher: { '@id': ORG },
  image: `${site.url}/og/default.jpg`, wordCount: post.words
});

export const graph = (...nodes) => ({ '@context': 'https://schema.org', '@graph': nodes.flat().filter(Boolean) });
