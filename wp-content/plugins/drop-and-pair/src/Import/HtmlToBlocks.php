<?php
namespace DropAndPair\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the rendered Markdown HTML from the SvelteKit content into block markup.
 *
 * Pure PHP (DOMDocument, no WordPress calls) so PHPUnit can run it. Anything we
 * don't map becomes a `core/html` block, which keeps the content intact rather
 * than guessing at a block that might change it.
 */
final class HtmlToBlocks {

	public static function convert( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}
		$doc = new \DOMDocument();
		// The XML declaration makes DOMDocument read the input as UTF-8.
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		return $body ? self::blocks_for_children( $doc, $body ) : '';
	}

	private static function blocks_for_children( \DOMDocument $doc, \DOMNode $parent ): string {
		$blocks = array();
		foreach ( $parent->childNodes as $node ) {
			if ( XML_TEXT_NODE === $node->nodeType && '' === trim( $node->textContent ) ) {
				continue; // Whitespace between elements.
			}
			$blocks[] = self::block_for( $doc, $node );
		}
		return implode( "\n\n", $blocks );
	}

	private static function block_for( \DOMDocument $doc, \DOMNode $node ): string {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return self::html_block( trim( $doc->saveHTML( $node ) ) );
		}
		$tag = strtolower( $node->nodeName );

		switch ( $tag ) {
			case 'p':
				return self::wrap( 'paragraph', '', '<p>' . self::inner( $doc, $node ) . '</p>' );
			case 'h2':
			case 'h3':
			case 'h4':
				$level = (int) substr( $tag, 1 );
				// Level 2 is the block's default, so it carries no attribute.
				$attrs = 2 === $level ? '' : json_encode( array( 'level' => $level ) );
				return self::wrap( 'heading', $attrs, "<$tag class=\"wp-block-heading\">" . self::inner( $doc, $node ) . "</$tag>" );
			case 'ul':
			case 'ol':
				return self::list_block( $doc, $node );
			case 'blockquote':
				return self::wrap(
					'quote',
					'',
					'<blockquote class="wp-block-quote">' . "\n" . self::blocks_for_children( $doc, $node ) . "\n" . '</blockquote>'
				);
			default:
				return self::html_block( trim( $doc->saveHTML( $node ) ) );
		}
	}

	private static function list_block( \DOMDocument $doc, \DOMElement $list ): string {
		$tag     = strtolower( $list->nodeName );
		$ordered = 'ol' === $tag;
		$items   = '';
		foreach ( $list->childNodes as $li ) {
			if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
				continue;
			}
			// A nested list is an inner block of the item; everything else is its text.
			$text   = '';
			$nested = '';
			foreach ( $li->childNodes as $child ) {
				if ( XML_ELEMENT_NODE === $child->nodeType && in_array( strtolower( $child->nodeName ), array( 'ul', 'ol' ), true ) ) {
					$nested .= "\n" . self::list_block( $doc, $child );
				} else {
					$text .= $doc->saveHTML( $child );
				}
			}
			$items .= self::wrap( 'list-item', '', '<li>' . trim( $text ) . $nested . '</li>' );
		}
		return self::wrap(
			'list',
			$ordered ? json_encode( array( 'ordered' => true ) ) : '',
			"<$tag class=\"wp-block-list\">" . $items . "</$tag>"
		);
	}

	private static function inner( \DOMDocument $doc, \DOMNode $node ): string {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}
		return $out;
	}

	private static function html_block( string $html ): string {
		return self::wrap( 'html', '', $html );
	}

	private static function wrap( string $name, string $attrs, string $markup ): string {
		$open = '' === $attrs ? "<!-- wp:$name -->" : "<!-- wp:$name $attrs -->";
		return "$open\n$markup\n<!-- /wp:$name -->";
	}
}
