# Polar.sh Evaluation for Manuscript

Research date: 2026-03-23

## Summary

Polar.sh is a Merchant of Record (MoR) platform built for software monetization. It handles payment processing, tax compliance, and benefit delivery. It is built on top of Stripe but abstracts away tax/VAT complexity. Polar is a Delaware C Corporation and is still relatively young (public since late 2024/early 2025).

---

## 1. VAT/Tax Handling for EU/German Customers

**Yes, Polar handles VAT automatically.**

- Polar acts as Merchant of Record and assumes **full liability for tax compliance globally**.
- EU VAT is handled via **Irish OSS (One Stop Shop) registration** -- VAT number: `EU372061545`.
- This covers all EU member states through a single registration.
- UK VAT is also registered separately.
- US state sales taxes are registered upon reaching nexus thresholds.
- Polar uses **Stripe Tax** under the hood for real-time tax rate calculation.
- **B2B reverse charge** is supported for EU business customers with valid VAT IDs.
- **B2C tax collection** is handled automatically for consumer purchases.

**Bottom line:** You do not need to register for VAT yourself. Polar files, collects, and remits VAT on your behalf.

---

## 2. Regional/Geo-Localized Pricing

**Partially supported.**

- Polar automatically detects customer location and shows prices in a **localized currency** if available.
- Supported currencies: USD, EUR, GBP, CAD, AUD, JPY, CHF, SEK, INR, BRL (10 total).
- You can set **different prices per currency** for the same product (e.g., EUR 29 / USD 32).
- However, all price structures must remain identical across currencies (same tier model).
- There is **no PPP (Purchasing Power Parity) discount system** built in -- you would need to handle country-specific discount codes manually or via API.

**Bottom line:** Currency-based pricing yes, true geo-pricing (different prices per country) no.

---

## 3. VAT-Inclusive Price Display (PAngV Compliance)

**NOT YET SUPPORTED -- Critical Gap for German market.**

