for ( const source of mw.config.get( 'wgRawJsSources' ) ) {
	mw.loader.load( source );
}
