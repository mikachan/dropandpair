<?php
declare(strict_types=1);

namespace DropAndPair\Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {
	public function test_harness_loads(): void {
		$this->assertTrue( defined( 'ABSPATH' ) );

		$prefix_registered = false;
		foreach ( spl_autoload_functions() as $loader ) {
			if ( is_array( $loader ) && $loader[0] instanceof \Composer\Autoload\ClassLoader
				&& isset( $loader[0]->getPrefixesPsr4()['DropAndPair\\'] ) ) {
				$prefix_registered = true;
			}
		}
		$this->assertTrue( $prefix_registered, 'Composer PSR-4 autoloader for DropAndPair\\ is registered' );
	}
}
