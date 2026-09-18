/**
 * Tick the countdown.
 *
 * The server has already printed the right numbers, so this only keeps them
 * moving, and swaps in the finished message when the moment arrives instead of
 * counting into negative numbers.
 */
( function () {
	const blocks = document.querySelectorAll( '.wp-block-tyche-companion-countdown' );
	if ( ! blocks.length ) {
		return;
	}

	const pad = ( value ) => String( Math.max( 0, value ) ).padStart( 2, '0' );

	const paint = ( block ) => {
		const deadline = Number( block.dataset.deadline );
		if ( ! deadline ) {
			return true;
		}

		const left = Math.floor( ( deadline - Date.now() ) / 1000 );

		if ( left <= 0 ) {
			const units = block.querySelector( '.tyche-countdown__units' );
			if ( units ) {
				const message = document.createElement( 'p' );
				message.className = 'tyche-countdown__finished';
				message.textContent = block.dataset.finished || '';
				units.replaceWith( message );
				block.classList.add( 'is-finished' );
				const spoken = block.querySelector( '.screen-reader-text' );
				if ( spoken ) {
					spoken.remove();
				}
			}
			return true;
		}

		const values = {
			days: Math.floor( left / 86400 ),
			hours: Math.floor( ( left % 86400 ) / 3600 ),
			minutes: Math.floor( ( left % 3600 ) / 60 ),
			seconds: left % 60,
		};

		Object.keys( values ).forEach( ( unit ) => {
			const cell = block.querySelector( `[data-unit="${ unit }"]` );
			if ( cell ) {
				const next = pad( values[ unit ] );
				if ( cell.textContent !== next ) {
					cell.textContent = next;
				}
			}
		} );

		return false;
	};

	const live = Array.from( blocks ).filter( ( block ) => ! paint( block ) );
	if ( ! live.length ) {
		return;
	}

	const timer = setInterval( () => {
		const running = live.filter( ( block ) => ! paint( block ) );
		if ( ! running.length ) {
			clearInterval( timer );
		}
	}, 1000 );
}() );
