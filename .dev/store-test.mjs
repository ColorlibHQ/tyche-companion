/**
 * Does this store work? Run against any starter, on any site.
 *
 *   WP_URL=http://localhost:8813 node .dev/store-test.mjs [shot-dir]
 *
 * Nothing here knows what the store sells. The products come from the Store
 * API, so the same test covers a knitwear shop, a coffee roaster and whatever
 * is built next -- which is the point: a starter is only listed once it has
 * passed this on a clean WordPress.
 */

import { chromium } from 'playwright';

const base = ( process.env.WP_URL || 'http://localhost:8813' ).replace( /\/$/, '' );
const shots = process.argv[ 2 ] || '/tmp';
const results = [];
const check = ( name, ok, detail = '' ) => results.push( `${ ok ? 'PASS' : 'FAIL' }  ${ name }${ detail ? '  (' + detail + ')' : '' }` );

const api = async ( path ) => {
	// rest_route carries the path; anything after "?" has to become its own
	// query argument, or WordPress reads the whole thing as one route and 404s.
	const [ route, query ] = path.split( '?' );
	const response = await fetch( `${ base }/?rest_route=/wc/store/v1${ route }${ query ? '&' + query : '' }` );
	if ( ! response.ok ) {
		throw new Error( `Store API ${ path } returned ${ response.status }` );
	}
	return response.json();
};

// What the store sells, as the store itself reports it.
const products = await api( '/products?per_page=100' );
const variable = products.find( ( p ) => 'variable' === p.type && p.is_in_stock );
const simple = products.find( ( p ) => 'simple' === p.type && p.is_purchasable && p.is_in_stock );
const soldOut = products.find( ( p ) => ! p.is_in_stock );
const onSale = products.find( ( p ) => p.on_sale );

check( 'the store has products', products.length > 0, `${ products.length }` );
check( 'a simple product is on sale somewhere', !! onSale, onSale ? onSale.name : 'none' );
check( 'something is out of stock, so the badge can be seen', !! soldOut, soldOut ? soldOut.name : 'none' );

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
const page = await context.newPage();
const errors = [];
page.on( 'pageerror', ( error ) => errors.push( error.message ) );

// The site's own root, not the domain's: on a subdirectory multisite
// "/?rest_route=..." asks the network's main site, which has no cart.
const cartCount = () => page.evaluate( ( site ) =>
	fetch( site + '/?rest_route=/wc/store/v1/cart', { credentials: 'same-origin' } )
		.then( ( r ) => r.json() )
		.then( ( c ) => c.items_count ), base );

// A simple product: the sticky bar follows the shopper past the button.
if ( simple ) {
	await page.goto( `${ base }/?p=${ simple.id }&post_type=product`, { waitUntil: 'load' } );
	const bar = page.locator( '.tyche-sticky-atc' );
	check( 'the product page opens', await page.locator( '.wp-block-woocommerce-product-details, .wc-block-components-product-name, h1' ).first().isVisible() );
	await page.mouse.wheel( 0, 1400 );
	await page.waitForTimeout( 700 );
	check( 'the sticky add-to-cart bar appears once the button scrolls away',
		await bar.first().isVisible().catch( () => false ) );
}

// A variable product: choose options, add, and watch the drawer answer.
if ( variable ) {
	await page.goto( `${ base }/?p=${ variable.id }&post_type=product`, { waitUntil: 'load' } );
	await page.waitForTimeout( 900 );

	const chips = page.locator( '.tyche-product__add .wc-block-product-filter-chips__item' );
	const groups = page.locator( '.tyche-product__add .wc-block-product-filter-chips' );
	const groupCount = await groups.count();
	check( 'the product has options to choose', groupCount > 0, `${ groupCount } attributes, ${ await chips.count() } choices` );

	// One choice per attribute. Clicking a chip that is already selected turns
	// it off, so only unselected ones are clicked.
	for ( let group = 0; group < groupCount; group++ ) {
		const option = groups.nth( group )
			.locator( '.wc-block-product-filter-chips__item[aria-checked="false"]:not([disabled])' )
			.first();
		if ( await option.count() ) {
			await option.click();
			await page.waitForTimeout( 400 );
		}
	}
	const selected = await page.locator( '.tyche-product__add .wc-block-product-filter-chips__item[aria-checked="true"]' ).count();
	check( 'a choice is made in every group', selected === groupCount, `${ selected } of ${ groupCount }` );

	const before = await cartCount();
	const addButton = page.locator( '.tyche-product__add .wc-block-components-product-button__button, .single_add_to_cart_button, button[type="submit"]' ).first();
	check( 'the add-to-cart button is ready', await addButton.isEnabled().catch( () => false ) );
	await addButton.click();
	await page.waitForTimeout( 2500 );
	const after = await cartCount();
	check( 'a variation goes in the cart without a page reload', after === before + 1, `${ before } -> ${ after }` );
	check( 'the cart drawer opens by itself',
		await page.locator( '.wc-block-mini-cart__drawer .wc-block-mini-cart__title' )
			.first().isVisible().catch( () => false ) );

	const drawerBar = page.locator( '.wp-block-tyche-companion-free-shipping-bar' ).first();
	if ( await drawerBar.count() ) {
		const message = ( await drawerBar.locator( '.tyche-fsb__message' ).textContent() ).trim();
		check( 'the drawer says how far the shopper is from free delivery', message.length > 0, message );
	}
}

// The cart page: the same bar, and totals that add up. The count is read in the
// browser, not from Node: the cart belongs to the session that filled it.
await page.goto( `${ base }/cart/`, { waitUntil: 'load' } );
await page.waitForTimeout( 1200 );
const cartBar = page.locator( '.wp-block-tyche-companion-free-shipping-bar' );
check( 'the cart page shows the free-shipping bar', await cartBar.count() > 0,
	await cartBar.count() ? ( await cartBar.first().locator( '.tyche-fsb__message' ).textContent() ).trim() : 'missing' );
const held = await cartCount();
check( 'the cart holds what was added', held > 0, `${ held } items` );

await page.screenshot( { path: `${ shots }/store-cart.png`, fullPage: false } );

// The shop, and its filters.
await page.goto( `${ base }/shop/`, { waitUntil: 'load' } );
await page.waitForTimeout( 800 );
const cards = await page.locator( '.wc-block-product-template li, .wp-block-post-template li' ).count();
check( 'the shop lists products', cards > 0, `${ cards } on the first page` );

check( 'no JavaScript errors anywhere', 0 === errors.length, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

console.log( results.join( '\n' ) );
const failed = results.filter( ( r ) => r.startsWith( 'FAIL' ) ).length;
console.log( `\n${ results.length - failed }/${ results.length } passed` );
process.exit( failed ? 1 : 0 );
