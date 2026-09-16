/**
 * Editor preview. The bar is rendered by render.php so the editor shows exactly
 * what the store will: the real threshold from the shipping settings.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const ServerSideRender = wp.serverSideRender;
	const el = wp.element.createElement;

	registerBlockType( 'tyche-companion/free-shipping-bar', {
		edit: function Edit( props ) {
			return el(
				'div',
				useBlockProps(),
				el( ServerSideRender, { block: 'tyche-companion/free-shipping-bar', attributes: props.attributes } )
			);
		},
		save: () => null,
	} );
}( window.wp ) );
