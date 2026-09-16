/**
 * Keeps free shipping progress current as the cart changes, without reloading.
 *
 * WooCommerce's blocks announce additions and removals with DOM events, and the
 * cart and mini-cart keep a data store that changes when quantities do. Either
 * way the numbers come from the Store API cart, in the store's minor units,
 * and are measured the way WooCommerce decides free shipping: the displayed
 * item total, less discounts unless the method ignores them.
 */
( function () {
	const bars = () => document.querySelectorAll( '.wp-block-tyche-companion-free-shipping-bar[data-min]' );
	if ( ! bars().length ) {
		return;
	}

	const pick = ( totals, snake, camel ) => Number( totals[ snake ] ?? totals[ camel ] ?? 0 );

	const format = ( minor, totals ) => {
		const unit = pick( totals, 'currency_minor_unit', 'currencyMinorUnit' );
		const fixed = ( minor / Math.pow( 10, unit ) ).toFixed( unit );
		const parts = fixed.split( '.' );
		const thousands = totals.currency_thousand_separator ?? totals.currencyThousandSeparator ?? ',';
		const decimal = totals.currency_decimal_separator ?? totals.currencyDecimalSeparator ?? '.';
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, thousands );
		const prefix = totals.currency_prefix ?? totals.currencyPrefix ?? '';
		const suffix = totals.currency_suffix ?? totals.currencySuffix ?? '';
		return prefix + ( parts[ 1 ] ? parts[ 0 ] + decimal + parts[ 1 ] : parts[ 0 ] ) + suffix;
	};

	const render = ( totals ) => {
		const unit = Math.pow( 10, pick( totals, 'currency_minor_unit', 'currencyMinorUnit' ) );

		bars().forEach( ( bar ) => {
			const inclTax = '1' === bar.dataset.inclTax;
			let total = pick( totals, 'total_items', 'totalItems' ) + ( inclTax ? pick( totals, 'total_items_tax', 'totalItemsTax' ) : 0 );
			if ( '1' !== bar.dataset.ignoreDiscounts ) {
				total -= pick( totals, 'total_discount', 'totalDiscount' ) + ( inclTax ? pick( totals, 'total_discount_tax', 'totalDiscountTax' ) : 0 );
			}

			const min = Math.round( parseFloat( bar.dataset.min ) * unit );
			const remaining = Math.max( 0, min - total );
			const percent = Math.max( 0, Math.min( 100, Math.floor( ( total / min ) * 100 ) ) );
			const message = bar.querySelector( '.tyche-fsb__message' );
			const track = bar.querySelector( '.tyche-fsb__track' );

			bar.classList.toggle( 'is-reached', 0 === remaining );
			track.setAttribute( 'aria-valuenow', String( percent ) );
			bar.querySelector( '.tyche-fsb__fill' ).style.width = percent + '%';

			if ( 0 === remaining ) {
				message.textContent = bar.dataset.textReached;
			} else {
				const [ before, after ] = bar.dataset.textRemaining.split( '%s' );
				const strong = document.createElement( 'strong' );
				strong.textContent = format( remaining, totals );
				message.replaceChildren( before || '', strong, after || '' );
			}
		} );
	};

	let timer;
	const refresh = () => {
		clearTimeout( timer );
		timer = setTimeout( () => {
			const url = bars()[ 0 ]?.dataset.cartUrl;
			if ( ! url ) {
				return;
			}
			fetch( url, { credentials: 'same-origin' } )
				.then( ( response ) => ( response.ok ? response.json() : null ) )
				.then( ( cart ) => cart && cart.totals && render( cart.totals ) )
				.catch( () => {} );
		}, 200 );
	};

	[ 'wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart' ].forEach( ( name ) => {
		document.body.addEventListener( name, refresh );
	} );

	// The cart page and the mini-cart keep the cart in a data store; quantity
	// steppers change it without firing the events above.
	const data = window.wp && window.wp.data;
	if ( data && data.subscribe && data.select ) {
		let last;
		data.subscribe( () => {
			const store = data.select( 'wc/store/cart' );
			const totals = store && store.getCartTotals ? store.getCartTotals() : null;
			if ( totals && totals !== last && ( totals.total_items !== undefined || totals.totalItems !== undefined ) ) {
				last = totals;
				render( totals );
			}
		} );
	}
}() );
