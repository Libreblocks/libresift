/**
 * Default @wordpress/scripts config plus the shared LibreBlocks UI kit,
 * which lives in libreblocks-core and is bundled into this plugin.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			'@libreblocks/ui': path.resolve( __dirname, '../libreblocks-core/assets/src/ui' ),
		},
	},
};
