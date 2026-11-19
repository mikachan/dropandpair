<?php
namespace DropAndPair\Import;

defined( 'ABSPATH' ) || exit;

/** What an import run did. Entries are "type:slug". */
final class ImportReport {

	/** @var string[] */
	public array $created = array();
	/** @var string[] Already existed, left untouched. */
	public array $skipped = array();
	/** @var string[] Things the author needs to fix in the content. */
	public array $problems = array();
}
