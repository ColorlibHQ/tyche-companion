/**
 * End-to-end checks for the plugin's store features on a running store.
 *
 *   WP_URL=http://localhost:8812 node .dev/feature-test.mjs [shotsdir]
 *
 * Needs Playwright where Node can resolve it (npm i -D playwright).
 */
import { chromium } from 'playwright';

const base = process.env.WP_URL || 'http://localhost:8812';
const shots = process.argv[ 2 ] || '/tmp';
const results = [];
const check = ( name, ok, detail = '' ) => results.push( `${ ok ? 'PASS' : 'FAIL' }  ${ name }${ detail ? '  (' + detail + ')' : '' }` );

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
const page = await context.newPage();
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message.slice( 0, 140 ) ) );

// Sticky add-to-cart: hidden at the top, shown after scrolling past the form, adds to cart.
await page.goto( base + '/?product=wool-overcoat', { waitUntil: 'load' } );
const bar = page.locator( '.tyche-sticky-atc' );
check( 'sticky bar printed on a simple product', await bar.count() === 1 );
check( 'sticky bar hidden at the top of the page', await bar.isHidden() );
await page.evaluate( () => window.scrollTo( 0, document.body.scrollHeight * 0.7 ) );
await page.waitForTimeout( 800 );
check( 'sticky bar shown after scrolling past the form', await bar.isVisible() );
await page.screenshot( { path: shots + '/sticky-bar.png' } );
await Promise.all( [ page.waitForLoadState( 'load' ), bar.locator( 'button' ).click() ] );
await page.waitForTimeout( 1000 );
const notice = await page.locator( '.wc-block-components-notice-banner, .woocommerce-message' ).first().textContent().catch( () => '' );
check( 'sticky bar button adds the product', /added to your cart/i.test( notice || '' ), ( notice || '' ).trim().slice( 0, 60 ) );

// Variable product: the bar offers options instead of adding.
await page.goto( base + '/?product=merino-rollneck', { waitUntil: 'load' } );
check( 'variable product bar says Choose options', ( await page.locator( '.tyche-sticky-atc__button' ).getAttribute( 'data-action' ) ) === 'scroll' );

// Free shipping bar on the cart page ($229 in the cart, $250 threshold).
await page.goto( base + '/?page_id=31', { waitUntil: 'load' } );
await page.waitForTimeout( 1500 );
const cartBar = page.locator( '.wp-block-tyche-companion-free-shipping-bar' );
check( 'free shipping bar before the cart', await cartBar.count() >= 1 );
const message = cartBar.count() ? ( await cartBar.first().locator( '.tyche-fsb__message' ).textContent() ).trim() : '';
check( 'cart bar shows the $21.00 still to spend', /\$21\.00/.test( message ), message );
await page.screenshot( { path: shots + '/cart-bar.png' } );

// Raise the quantity in the cart block: the bar must follow without a reload.
await page.waitForSelector( '.wc-block-cart .wc-block-components-quantity-selector', { timeout: 15000 } ).catch( () => {} );
const plus = page.locator( '.wc-block-cart .wc-block-components-quantity-selector__button--plus' ).first();
check( 'cart quantity stepper found (so the next check really runs)', await plus.count() === 1 );
if ( await plus.count() ) {
	await plus.click();
	await page.waitForTimeout( 2500 );
	const after = ( await cartBar.first().locator( '.tyche-fsb__message' ).textContent() ).trim();
	check( 'cart bar updates when quantity rises past the threshold', /unlocked/i.test( after ), after );
	await plus.page().locator( '.wc-block-cart .wc-block-components-quantity-selector__button--minus' ).first().click();
	await page.waitForTimeout( 2500 );
}

// Mini-cart drawer.
await page.goto( base + '/?product=wool-overcoat', { waitUntil: 'load' } );
await page.locator( '.wc-block-mini-cart__button' ).first().click();
await page.waitForTimeout( 1500 );
const drawerBar = page.locator( '.wc-block-mini-cart__drawer .wp-block-tyche-companion-free-shipping-bar' );
check( 'free shipping bar inside the cart drawer', await drawerBar.count() >= 1 );
await page.screenshot( { path: shots + '/drawer-bar.png' } );

check( 'no JavaScript errors', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );
await browser.close();
console.log( results.join( '\n' ) );
