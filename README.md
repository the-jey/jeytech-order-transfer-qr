# JeyTech Order Transfer QR for WooCommerce

Fourth free plugin in the JeyTech pipeline. Adds a locally generated EPC / GiroCode QR and readable payment details to the confirmation page and customer on-hold email for BACS orders in EUR. It never confirms a payment or changes order status or stock.

Name checked before implementation on 27 September 2026: **JeyTech Order Transfer QR for WooCommerce**, slug `jeytech-order-transfer-qr`, namespace `JeyTech\OrderTransferQR`. No exact namesake found in Web searches or the 221 WordPress search results inspected. Both the proposed slug and `order-transfer-qr` return `Plugin not found` through the public API. See [the name audit](dev/name-audit-2026-09-27.json). Public availability is not a WordPress slug reservation.

## Commands

WordPress, WooCommerce and PHP run in the existing WordPress Playground toolchain. Node.js is the development prerequisite; no local PHP server or Composer is required.

| Command | Purpose |
| --- | --- |
| `npm run dev` | English demo on port 9406 |
| `npm run dev:fr` | French demo on port 9407 |
| `npm test` | Payment, PNG and integration checks on HPOS / PHP 8.3 |
| `npm run test:legacy` | Same checks on classic order storage / PHP 7.4 |
| `npm run test:minimum` | WordPress 6.6.2 / WooCommerce 9.6.2 / PHP 7.4 |
| `npm run i18n` | Generate POT and compile the bundled French catalogs |
| `npm run check` | Build the ZIP and run Plugin Check static checks against its contents |
| `npm run build` | Build `dist/jeytech-order-transfer-qr.zip` |

The ZIP excludes development scripts, tests, node_modules, screenshots, Git and build artifacts. It includes the complete readable encoder source and licenses. The French catalog is bundled for manual ZIP installations too.

## Payment behavior

The merchant selects a WooCommerce bank account explicitly. Selection is keyed by name, IBAN and BIC, so reordering accounts does not redirect transfers. Editing those coordinates requires selecting the account again. The plugin respects per-order BACS account filters and omits the QR if final displayed bank fields would differ.

Only on-hold BACS orders in EUR qualify. Amounts are exact cents, between 0.01 and 999999999.99. Reference formats must contain `{order_number}`; final text is bounded to 140 characters and complete UTF-8 payloads to 331 bytes. An invalid or unsupported order does not receive a QR. Images are black/white PNG, correction M, with a four-module quiet zone and QR version at most 13.

Hosted email images use a signed `admin-post.php` URL. The signature binds order ID, order key, expiry and payload hash. It expires after 30 days and is rechecked against current settings/order data for every request. Tampered, expired, paid, cancelled or changed orders cannot return a PNG. No public QR files are written into uploads. Email proxies or recipients can retain copies, so these copies cannot be revoked.

The same WooCommerce payment-specific thank-you hook is used by classic and block order confirmation pages. Customer on-hold HTML emails get a hosted PNG and all readable payment details; plain-text emails keep the details without an image. The plugin sends no additional email and performs no bank or external QR request.

## Review and next versions

The WordPress.org submission is pending the completion of Safety Data by Brand's review. WordPress normally accepts one pending review per author. Prepare the final ZIP and screenshots, then submit with `jeytech` and set the proposed short slug if WordPress initially appends `for-woocommerce`. No SVN upload before approval.

Planned Pro functionality remains a separate future release. Apply [the shared Pro release checklist](../PRO_RELEASE_CHECKLIST.md): EN/FR guides, matching delivery-email links, language tests, private ZIP delivery and license checks. No Pro is sold or claimed available by this free release.

## Verification, 27 September 2026

| Runtime | Storage | Functional checks |
| --- | --- | --- |
| WordPress 7.1.2, WooCommerce 11.1.2, PHP 8.3.33 | HPOS | 55 passed |
| WordPress 7.1.2, WooCommerce 11.1.2, PHP 7.4.33 | Classic | 54 passed |
| WordPress 6.6.2, WooCommerce 9.6.2, PHP 7.4.33 | Classic, minimum requirements | 54 passed |

Recorded outputs are in `dev/results/`. Native WooCommerce HTML email rendering is verified on PHP 8.3. The PHP 7.4 WASM runtime cannot render the native WooCommerce mail template; plugin HTML/plain-text hooks and translations are tested on both versions. No test email is sent.

Six PNGs were independently decoded with ZXing-C++: accented and Greek text, exact amounts/references, and the 331-byte boundary. PNG chunk CRCs and the four-module white quiet zone are checked. `bin/verify-qr.py` repeats these checks with the optional development dependencies documented in the script. This proves the encoded bytes; no real banking app or transfer was used.

Browser checks use the packaged production files: EN/FR settings, nonce-protected saves, forged requests (403), revocation after reference changes and disabling, valid PNGs (200/no-store), classic and block confirmation pages, and native HTML email previews. Desktop 1440px and mobile 390px, no JavaScript errors. Successful native classic and Blocks checkouts were also submitted on a non-stock virtual demo product at EUR 19.90; stock preservation is asserted separately in the functional suites. Outgoing mail is intercepted in the demo MU helper only.

Run `npm run build` before the demo servers. `node dev/capture-ui.mjs` performs the UI checks and six real screenshots, `node dev/checkout-ui.mjs` checks the two checkout types, and `node dev/assets-src/render.mjs` renders the original HTML/SVG branding. Geist font source and OFL license are included in the development assets only. Directory assets are prepared in `.wordpress-org/`; they are excluded from the plugin ZIP and are uploaded separately to SVN after approval.
