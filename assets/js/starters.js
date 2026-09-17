/**
 * The Starter sites screen.
 *
 * Importing runs a step at a time: each request does a little work and says how
 * far along it is, so the browser shows progress instead of waiting on one long
 * request that a host may cut short.
 */
( function () {
	const config = window.tycheStarters || { strings: {}, home: '/' };
	const strings = config.strings;
	const panel = document.querySelector( '.tyche-starters__progress' );
	const text = document.querySelector( '.tyche-starters__progress-text' );
	const bar = document.querySelector( '.tyche-starters__bar span' );

	const show = ( message, percent ) => {
		if ( ! panel ) {
			return;
		}
		panel.hidden = false;
		text.textContent = message;
		bar.style.width = ( percent || 0 ) + '%';
	};

	const fail = ( error ) => {
		const message = error && error.message ? error.message : '';
		show( ( strings.failed || 'The import stopped:' ) + ' ' + message, 100 );
		if ( bar ) {
			bar.classList.add( 'is-failed' );
		}
	};

	const post = ( path, data ) =>
		window.wp.apiFetch( {
			path: '/tyche-companion/v1/' + path,
			method: 'POST',
			data: data || {},
		} );

	const runSteps = () =>
		post( 'import/step' ).then( ( state ) => {
			show( ( strings.importing || 'Importing' ) + ': ' + state.message, state.progress );
			if ( state.complete ) {
				show( strings.done || 'Done.', 100 );
				window.location.href = config.home;
				return null;
			}
			return runSteps();
		} );

	document.querySelectorAll( '.tyche-starter__import' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const card = button.closest( '.tyche-starter' );
			if ( ! card ) {
				return;
			}
			document.querySelectorAll( '.tyche-starter__import' ).forEach( ( other ) => {
				other.disabled = true;
			} );
			show( strings.importing || 'Importing', 2 );
			post( 'import', { slug: card.dataset.slug, mode: button.dataset.mode } )
				.then( runSteps )
				.catch( fail );
		} );
	} );

	const remove = document.querySelector( '.tyche-starters__remove' );
	if ( remove ) {
		remove.addEventListener( 'click', () => {
			if ( ! window.confirm( strings.confirm || 'Remove the starter content?' ) ) {
				return;
			}
			show( strings.removing || 'Removing', 50 );
			post( 'import/remove' )
				.then( () => {
					show( strings.removed || 'Removed.', 100 );
					window.location.reload();
				} )
				.catch( fail );
		} );
	}
}() );
