<?php

namespace MediaWiki\Extension\RawJS;

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Page\PageLookup;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Title\Title;

class MainHookHandler implements ParserFirstCallInitHook, ContentHandlerDefaultModelForHook {
	private PageLookup $pageLookup;

	public function __construct( PageLookup $pageLookup ) {
		$this->pageLookup = $pageLookup;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setHook( 'rawjs', [ $this, 'renderTag' ] );
	}

	public function renderTag( ?string $text, array $params, Parser $parser, PPFrame $frame ): string {
		if ( trim( $params['src'] ?? '' ) === '' ) {
			return self::formatError( $parser, 'rawjs-tag-missing-src' );
		}

		$sourcePage = $this->pageLookup->getPageByName(
			NS_MEDIAWIKI,
			'RawJS-' . str_replace( ' ', '_', $params['src'] )
		);
		if ( $sourcePage === null ) {
			return self::formatError(
				$parser,
				'rawjs-tag-invalid-src',
				$parser->getContentLanguage()->getFormattedNsText( NS_MEDIAWIKI ),
				wfEscapeWikiText( $params['src'] )
			);
		}

		$parser->getOutput()->addModules( [ 'ext.RawJS' ] );
		$parser->getOutput()->appendJsConfigVar(
			'wgRawJsSources',
			Title::newFromPageIdentity( $sourcePage )->getFullURL( [
				'action' => 'raw',
				'ctype' => 'text/javascript'
			] )
		);

		return '';
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ContentHandlerDefaultModelFor
	 * @inheritDoc
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ): bool {
		if ( $title->getNamespace() === NS_MEDIAWIKI && str_starts_with( $title->getText(), 'RawJS-' ) ) {
			$model = CONTENT_MODEL_JAVASCRIPT;
			return false;
		}

		return true;
	}

	private static function formatError( Parser $parser, mixed $key, mixed ...$params ): string {
		$parser->addTrackingCategory( 'rawjs-tracking-category' );
		return '<strong class="error">'
			. wfMessage( $key, ...$params )->inContentLanguage()->parse()
			. '</strong>';
	}
}
