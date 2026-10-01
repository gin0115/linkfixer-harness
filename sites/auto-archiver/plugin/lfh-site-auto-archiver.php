<?php
/**
 * Plugin Name: Link Fixer Harness site: Auto Archiver
 * Description: Four posts at each point of the Auto Archiver's 28 day cycle. The checklist panel covers the Last Archived column, the routine rescan, archiving on publish, excluded posts and Archive.org going offline.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: linkfixer-harness
 * Author: Glynn Quelch
 * License: GPL-2.0-or-later
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

use Internet_Archive\Wayback_Machine_Link_Fixer\Settings\Settings;

defined( 'ABSPATH' ) || exit;

add_action(
	'lfh_loaded',
	static function () {
		require_once __DIR__ . '/Scenario_Auto_Archiver.php';
		add_filter( 'lfh_scenarios', static fn( $scenarios ) => array_merge( $scenarios, array( new Scenario_Auto_Archiver() ) ) );

		// Jobs only run when the panel's buttons say so.
		Queue::manual();
	}
);

add_filter(
	'lfh_checklist',
	static function () {
		$scenario = Scenarios::get( 'auto-archiver' );
		$registry = $scenario ? (array) $scenario->registry() : array();
		$posts    = array_merge(
			array(
				'fresh'    => 0,
				'recent'   => 0,
				'stale'    => 0,
				'excluded' => 0,
			),
			(array) ( $registry['posts'] ?? array() )
		);

		// The tester's new post: the newest published post that was not seeded.
		$seeded = array_map( 'intval', array_values( $posts ) );
		$found  = get_posts(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'numberposts'  => 1,
				'orderby'      => 'ID',
				'order'        => 'DESC',
				'fields'       => 'ids',
				'post__not_in' => $seeded,
			)
		);
		$newest = ! empty( $found ) && (int) $found[0] > max( $seeded ) ? (int) $found[0] : 0;

		$permalink = static fn( $post_id ) => $post_id ? (string) get_permalink( $post_id ) : 'no-such-post';
		$job       = static fn( $post_id, $status, $extra = array() ) => array_merge(
			array(
				'type'   => 'action',
				'hook'   => 'iawmlf_process_local_post',
				'status' => $status,
				'args'   => array( 'post_id' => $post_id ),
			),
			$extra
		);
		$snapshots = static fn( $post_id, $min, $max, $say ) => array(
			'type'    => 'calls',
			'method'  => 'create_snapshot',
			'url_has' => $permalink( $post_id ),
			'min'     => $min,
			'max'     => $max,
			'say'     => $say,
		);
		$archived  = static fn( $post_id, $say ) => array(
			'type'   => 'post_meta',
			'post'   => $post_id,
			'key'    => Settings::OWN_LINK_LAST_PROCESSED,
			'within' => 10 * MINUTE_IN_SECONDS,
			'say'    => $say,
		);
		$online    = static fn( $is ) => array(
			'type' => 'option',
			'name' => 'lfh_archive_online',
			'is'   => $is,
		);
		$exists    = static fn( $selector, $say = '' ) => array_filter(
			array(
				'type'     => 'exists',
				'selector' => $selector,
				'say'      => $say,
			)
		);
		$cell      = static fn( $role ) => '#post-' . $posts[ $role ] . ' td.column-wayback_archived';
		$list      = $exists( 'body.edit-php.post-type-post' );
		$all       = array( 'pending', 'complete', 'failed' );
		$hour      = array(
			'due_min' => 50 * MINUTE_IN_SECONDS,
			'due_max' => 65 * MINUTE_IN_SECONDS,
		);

		return array(
			'title'         => 'Auto Archiver',
			'intro'         => 'The Auto Archiver asks Archive.org to snapshot your own posts: an hour after you publish or update one, and again once 28 days have passed. Four posts are seeded: never archived, archived 3 days ago, archived 30 days ago, and one excluded from auto archiving. Jobs only run when you press Run next job.',
			'queue'         => true,
			'online_toggle' => true,
			'items'         => array(
				array(
					'id'     => 'column-hidden',
					'do'     => 'Open Posts.',
					'expect' => 'The Links column shows. The Last Archived column is hidden until you choose it in Screen Options.',
					'link'   => 'admin:edit.php',
					'when'   => array( $list ),
					'checks' => array(
						$exists( 'th.column-wayback_links', 'The Links column shows' ),
						$exists( 'th.column-wayback_archived.hidden', 'Last Archived is hidden' ),
						array(
							'type'     => 'checked',
							'selector' => '#wayback_archived-hide',
							'is'       => false,
							'say'      => 'Last Archived is not ticked in Screen Options',
						),
					),
				),
				array(
					'id'     => 'column-shown',
					'do'     => 'Open Screen Options (top right) and tick "Last Archived".',
					'expect' => 'The column shows "Never archived" for "Harness: never archived", a date for the posts archived 3 and 30 days ago, and "Excluded post" for "Harness: excluded from auto archiving".',
					'when'   => array(
						$list,
						array(
							'type'     => 'checked',
							'selector' => '#wayback_archived-hide',
							'is'       => true,
						),
					),
					'checks' => array(
						array(
							'type'     => 'visible',
							'selector' => 'th.column-wayback_archived',
							'is'       => true,
							'say'      => 'Last Archived shows',
						),
						array(
							'type'     => 'text',
							'selector' => $cell( 'fresh' ),
							'has'      => 'Never archived',
							'say'      => '"Harness: never archived" says "Never archived"',
						),
						$exists( $cell( 'recent' ) . ' time', '"Harness: archived 3 days ago" shows a date' ),
						$exists( $cell( 'stale' ) . ' time', '"Harness: archived 30 days ago" shows a date' ),
						array(
							'type'     => 'text',
							'selector' => $cell( 'excluded' ),
							'has'      => 'Excluded post',
							'say'      => '"Harness: excluded from auto archiving" says "Excluded post"',
						),
					),
				),
				array(
					'id'     => 'scan',
					'do'     => 'Press Run next job until "Queue your posts for archiving" has run.',
					'expect' => 'It queues "Harness: never archived" and "Harness: archived 30 days ago" to be archived. It skips the post archived 3 days ago and the excluded post.',
					'when'   => array(
						array(
							'type'   => 'action',
							'hook'   => 'iawmlf_add_own_posts',
							'status' => 'complete',
							'min'    => 1,
						),
					),
					'checks' => array(
						$job( $posts['fresh'], array( 'pending', 'complete' ), array( 'say' => '"Harness: never archived" is queued' ) ),
						$job( $posts['stale'], array( 'pending', 'complete' ), array( 'say' => '"Harness: archived 30 days ago" is queued' ) ),
						$job(
							$posts['recent'],
							$all,
							array(
								'count' => 0,
								'say'   => '"Harness: archived 3 days ago" is not queued',
							)
						),
						$job(
							$posts['excluded'],
							$all,
							array(
								'count' => 0,
								'say'   => 'The excluded post is not queued',
							)
						),
					),
				),
				array(
					'id'     => 'fresh-archived',
					'do'     => 'Keep pressing Run next job until "Harness: never archived" has been archived.',
					'expect' => 'Archive.org is asked once to snapshot the post\'s own address, and its Last Archived date is now.',
					'when'   => array( $job( $posts['fresh'], 'complete' ) ),
					'checks' => array(
						$snapshots( $posts['fresh'], 1, 1, 'One snapshot of the post\'s address was asked for' ),
						$archived( $posts['fresh'], 'Last Archived is now' ),
					),
				),
				array(
					'id'     => 'stale-archived',
					'do'     => 'Keep pressing Run next job until "Harness: archived 30 days ago" has been archived.',
					'expect' => 'Archive.org is asked once to snapshot it, and its Last Archived date moves from 30 days ago to now.',
					'when'   => array( $job( $posts['stale'], 'complete' ) ),
					'checks' => array(
						$snapshots( $posts['stale'], 1, 1, 'One snapshot of the post\'s address was asked for' ),
						$archived( $posts['stale'], 'Last Archived is now' ),
					),
				),
				array(
					'id'     => 'publish-queued',
					'do'     => 'Write and publish a new post (any title), then open Posts.',
					'expect' => 'A job to archive your new post is queued for about 1 hour from now.',
					'when'   => array( $list, $job( $newest, $all ) ),
					'checks' => array( $job( $newest, 'pending', array_merge( $hour, array( 'say' => 'Your post\'s job is due in about 1 hour' ) ) ) ),
				),
				array(
					'id'     => 'publish-archived',
					'do'     => 'Press Run next job until your new post has been archived (its job is an hour away, so it comes last).',
					'expect' => 'Archive.org is asked once to snapshot your post\'s address, and its Last Archived date is now.',
					'when'   => array( $job( $newest, 'complete' ) ),
					'checks' => array(
						$snapshots( $newest, 1, 1, 'One snapshot of your post\'s address was asked for' ),
						$archived( $newest, 'Last Archived is now' ),
					),
				),
				array(
					'id'     => 'excluded-update',
					'do'     => 'Open "Harness: excluded from auto archiving", press Update, then open Posts.',
					'expect' => 'No job is queued for it, because it is excluded from auto archiving.',
					'when'   => array(
						$list,
						array(
							'type' => 'option',
							'name' => 'lfh_saved_posts',
							'has'  => $posts['excluded'],
						),
					),
					'checks' => array(
						$job(
							$posts['excluded'],
							$all,
							array(
								'count' => 0,
								'say'   => 'The excluded post has no job',
							)
						),
					),
				),
				array(
					'id'     => 'offline-update',
					'do'     => 'Press "Take offline" above. Open your new post, press Update, then press Run next job until its job has run.',
					'expect' => 'Archive.org is not asked anything: the job fails ("Service is offline, trying again in 1 hour.") and a new one is queued for 1 hour from now.',
					'when'   => array( $online( 'no' ), $job( $newest, 'failed' ) ),
					'checks' => array(
						$job( $newest, 'pending', array_merge( $hour, array( 'say' => 'A new job is due in about 1 hour' ) ) ),
						$snapshots( $newest, 1, 1, 'No new snapshot was asked for (still the one from before)' ),
					),
				),
				array(
					'id'     => 'back-online',
					'do'     => 'Press "Bring back online", then press Run next job until your post\'s job has run again.',
					'expect' => 'This time Archive.org is asked to snapshot your post.',
					'when'   => array(
						$online( 'yes' ),
						$job( $newest, 'complete', array( 'min' => 2 ) ),
					),
					'checks' => array( $snapshots( $newest, 2, 2, 'A second snapshot of your post was asked for' ) ),
				),
			),
		);
	}
);
