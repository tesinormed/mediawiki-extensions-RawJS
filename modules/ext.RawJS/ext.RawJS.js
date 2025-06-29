for ( const source of Object.keys( mw.config.get( 'wgRawJsSources' ) ) ) {
	mw.loader.load( source );
}
