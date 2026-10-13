# Marketplace patterns reviewed (October 2026)

Large marketplaces were reviewed for patterns that help people with little online-shopping experience. Only patterns that fit PAL Market (a request-for-prices marketplace for building materials in Tanzania, no payments, no delivery service) were applied.

## What was looked at

| Site | How | Notes |
| --- | --- | --- |
| Alibaba.com | Home page fetched and read | Search with scope tabs (Products / Manufacturers), "What are you looking for?" prompt, request-for-quotation wording ("Post customized sourcing requirements to get quotes from multiple matching suppliers"), "Ship to" location selector, language/currency selector, verified-supplier labels, "All categories" menu. |
| eBay | Home page fetched and read | "Shop by category" next to the search box, "Sign in or register", Help & Contact, a visible Sell link. The search results page redirected to the category index, so result-page filters were not observed directly. |
| Amazon | Home page content had no header; the search page was refused (robots) | The "Deliver to" location indicator was **not observed directly**. Its behaviour is described from third-party documentation (repricer and localisation guides). Treat that row as second-hand. |

## Applied

| Pattern | Where it came from | In PAL Market |
| --- | --- | --- |
| One prominent search box with a category scope | Alibaba, eBay | Header search on every public page with "All categories" select; searches `/materials`. |
| Location near the top that changes what you see | Amazon ("Deliver to", second-hand), Alibaba ("Ship to") | "Your area" chip. Region only, chosen from a list or suggested from the browser's location after permission. Changes ordering ("near me first"), an "only my area" filter, local-seller badges, the default delivery region of new requests and a quick filter for sellers. |
| Request quotes from many suppliers | Alibaba RFQ | Already the core flow; wording simplified to "Ask for prices" / "Send to sellers" / "price offer". |
| Language selector always visible | Alibaba | EN / SW switch in the header of every page, saved to the device and the account. |
| Visible "Sell" and "Help" links | eBay | "Sell on PAL Market" and "Help" in the public navigation and footer. |
| Filter chips you can remove, sort options | General marketplace practice | Applied filters shown as removable chips with "Clear all"; sort by nearest stock, price or name; filters in a panel on small screens. |
| Trust labels | Alibaba "verified" | Only approved sellers appear; no ratings or badges were invented because the data does not exist. |

## Not applied, and why

- Ratings, reviews, "best seller" or stock-level badges: no such data exists; inventing it would mislead.
- Currency selector: all prices are in TZS.
- Chat assistant: the brief asked for concise contextual help instead; a bilingual help page and step guides were added.
- Delivery promises ("arrives tomorrow"): PAL Market does not deliver; sellers state their own terms on each offer.
