/**
 * Editor: the block as the store will render it, with the date beside it.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl, ToggleControl } = wp.components;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;
	const el = wp.element.createElement;

	registerBlockType( 'tyche-companion/countdown', {
		edit: function Edit( { attributes, setAttributes } ) {
			const preview = attributes.date
				? el( ServerSideRender, { block: 'tyche-companion/countdown', attributes } )
				: el( 'p', { className: 'tyche-countdown__empty' }, __( 'Choose the date and time to count down to.', 'tyche-companion' ) );

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Countdown', 'tyche-companion' ) },
						el( TextControl, {
							label: __( 'Counts down to', 'tyche-companion' ),
							type: 'datetime-local',
							value: attributes.date,
							onChange: ( date ) => setAttributes( { date } ),
							help: __( 'In your site\'s time zone.', 'tyche-companion' ),
							__nextHasNoMarginBottom: true,
						} ),
						el( TextControl, {
							label: __( 'When it is over', 'tyche-companion' ),
							value: attributes.finished,
							onChange: ( finished ) => setAttributes( { finished } ),
							placeholder: __( 'It is live now', 'tyche-companion' ),
							__nextHasNoMarginBottom: true,
						} ),
						el( ToggleControl, {
							label: __( 'Show days, hrs, min, sec', 'tyche-companion' ),
							checked: attributes.showLabels,
							onChange: ( showLabels ) => setAttributes( { showLabels } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				preview
			);
		},
		save: () => null,
	} );
}( window.wp ) );
