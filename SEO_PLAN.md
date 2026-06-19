# Puresol — Intensive SEO Playbook

**Goal:** Rank Puresol as *the* authority for natural / alkaline / Sambhar Lake salt in India,
drive organic retail + bulk/wholesale enquiries, and compound traffic through daily content.

Site: https://puresol.in · Niche: alkaline pink salt, Sambhar Lake, 54+ minerals, zero microplastics, FSSAI.
Primary engine now live: **admin → Journal** (blog CMS) publishing to clean URLs `/blog/<slug>`.

---

## 0. One-time setup (do this week)

- [ ] **Run the DB migration** so the blog works. In Hostinger → phpMyAdmin, paste the `posts`
      table SQL (in `setup.sql`, "Blog / Journal Posts" block), OR run `php scripts/migrate_posts.php` via SSH.
- [ ] **Google Search Console** — verify `puresol.in` (the `google5f9b2219ea19b631.html` token is already deployed).
      Submit `https://puresol.in/sitemap.xml`.
- [ ] **Bing Webmaster Tools** — verify + submit sitemap (import from GSC in one click).
- [ ] **Google Business Profile** — create/claim for "Puresol / Agrigore Ventures", category *Salt supplier* /
      *Wholesaler*. Add address, hours, photos, products. This is the single biggest local-SEO lever.
- [ ] **Google Merchant Center** (for product visibility / free listings) — feed the 2 products with schema.
- [ ] Confirm GA4 (`G-3VYJ4LFE76`) is firing and linked to Search Console.
- [ ] Set GSC + GA4 to email weekly performance reports.

---

## 1. Keyword universe (build content around these)

**Money / commercial (product pages, /index, /health):**
- buy alkaline salt online india · pink salt wholesale india · bulk natural salt supplier
- sambhar lake salt buy · FSSAI certified pink salt · alkaline salt price india
- natural salt without microplastics buy · rock salt vs sea salt vs lake salt

**Informational (blog — high volume, build authority):**
- is sea salt healthy · microplastics in salt · what is alkaline salt · pH of salt
- benefits of pink salt · sambhar lake facts · dunaliella salina beta carotene
- iodine in natural salt · sodium vs potassium balance · best salt for high blood pressure (careful, factual)
- ayurveda saindhava lavana / lavanam · trace minerals in salt · how salt is sun-dried

**Local / long-tail:**
- pink salt manufacturer rajasthan · salt supplier jaipur · wholesale salt distributor india
- sambhar lake salt company · "[city] alkaline salt"

> Rule: one **primary keyword per post**, 3–5 secondary in headings/body. Match search intent.
> Put the target phrase in the **title, URL slug, H1, first 100 words, one H2, meta description, image alt**.

---

## 2. The daily / weekly cadence ("SEO is an everyday job")

### Daily (15–30 min)
- Publish or progress **1 blog post** (aim 3–5 quality posts/week). 1,000–2,000 words, sourced, cited.
- Internal-link the new post to 2–3 existing pages (products, science, health) and vice-versa.
- Check GSC "Performance" for queries gaining impressions → write/优化 a post targeting them.
- Reply to any Google Business Profile reviews / Q&A.

### Weekly
- Post a GBP update/photo. Get 1–2 new customer reviews.
- Build **2–3 backlinks** (see §5). Even one good link/week compounds.
- Review GSC "Pages" → fix anything not indexed; request indexing for new posts.
- Refresh/expand 1 older post (Google rewards freshness).
- Share each post to socials + relevant communities (Reddit r/india, Quora answers, FB groups).

### Monthly
- Re-crawl with a free auditor (Screaming Frog free ≤500 URLs, or Ahrefs/SEMrush trial).
- Update `<lastmod>` dates already auto-handled for posts; review Core Web Vitals in GSC.
- Check rankings for the top 20 target keywords; double down on movers.
- Earn 1 "hero" backlink (guest post, PR, supplier directory).

---

## 3. Content engine — how to write a ranking post

**Per post checklist (use the admin Journal fields):**
- [ ] **Title** = primary keyword + hook (≤60 chars). e.g. *"Microplastics in Sea Salt: What 2018 Research Found"*
- [ ] **Slug** auto-generated — keep it short and keyword-rich.
- [ ] **Meta description** ≤155 chars, includes keyword + a reason to click. (Auto-fills, but write it manually for money posts.)
- [ ] **Cover image** + descriptive **ALT text** (image SEO + accessibility).
- [ ] **Body structure:** intro answers the query in 2 sentences → `<h2>` sections → bullet lists → a data point or citation → CTA.
- [ ] **Internal links:** link to /index#products, /science.html, /health.html, and 1–2 sibling posts.
- [ ] **External links:** 1–2 to authoritative sources (studies, FSSAI, Ramsar) — builds trust/E-E-A-T.
- [ ] **Category + tags** filled (drives the on-site filter + topical clusters).
- [ ] Length: 1,000+ words for informational, 600+ for news/updates.

