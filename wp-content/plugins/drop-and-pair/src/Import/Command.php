<?php
namespace DropAndPair\Import;

defined( 'ABSPATH' ) || exit;

/** WP-CLI: `wp dropandpair import <file> [--dry-run]`. */
final class Command {

	/**
	 * Imports parks, pairings, rides and the About page from the exported content JSON.
	 *
	 * Everything is created as a draft. Slugs that already exist are skipped.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to content.json from scripts/export-content.js.
	 *
	 * [--dry-run]
	 * : Report what would be created without writing anything.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			\WP_CLI::error( "Can't read $file" );
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			\WP_CLI::error( "$file isn't valid JSON" );
		}

		$dry    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$store  = $dry ? new DryRunStore( new WpPostStore() ) : new WpPostStore();
		$report = ( new Importer( $store ) )->run( $data );

		$prefix = $dry ? 'Would create' : 'Created';
		foreach ( $report->created as $label ) {
			\WP_CLI::log( "$prefix $label" );
		}
		foreach ( $report->skipped as $label ) {
			\WP_CLI::log( "Skipped $label (already exists)" );
		}
		foreach ( $report->problems as $problem ) {
			\WP_CLI::warning( $problem );
		}
		\WP_CLI::success(
			sprintf(
				'%s %d, skipped %d, problems %d.',
				$dry ? 'Would create' : 'Created',
				count( $report->created ),
				count( $report->skipped ),
				count( $report->problems )
			)
		);
	}
}
