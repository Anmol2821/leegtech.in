import patent from './services-patent.mjs';
import design from './services-design.mjs';
import tm from './services-tm.mjs';
import business from './services-business.mjs';
import legal from './services-legal.mjs';

export const categories = [
  {
    slug: 'patent',
    name: 'Patent',
    navName: 'Patent',
    label: 'PATENT',
    accent: 'orange',
    image: 'spot-patent',
    imageAlt: 'Illustration of a patented mechanical invention',
    h1: 'Patent Services in India & Abroad',
    metaTitle: 'Patent Registration & Filing Services in India',
    metaDesc: 'Patent search, provisional and complete patent filing, FER replies, hearings, oppositions, renewals and PCT, US and UK filings, handled end to end by Leegtech.',
    tagline: 'Protect Your Invention. Build Without Borders.',
    intro: 'From your first patent search to filing, examination and grant, Leegtech manages the complete patent lifecycle with strategic support at every stage. Whether you are protecting an invention in India or expanding globally, we assist with provisional and complete specifications, PCT applications, international filings, examination responses, oppositions and renewals.',
    points: [
      ['Exclusive rights for 20 years', 'A granted patent lets you stop others making, using, selling or importing your invention in the country of grant, subject to renewals.'],
      ['First-to-file system', 'In India and most countries, the earlier filing date generally wins. Filing early protects your priority.'],
      ['A business asset', 'Patents can be licensed, assigned, used to attract investment and listed as intangible assets.'],
      ['Startup benefits', 'DPIIT-recognised startups and small entities get reduced official fees and can request expedited examination.']
    ],
    services: patent,
    faqs: [
      ['What can be patented in India?', 'An invention that is new, involves an inventive step and is capable of industrial application, and does not fall within the exclusions in Sections 3 and 4 of the Patents Act, 1970 (for example, mere discoveries, abstract theories, methods of medical treatment, and computer programmes per se).'],
      ['How long does a patent last?', '20 years from the date of filing, provided renewal fees are paid on time.'],
      ['Should I file a provisional or a complete application?', 'A provisional application is useful when the invention is still being developed or must be disclosed soon. If the invention is fully developed, filing a complete specification directly can save time.'],
      ['Can I file a patent abroad directly from India?', 'Residents of India must file in India first (and wait six weeks) or obtain foreign filing permission under Section 39 before filing abroad. We handle this for you.']
    ]
  },
  {
    slug: 'design',
    name: 'Design',
    navName: 'Design',
    label: 'DESIGN',
    accent: 'green',
    image: 'why',
    imageAlt: 'Overview of IP and business services offered by Leegtech',
    h1: 'Design Registration Services',
    metaTitle: 'Design Registration in India, UK & Germany',
    metaDesc: 'Protect the look of your product with Indian design registration, UK registered designs and German utility models. Expert filing and prosecution.',
    tagline: 'Your Product\'s Look Is Worth Protecting.',
    intro: 'The appearance of a product often sells it. Design registration protects shape, configuration, pattern and ornamentation so that competitors cannot copy the look of your product. We also help protect technical inventions quickly in Germany through utility models.',
    points: [
      ['Protect appearance', 'Stops others copying the visual design of your product.'],
      ['Up to 15 years in India', '10 years from registration, extendable once by 5 years.'],
      ['File before launch', 'Prior publication anywhere in the world can destroy novelty.'],
      ['International reach', 'Claim priority within 6 months to protect the design abroad.']
    ],
    services: design,
    faqs: [
      ['What is the difference between design registration and copyright?', 'Design registration protects the appearance of an industrial article. Artistic works may be protected by copyright, but copyright in a design capable of registration can be lost once the article is industrially produced beyond a limit without registration. Registration is the safer route for products.'],
      ['Can I register a design that is already on sale?', 'Generally no, because it is no longer new. File before any public disclosure.']
    ]
  },
  {
    slug: 'trademark-copyright',
    name: 'Trademark & Copyright',
    navName: 'Trademark & Copyright',
    label: 'TM & COPYRIGHT',
    accent: 'orange',
    image: 'spot-tm',
    imageAlt: 'Trademark symbol representing brand protection',
    h1: 'Trademark & Copyright Services',
    metaTitle: 'Trademark Registration & Copyright Services in India',
    metaDesc: 'Trademark search, registration, objections, opposition, hearings, renewals, Madrid international filing, and copyright registration in India and Canada.',
    tagline: 'Your Brand. Protected. Recognised.',
    intro: 'Your brand is one of your most valuable business assets. Leegtech helps you protect it, from trademark search and registration to examination, opposition, hearings, enforcement and international protection through the Madrid Protocol. We also register copyright in literary, artistic, musical and software works in India and Canada.',
    points: [
      ['Exclusive brand rights', 'A registered trademark gives you the exclusive right to use the mark for your goods or services across India.'],
      ['Renewable forever', 'Trademark registration lasts 10 years and can be renewed indefinitely.'],
      ['Copyright evidence', 'Copyright registration gives prima facie evidence of authorship and ownership.'],
      ['Global protection', 'The Madrid Protocol lets you extend your Indian mark to 130+ countries.']
    ],
    services: tm,
    faqs: [
      ['Can I use ™ before registration?', 'Yes. ™ can be used once you claim a mark. The ® symbol may be used only after registration.'],
      ['How many classes should I file in?', 'File in every class that covers the goods or services you offer now or plan to offer soon. We map your business to the right classes.'],
      ['Is copyright registration compulsory?', 'No. Copyright exists automatically, but registration provides strong evidence in disputes and transactions.']
    ]
  },
  {
    slug: 'business-tax',
    name: 'Business & Tax',
    navName: 'Business & Tax',
    label: 'BUSINESS & TAX',
    accent: 'green',
    image: 'spot-startup',
    imageAlt: 'Startup founders planning business growth',
    h1: 'Business Registration, Tax & Compliance',
    metaTitle: 'Company Registration, GST, Startup India & Compliance',
    metaDesc: 'Private limited company, LLP, OPC, Section 8, Startup India, MSME, GST, FSSAI, ISO, income tax, TDS and annual ROC compliance services.',
    tagline: 'Start. Register. Grow. Stay Compliant.',
    intro: 'Complete support for business registration, tax, licensing, certification and compliance, all under one roof. From incorporating your company to DPIIT recognition, GST and annual filings, we keep your business on a solid legal foundation so you can focus on growth.',
    points: [
      ['Right structure from day one', 'Choose between Pvt Ltd, LLP, OPC or Section 8 based on funding, liability and compliance.'],
      ['Unlock benefits', 'Startup India and MSME registrations give access to fee rebates and government schemes.'],
      ['Stay compliant', 'Avoid daily late fees and director disqualification with a compliance calendar.'],
      ['IP and business together', 'Register your brand and patents alongside your company, with one team.']
    ],
    services: business,
    faqs: [
      ['Which business structure is best for a startup?', 'If you plan to raise equity investment or offer ESOPs, a Private Limited Company is usually the best fit. LLPs suit professional and owner-run businesses with lower compliance needs.'],
      ['Do I need GST registration immediately?', 'Only if you cross the turnover threshold or fall into a compulsory category, such as inter-state supply of goods or most e-commerce sales. Voluntary registration is also possible.']
    ]
  },
  {
    slug: 'legal',
    name: 'Legal',
    navName: 'Legal',
    label: 'LEGAL',
    accent: 'orange',
    image: 'spot-legal',
    imageAlt: 'Agreements and contracts that protect a business',
    h1: 'Legal Agreements & Drafting Services',
    metaTitle: 'Legal Agreement Drafting: NDA, Licensing & More',
    metaDesc: 'Professionally drafted NDAs, employment, licensing, franchise, vendor, merger and acquisition agreements, trademark coexistence agreements and legal notices.',
    tagline: 'Agreements That Protect Your Business.',
    intro: 'From NDAs and contracts to licensing, franchise, merger, acquisition and employment agreements, our legal documents are professionally drafted and tailored to your business and IP needs. Clear contracts prevent disputes and protect the value you create.',
    points: [
      ['Protect your IP', 'Make sure inventions, code and designs created by employees and vendors belong to you.'],
      ['Prevent disputes', 'Clear scope, timelines and remedies reduce conflict.'],
      ['Enforceable', 'Drafted under Indian law with proper stamping and execution guidance.'],
      ['Monetise IP', 'License or franchise your brand and technology on strong terms.']
    ],
    services: legal,
    faqs: [
      ['Are online agreement templates enough?', 'Templates are a starting point but often miss business-specific risks, Indian law points and enforceability requirements such as stamping.'],
      ['Can agreements be signed digitally?', 'Most commercial agreements can be signed electronically under the IT Act, 2000. Some documents are excluded, such as wills and certain property documents.']
    ]
  }
];

export const allServices = categories.flatMap(c => c.services.map(s => ({ ...s, cat: c.slug, catName: c.name, catLabel: c.label, url: `/${c.slug}/${s.slug}/` })));
export const serviceBySlug = Object.fromEntries(allServices.map(s => [s.slug, s]));
