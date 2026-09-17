/**
 * Reveal the sticky add-to-cart bar once the page's own form is scrolled past.
 *
 * "Past" means above the viewport: a form still below the fold (a long gallery
 * on a phone) does not count, or the bar would cover the page it duplicates.
 */
( function () {
	const bar = document.querySelector( '.tyche-sticky-atc' );
	// The Add to Cart with Options block, or the classic add-to-cart form.
	const form = document.querySelector( 'form.wc-block-add-to-cart-with-options, form.cart' );
	if ( ! bar || ! form || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	const button = bar.querySelector( '.tyche-sticky-atc__button' );

	const setVisible = ( visible ) => {
		if ( visible === ! bar.hidden ) {
			return;
		}
		if ( visible ) {
			bar.hidden = false;
			// Next frame, so the transition runs from the hidden state.
			window.requestAnimationFrame( () => bar.classList.add( 'is-visible' ) );
		} else {
			bar.classList.remove( 'is-visible' );
			bar.hidden = true;
		}
		document.body.classList.toggle( 'has-tyche-sticky-atc', visible );
	};

	new IntersectionObserver( ( [ entry ] ) => {
		setVisible( ! entry.isIntersecting && entry.boundingClientRect.bottom < 0 );
	} ).observe( form );

	button.addEventListener( 'click', () => {
		if ( 'submit' === button.dataset.action ) {
			const submit = form.querySelector( '.wc-block-components-product-button__button:not([hidden]), .single_add_to_cart_button, [type="submit"]' );
			if ( submit ) {
				submit.click();
				return;
			}
		}
		const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		form.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'center' } );
		const first = form.querySelector( 'select, input:not([type="hidden"]), button' );
		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	} );
}() );
