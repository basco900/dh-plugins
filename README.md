# DixcoverHub WordPress plugins

This repository contains DixcoverHub's WordPress plugin source. The sibling `dixcoverhub-final` Next.js project is a read-only reference; WordPress core, LocalWP uploads, and production files are not maintained here.

## Local development

The LocalWP development site is `DixcoverHub Dev` at `http://dixcoverhub-dev.local/`. Plugin source lives in `plugins/`; LocalWP loads copies from `C:\Users\HP\Local Sites\dixcoverhub-dev\app\public\wp-content\plugins\`. Preview and refine there before planning a production deployment. Keep the LocalWP database, uploads, WordPress core, and production credentials out of this repository.

## Plugin architecture

- `plugins/dixcoverhub-core`: shared settings, API services, analytics, opportunity taxonomies, and extension hooks.
- `plugins/dixcoverhub-custom-ui`: navbar, single post layout, opportunity archive and filters, footer, logo, fonts, and popups.
- `plugins/dixcoverhub-ai-editor`: opportunity generation and WhatsApp summaries.

Activate Core first, then Custom UI and AI Editor. Custom behavior belongs in plugins, not WordPress core or a theme's `functions.php`.

Activating a plugin only installs its tools. Public-site features such as the navbar, single-post layout, archive, deadlines page, custom footer, fonts, and popups each stay off until their own Studio switch is enabled. Saved settings remain available while a feature is off.

## AI Opportunity Generator

Open **Posts → AI Opportunity Generator**. Provide verified notes, public source links, and reference images. The generator can write, refine, or regenerate an article from the sources. It returns the article, excerpt, reader summary, provider and application details, taxonomy suggestions, requirements, benefits, FAQs, SEO fields, and source citations for review. Application details support up to eight labeled links plus an optional email address; existing single-link posts continue to work. A reference image can also be saved to the WordPress Media Library and selected as the draft's featured image. Saving creates or updates a WordPress draft; it does not publish automatically.

The OpenAI key is read on the server. Configure `DIXCOVERHUB_OPENAI_API_KEY` in `wp-config.php` or `OPENAI_API_KEY` in the server environment. Set `DIXCOVERHUB_OPENAI_OPPORTUNITY_MODEL` or `OPENAI_OPPORTUNITY_MODEL` to choose the model; the default is `gpt-5`. Evidence images are sent only with the generation request; the editor can separately choose to save an image into the Media Library as the draft's featured image. Source links are fetched through WordPress's safe HTTP client, and their readable text is bounded before generation.

The post editor includes **DixcoverHub WhatsApp Summary**. It uses the article and saved opportunity fields, appends the post URL and up to three related posts, and stores the result on the post. Edit, save, and copy actions work with drafts so a summary can be reviewed before publishing.

## Analytics

Open **Analytics** in the WordPress admin. The dashboard covers GA4 users, views, sessions, engagement, conversions, daily traffic, realtime activity, content performance, acquisition channels, countries, device categories, and application/share/bookmark/community-join events. Choose a Live, 5-minute, 30-minute, one-hour, today, or yesterday window for active traffic. Search and page through published posts and pages, or scope the list to your own content. Each published post or page also has a focused report with views over time, application clicks, shares, bookmarks, and its application conversion rate. It shows a setup state instead of fabricated metrics when credentials are absent.

Configure `GA4_PROPERTY_ID`, `GA4_CLIENT_EMAIL`, and `GA4_PRIVATE_KEY` on the server, or provide `GA4_SERVICE_ACCOUNT_JSON`. Equivalent `DIXCOVERHUB_GA4_*` constants are supported in `wp-config.php`. The service account needs Viewer access to the GA4 property, the Google Analytics Data API must be enabled, and PHP OpenSSL must be available. `GA4_MEASUREMENT_ID` (or `NEXT_PUBLIC_GA_MEASUREMENT_ID`) enables browser tracking. `GA4_TIMEZONE` is optional and defaults to `Africa/Lagos`.

The plugins tag application links, share controls, and the community link. The tracker also supports `bookmark` for any bookmark control you add. Supported names are `application_click`, `share`, `bookmark`, and `community_join`. Optional `data-content-type` and `data-content-id` attributes add content context without sending user-entered text.

## Custom UI Studio

Open **Appearance → DixcoverHub UI**. The Studio contains separate workspaces for:

- **Navbar:** select preset WordPress menus or build up to twelve manual link, dropdown, or text items with labels, destinations, Hugeicons, child links, and new-tab behavior. The public layout uses a sharp, constrained divider and supports a Media Library logo or the site's WordPress logo. Configure the palette, actions, and responsive behavior. A separate opt-in control restores the mobile bottom dock and floating desktop community pill; its labels, destinations, panel links, descriptions, and accent colour are configurable.
- **Single Post:** control the post layout and its summary, application panel, FAQs, related posts, sidebar, width, and hero color.
- **Archive & Filters:** configure the `/opportunities/` archive, categories, text search, type, mode, location, deadline status, sorting, page size, and sidebar.
- **Footer:** configure page placement, logo/wordmark/description, copyright and legal links, social links, and up to eight sections. Each section can contain re-orderable links, text, logos/images, social links, or dividers. An optional callout banner has editable badge and headline text, two actions, tab behavior, gradient and button colors, and corner radius. Set the footer background, heading, muted, link and border colors, and content width. The custom footer replaces Astra and block-theme footers when enabled; other classic themes can use `[dixcoverhub_footer]`.
- **Logo:** choose a logo from the Media Library, control desktop and mobile dimensions, and show or hide the site-name wordmark.
- **Fonts:** upload WOFF2/WOFF faces directly or convert TTF/OTF to WOFF2 in the browser before upload; font data stays on your device until WordPress receives the optimized WOFF2. Assign body, heading, navigation/button, and small-text roles. Static Regular, Medium, Semibold, and Bold files should share a family name and use their actual weights. Only mark a font variable when its file contains variable axes. The bundled WOFF2 encoder is MIT licensed; see `assets/vendor/woff2-encode-wasm/LICENSE.txt`.
- **Popups:** create draft-first, path-targeted popup experiences with timing, frequency, device, schedule, position, design, custom code, preview, revision history, and anonymous metrics. The public popup feature and each popup are independently controlled; both must be active before it appears.

Use `[dixcoverhub_navbar]` or `[dixcoverhub_footer]` if automatic rendering is disabled or a theme requires manual placement. Built-in icons are inline SVG from Hugeicons Core Free; there is no icon-font or icon-library network request. See `plugins/dixcoverhub-custom-ui/assets/icons/LICENSE-Hugeicons-MIT.txt` for the bundled icon license.

## Local verification

The three plugins are copied to the LocalWP site for preview. The homepage and `/opportunities/` return HTTP 200. The archive renders six custom listboxes and no browser-native select controls. With the footer and theme-replacement switches on, LocalWP renders the custom footer without Astra's footer builder. The mobile dock and floating desktop community pill remain present when their quick-navigation switch is on. The Popups REST route is registered, and its front-end markup and script stay unloaded while its master switch is off. Analytics assets load from LocalWP. Front-end UI features retain their own saved activation switches. AI generation and live GA4 metrics still require server-side credentials before their live requests can be verified.

Production deployment remains a separate step after review in LocalWP. Only plugin folders should be prepared for deployment; WordPress content, database, uploads, and secrets stay managed by WordPress and the hosting environment.
