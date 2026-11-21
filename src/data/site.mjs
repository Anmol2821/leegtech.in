// Central site configuration. Fill the tracking IDs below to switch on analytics and ads tracking.
// Leave a value empty ('') to keep that tag off. Nothing is loaded until it has an ID.

export const site = {
  url: 'https://leegtech.in',
  name: 'Leegtech',
  legalName: 'Leegtech Private Limited',
  tagline: 'Protecting Innovation, Empowering Growth.',
  slogan: 'Build Bold, Protect Smart.',
  description: 'Leegtech provides end-to-end patent, trademark, copyright, design, business registration, compliance and legal drafting services across India, with international filing support.',
  founder: { name: 'Anmol Shrivastava', role: 'Founder' },
  phone: '+918982346105',
  phoneDisplay: '+91 89823 46105',
  whatsapp: '918982346105',
  whatsappText: 'Hi Leegtech! I need help with IP/Legal services. Please get in touch.',
  email: 'info.leegtech@gmail.com',
  address: {
    street: 'Gincube Center, Moti Mahal',
    city: 'Gwalior',
    region: 'Madhya Pradesh',
    postalCode: '474001',
    country: 'IN',
    display: 'Gincube Center, Moti Mahal, Gwalior, Madhya Pradesh 474001'
  },
  registeredAddress: '97 Tansen Nagar, Gwalior, Madhya Pradesh 474003, India',
  areaServed: 'India',
  // Office hours: fill both to show them on the site and in structured data, e.g. 'Mo-Sa 10:00-19:00'.
  hours: '',
  hoursDisplay: '',
  // Add your public profiles here (used in structured data "sameAs" and the footer).
  social: {
    linkedin: '',
    instagram: '',
    facebook: '',
    youtube: '',
    x: '',
    googleBusiness: ''
  },
  tracking: {
    gtmId: '',            // e.g. 'GTM-XXXXXXX' (recommended: manage GA4, Ads and Meta inside GTM)
    ga4Id: '',            // e.g. 'G-XXXXXXXXXX' (used only if gtmId is empty)
    googleAdsId: '',      // e.g. 'AW-123456789' (used only if gtmId is empty)
    googleAdsLeadLabel: '', // conversion label for the lead conversion
    metaPixelId: '',      // e.g. '123456789012345'
    googleSiteVerification: '',
    bingSiteVerification: '',
    facebookDomainVerification: ''
  },
  lastReviewed: '2026-11-21'
};