**Topic clusters (pillar → supporting):** build sets of 5–8 interlinked posts so Google sees topical authority.
1. *Microplastics & salt purity* → ocean vs lake, what studies found, how to choose clean salt, plastic in food.
2. *Alkalinity & pH* → what alkaline salt is, pH 9 explained, acid-alkaline diet myths vs facts.
3. *Minerals & nutrition* → 54+ minerals, iodine naturally, potassium/sodium, beta-carotene from algae.
4. *Sambhar Lake & sourcing* → the lake, Ramsar wetland, hand-harvest, Suryatapa process, sustainability.
5. *Ayurveda & tradition* → saindhava lavana, traditional uses, culinary guides, recipes.

Each cluster's posts link to each other + to the relevant money page. **First 90 days: ~40 posts** across these clusters.

---

## 4. Technical SEO (mostly already strong — keep it that way)

Already in place ✅: HTTPS + non-www canonical, gtag, rich meta/OG/Twitter, JSON-LD, sitemap, robots,
compression, caching, security headers. New ✅: blog posts auto-add to `sitemap.xml` on publish, each
post outputs canonical + BlogPosting + BreadcrumbList JSON-LD.

Still worth doing:
- [ ] **Product schema** (`Product` + `Offer` + `AggregateRating`) on product cards/pages for rich results & Merchant Center.
- [ ] **Organization / LocalBusiness schema** sitewide (name, logo, address, sameAs socials) — helps Knowledge Panel.
- [ ] **Core Web Vitals:** keep images sized (`width`/`height` already used), lazy-load below the fold,
      consider self-hosting fonts to cut render-blocking. Test on PageSpeed Insights, target ≥90 mobile.
- [ ] **Image SEO:** descriptive filenames + alt on every asset; serve WebP where possible.
- [ ] **Breadcrumbs** on category/blog pages (post pages already have breadcrumb + schema).
- [ ] **404 / broken links:** keep `404.html` (present), monitor GSC "Not indexed".
- [ ] Add a **`/blog` index** experience: the live "Latest Articles" grid is now on blog.html (JS-loaded); consider
      a server-rendered paginated archive later for deeper crawl of many posts.

---

## 5. Off-page / backlinks (the hard part — what actually moves rankings)

- **Business directories / citations (NAP consistent):** Justdial, IndiaMART, TradeIndia, Sulekha,
      Google Business, Bing Places, Apple Maps, FSSAI listings, local Jaipur/Rajasthan directories.
- **Industry / niche:** Ayurveda blogs, clean-eating sites, sustainability/wetland (Ramsar) orgs, food bloggers.
- **Digital PR:** pitch the "salt with zero microplastics, from an inland Ramsar lake" angle to Indian health/food press.
- **Guest posts** on wellness/food sites linking back to a relevant cluster pillar.
- **HARO / Qwoted / SourceBottle (India):** answer journalist queries about salt, minerals, sustainability.
- **Quora + Reddit:** genuinely answer salt/health questions, link where relevant (no spam).
- **Supplier/B2B:** since you do bulk/wholesale, get listed on IndiaMART/TradeIndia with the website link.
- **Reviews:** drive Google + product reviews; review signals help local + trust.

> Quality > quantity. 5 relevant, real links beat 500 spammy ones (which can get you penalized).

---

## 6. Measurement & KPIs

Track monthly in a simple sheet:
- Organic sessions (GA4) · Indexed pages (GSC) · Impressions & avg position (GSC) · Top queries movement
- Keywords in top 3 / top 10 · Backlinks (Search Console "Links") · Conversions (enquiries/orders) from organic
- Blog: posts published, top posts by views (the CMS tracks `views` per post)

**90-day targets (adjust to baseline):** 40+ posts live · all key pages indexed · 30+ keywords ranking ·
GBP live with 10+ reviews · 20+ quality backlinks · measurable organic enquiry growth.

---

## 7. Quick wins this week
1. Run the migration + publish 3 starter posts (one per top cluster).
2. Submit sitemap to GSC + Bing; request indexing of the new posts.
3. Create Google Business Profile.
4. Add Product + Organization schema to the storefront.
5. List on IndiaMART + Justdial with NAP + website link.
