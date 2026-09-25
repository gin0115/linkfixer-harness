<?php
/**
 * The list of test suites, in the order "Run all" uses.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * Suite registry.
 */
class Suites {

	/**
	 * Suite instances by slug.
	 *
	 * @var array<string, Suite>|null
	 */
	private static $all = null;

	/**
	 * Every suite, by slug, in run order.
	 *
	 * @return array<string, Suite>
	 */
	public static function all(): array {
		if ( null === self::$all ) {
			self::$all = array();
			foreach ( array(
				new Suite_Links_On_Page(),
				new Suite_Display_Modes(),
				new Suite_Setup_Wizard(),
			) as $suite ) {
				self::$all[ $suite->slug() ] = $suite;
			}
		}
		return self::$all;
	}

	/**
	 * A suite by slug.
	 *
	 * @param string $slug The slug.
	 *
	 * @return Suite|null
	 */
	public static function get( string $slug ): ?Suite {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * Slug, title, summary and step count of every suite, for the Tests page and the runner.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function describe(): array {
		$list = array();
		foreach ( self::all() as $slug => $suite ) {
			$list[] = array(
				'slug'    => $slug,
				'title'   => $suite->title(),
				'summary' => $suite->summary(),
				'steps'   => array_map( static fn( $step ) => $step['title'], $suite->steps() ),
			);
		}
		return $list;
	}
}
