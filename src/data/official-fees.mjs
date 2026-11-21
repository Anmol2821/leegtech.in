// OFFICIAL GOVERNMENT FEES (INR, e-filing), used only by the Official Fee Calculator.
// Every amount here was read from the official source listed below on `verifiedOn`.
// The monthly content review must re-check these sources and update amounts and `verifiedOn`.
// "lower" = natural person / startup / small entity (and educational institution for patents;
// individual / startup / small enterprise for trademarks). "others" = all other applicants.
export const officialFees = {
  verifiedOn: '2026-11-21',
  sources: {
    patents: 'https://ipindia.gov.in/frontend/pdf/forms_and_official_fees/Schedule_1.pdf',
    trademarks: 'https://ipindia.gov.in/pages/trade-marks/learn/forms-and-official-fees',
    designs: 'https://ipindia.gov.in/designs-resources-rules'
  },
  patents: {
    application: { lower: 1600, others: 8000, form: 'Form 1' },
    extraSheet: { lower: 160, others: 800, note: 'per sheet beyond 30' },
    extraClaim: { lower: 320, others: 1600, note: 'per claim beyond 10' },
    earlyPublication: { lower: 2500, others: 12500, form: 'Form 9' },
    examination: { lower: 4000, others: 20000, form: 'Form 18' },
    expeditedExamination: { lower: 8000, others: 60000, form: 'Form 18A' },
    preGrantOpposition: { lower: 4000, others: 20000, form: 'Form 7A' },
    postGrantOpposition: { lower: 8000, others: 40000, form: 'Form 7' },
    renewal: [
      { years: [3, 6], lower: 800, others: 4000 },
      { years: [7, 10], lower: 2400, others: 12000 },
      { years: [11, 15], lower: 4800, others: 24000 },
      { years: [16, 20], lower: 8000, others: 40000 }
    ]
  },
  trademarks: {
    application: { lower: 4500, others: 9000, form: 'TM-A', per: 'class' },
    expedited: { lower: 20000, others: 40000, form: 'TM-M (expedited processing)', per: 'class' },
    opposition: { all: 2700, form: 'TM-O', per: 'class' },
    renewal: { all: 9000, form: 'TM-R', per: 'class' },
    renewalSurcharge: { all: 4500, note: 'plus renewal fee, within 6 months after expiry', per: 'class' },
    searchCertificate: { all: 9000, form: 'TM-C' }
  },
  designs: {
    application: { lower: 1000, others: 4000, form: 'Form 1', per: 'application' },
    extension: { lower: 2000, others: 8000, form: 'Form 3', note: 'to extend protection by 5 years' },
    restoration: { lower: 1000, others: 4000, form: 'Form 4', note: 'plus additional restoration fee of the same amount' }
  }
};