- Currently, **all prices on Polar exclude taxes**. Customers see the base price, then VAT is added at checkout.
- For German consumers, this violates the **Preisangabenverordnung (PAngV)**, which requires all prices shown to end consumers to include VAT.
- This is tracked as GitHub issue [#4788](https://github.com/polarsource/polar/issues/4788) (opened January 2025).
- A pull request [#10472](https://github.com/polarsource/polar/pull/10472) implementing tax-inclusive pricing was opened on **March 20, 2026** and is currently **in active development** (not yet merged as of March 23, 2026).
- The planned feature adds an **organization-level setting** (`default_tax_behavior`) to choose between:
  - Tax-exclusive pricing (current behavior -- price + tax at checkout)
  - Tax-inclusive pricing (advertised price includes tax, Polar deducts tax from your revenue)
- The PR affects fixed, custom, metered, and seat-based pricing models.

**Bottom line:** As of today, Polar is NOT PAngV-compliant for German B2C sales. The fix is imminent (PR is open and actively being worked on), but not yet shipped. This is a **blocker for launching to German consumers** until it lands.

### Workaround (temporary)

You could set your EUR price to include the highest EU VAT rate (e.g., 27% Hungary) and accept that your net revenue will vary by country. But customers would still see "price + VAT" at checkout rather than an all-inclusive price, which still violates PAngV for the price display on your marketing site.

---

## 4. Pricing Options Supported

| Model | Supported | Notes |
|-------|-----------|-------|
| **One-time purchase** | Yes | Customer pays once, permanent access |
| **Recurring subscription** | Yes | Daily, weekly, monthly, yearly, or custom intervals (e.g., every 2 weeks) |
| **Lifetime deal** | Yes | Implemented as one-time purchase with permanent benefit access |
| **Pay what you want** | Yes | Optional minimum amount + suggested default |
| **Free products** | Yes | No charge, useful for freemium funnels |
| **Fixed pricing** | Yes | Predetermined price per product |
| **Usage-based / metered** | Yes | Aggregate usage events into meters, invoice monthly |
| **Seat-based** | Yes | Per-seat pricing for team plans |

**Bottom line:** All common pricing models are covered. Lifetime deals work as one-time purchases -- there is no special "lifetime" product type, but the effect is the same.

---

## 5. Software License Keys

**Yes, fully supported with a public API.**

### Features
- Automatic license key generation on purchase
- Key format example: `POLAR-ABC123-XYZ789-DEF456` or `DEVTUI-2CA57A34-E191-4290-A394-XXXXXX`
- Configurable **activation limits** (e.g., 3 machines per license)
- Machine identification via labels (MAC address, device ID, etc.)
- Usage tracking / increment counters
- Custom validation conditions

### API Endpoints (no auth required -- safe for desktop apps)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/v1/customer-portal/license-keys/activate` | POST | Activate a key on a device |
| `/v1/customer-portal/license-keys/validate` | POST | Validate an active key |
| `/v1/customer-portal/license-keys/deactivate` | POST | Deactivate a key from a device |

### Parameters
- `key` -- the license key string
- `organization_id` -- your Polar org ID
- `label` -- device identifier (e.g., MAC address)
- `activation_id` -- required if activation limits are enabled
- `conditions` -- optional custom conditions (IP, version, etc.)
- `meta` -- optional metadata
- `increment_usage` -- optional usage counter

### Integration Pattern for NativePHP/Desktop Apps
1. User enters license key in app
2. App calls `/activate` with key + device label (MAC address)
3. Store activation response in local `license.json`
4. Periodically call `/validate` (e.g., weekly) to verify key is still active
5. Use local hash check between validations to detect tampering
6. On subscription cancellation, Polar revokes the benefit and validation fails

**Bottom line:** Well-suited for Manuscript's desktop app license model. The unauthenticated API endpoints are specifically designed for public clients like desktop apps.

---

## 6. Polar.sh Fees

### Transaction Fee
**4% + $0.40 per transaction** (flat rate)

This includes:
- Payment processing (Stripe's 2.9% + 30c is absorbed by Polar)
- Global tax compliance and MoR services
- Benefit delivery automation

### Additional Fees
| Fee | Amount |
|-----|--------|
| International cards (non-US) | +1.5% |
| Subscription payments | +0.5% |
| Disputes/chargebacks | $15 per dispute |
| Refunds | Transaction fee is non-refundable |

### Payout Fees (Stripe pass-through, no Polar markup)
| Fee | Amount |
|-----|--------|
| Monthly active payout | $2/month |
| Per payout | 0.25% + $0.25 |
| Cross-border conversion (EU) | 0.25% |
| Cross-border conversion (other) | up to 1% |

### No Monthly Fees
- No platform subscription fee
- No setup costs
- Pay only when you make sales

### Effective Cost Example
For a EUR 29/month subscription purchased by a German customer:
- Base: 4% + $0.40 = ~$1.56 + $0.40 = ~$1.96
- Subscription surcharge: +0.5% = ~$0.15
- International card: +1.5% = ~$0.44 (if non-US card)
- **Total per transaction: ~$2.55 (~6.5% of $39 equivalent)**
- Plus payout fees when you withdraw

### Comparison
- Polar: 4% + 40c (MoR included)
- Paddle: 5% + 50c (MoR included)
- Lemon Squeezy: 5% + 50c (MoR included)
- Stripe: 2.9% + 30c (NO MoR, tax compliance is your problem)
- Gumroad: 10% flat

**Bottom line:** Competitive pricing for a MoR. Cheaper than Paddle/Lemon Squeezy. The hidden costs are the international card surcharge (+1.5%) and subscription surcharge (+0.5%) which bring the effective rate closer to ~6-7% for EU subscription sales.

---

## 7. Merchant of Record Status

**Yes, Polar is a full Merchant of Record.**

- Polar Software Inc. (Delaware C Corp) is the legal seller on all transactions.
- Polar appears on customer bank statements / invoices as the merchant.
- Polar assumes **full liability** for:
  - VAT/GST/sales tax collection and remittance globally
  - Tax filing in all registered jurisdictions
  - Invoice generation with proper tax breakdowns
  - B2B reverse charge handling
  - Dispute/chargeback management
- Current tax registrations:
  - EU VAT via Irish OSS (all 27 EU member states)
  - UK VAT
  - US state sales taxes (registered upon threshold)
- Polar works with global accounting firms to scale registrations as needed.

**Bottom line:** Full MoR. You never touch tax compliance. Polar handles everything from collection to remittance.

---

## Overall Assessment for Manuscript

### Pros
- Full MoR eliminates all VAT/tax headaches
- License key system is purpose-built for desktop software
- Unauthenticated validation API is perfect for NativePHP
- Competitive fees (cheaper than Paddle/Lemon Squeezy)
- One-time + subscription + lifetime all supported
- EUR currency supported with geo-detection
- Built on Stripe (reliable payment infrastructure)
- Open source platform

### Cons / Risks
- **PAngV non-compliance is a blocker** -- tax-inclusive pricing not yet shipped (PR #10472 is open as of March 23, 2026)
- No true PPP/geo-pricing (only currency-based, not country-based discounts)
- Relatively young platform (less track record than Paddle/Stripe)
- International card surcharge (+1.5%) and subscription surcharge (+0.5%) add up
- Effective fee for EU subscriptions is ~6-7%, not the advertised 4%+40c

### Recommendation
Polar.sh is a strong candidate for Manuscript's payment infrastructure, **but the PAngV issue must be resolved before launching to German customers**. The tax-inclusive pricing PR (#10472) appears close to shipping. Monitor it closely -- once merged, Polar becomes viable for the German market.

If you need to launch before the PR ships, consider:
1. **Paddle** -- established MoR with tax-inclusive pricing already supported
2. **Lemon Squeezy** -- similar to Polar but with tax-inclusive pricing
3. Wait for Polar's PR to merge (appears imminent given active development)

---

## Sources

- [Polar Merchant of Record Documentation](https://polar.sh/docs/merchant-of-record/introduction)
- [Polar Products Documentation](https://polar.sh/docs/features/products)
- [Polar Pricing Page](https://polar.sh/resources/pricing)
- [Polar Benefits & Fulfillment](https://polar.sh/features/benefits)
- [Polar vs Stripe Comparison](https://polar.sh/resources/comparison/stripe)
- [GitHub Issue #4788 -- Tax-Inclusive Pricing](https://github.com/polarsource/polar/issues/4788)
- [GitHub PR #10472 -- Tax-Inclusive Implementation](https://github.com/polarsource/polar/pull/10472)
- [License Key Activate API](https://polar.sh/docs/api-reference/customer-portal/license-keys/activate)
- [License Key Validate API](https://docs.polar.sh/api-reference/customer-portal/license-keys/validate)
- [Software License Management with Polar.sh (Blog)](https://skatkov.com/posts/2025-05-11-software-license-management-for-dummies)
- [Polar.sh Review (Dodo Payments)](https://dodopayments.com/blogs/polar-sh-review)
- [Stripe vs Polar.sh Comparison (Buildcamp)](https://www.buildcamp.io/blogs/stripe-vs-polarsh-which-payment-platform-is-best-for-your-saas)
