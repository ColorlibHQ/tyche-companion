=== Tyche Companion ===
Contributors: colorlib
Tags: woocommerce, starter sites, free shipping, sticky add to cart, countdown
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Starter sites for the Tyche theme, plus free shipping progress, a sticky add-to-cart bar and product badges.

== Description ==

Tyche Companion adds the store features a WooCommerce block theme is not allowed to include. It is made for the Tyche theme and works with any block theme that uses WooCommerce's blocks.

* **Free shipping progress.** "Spend $21.00 more for free shipping", above the cart and in the cart drawer, updating as the cart changes. The amount comes from the minimum order amount on your Free shipping method, so it always matches what checkout charges. Also available as a block.
* **Sticky add-to-cart bar.** On product pages, once the main button scrolls out of view, a bar with the product, price and button follows the shopper. It submits the page's own form, so quantities and other plugins keep working.
* **Sale badges as a percentage.** "-25%" instead of "Sale". Variable products show their largest discount.
* **"New" and "Sold out" badges** on product cards.
* **Second photo on hover.** Product cards show the first gallery image when a pointer hovers them.
* **Countdown block.** Counts down to a drop, a sale or the end of an offer, and says so when the moment passes. The numbers are hidden from screen readers, which are given the date instead.

Every feature can be switched off under WooCommerce > Tyche Companion.

= Starter sites =

Appearance > Starter sites lists complete stores you can import in one step: products with real options, pages, journal posts, photographs and a look that matches.

* **Import the full store**, or **just the look** if you already sell something. "Look only" changes the design and the home page layout and leaves your products and pages alone.
* **One click removes it again.** Everything an import adds is recorded, so removing takes out exactly that, and keeps anything you have since used or edited.
* **Nothing else is touched.** Orders, customers, payment, tax and account settings are never changed by an import.

== External services ==

This plugin downloads starter sites from Colorlib's library at https://downloads.colorlib.com when you open Appearance > Starter sites and when you import one.

Opening the screen requests the list of starters (their names, the niche each one suits, and how many products and pages it has). Importing one requests that starter's content: its products, pages, menus and photographs. No information about your site is sent with either request, and nothing is requested unless you open that screen.

Colorlib's terms of service: https://colorlib.com/wp/terms-of-service/ - privacy policy: https://colorlib.com/wp/privacy-policy/

== Changelog ==

= 0.2.0 =
* Starter sites: import a complete store, or only its look, from Appearance > Starter sites.
* A countdown block, for drops and offers.
* Removing an imported starter takes out what it added and leaves anything since used or edited.
* WP-CLI: `wp tyche starters`, `wp tyche import <slug>`, `wp tyche remove`.

= 0.1.0 =
* First release.
