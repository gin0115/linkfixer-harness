<?php
/**
 * Base for a test suite: a list of plain English steps the runner works through.
 *
 * @package LinkFixer_Harness
 */

namespace LinkFixer_Harness;

defined( 'ABSPATH' ) || exit;

/**
 * A suite is data: its steps.
 *
 * Each step is an array:
 *
 * title  - plain English sentence, what the step proves. This is what a project manager reads.
 * setup  - server actions run before the page is opened, each an array: [ name, ...args ].
 *          seed <scenario> | age <scenario> <days> | options { option: value, null deletes }
 *          environment production|staging|'' | archive_online yes|no | reset_wizard | run_queue { ... }
 * open   - the page to open: "admin:<path>", "post:<scenario>:<role>", "front:<path>",
 *          or null to stay on the page the previous step's "then" led to.
 * checks - what must be true, each an array with a "type":
 *          In the browser: url (has|lacks), text (selector, has), exists (selector), missing (selector),
 *          checked (selector, is), count (selector, is), links (every panel row passed),
 *          link (id, swapped), script (loaded).
 *          On the server: option (name, is), action (hook, status, count|min, due_within),
 *          link_row (scenario, id, field, is), calls (method, url_has, min).
 *          Any check can carry "say", a plain English line shown instead of the generated one.
 * then   - browser actions after the checks, each an array: click|check|uncheck|fill => selector, value.
 *          A click that submits a form navigates; the next step then opens with "open" null.
 */
abstract class Suite {

	/**
	 * Unique slug.
	 *
	 * @return string
	 */
	abstract public function slug(): string;

	/**
	 * Plain English name.
	 *
	 * @return string
	 */
	abstract public function title(): string;

	/**
	 * Plain English summary of what the suite proves.
	 *
	 * @return string
	 */
	abstract public function summary(): string;

	/**
	 * The steps.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract public function steps(): array;

	/**
	 * One step, or null past the end.
	 *
	 * @param integer $index Zero based.
	 *
	 * @return array<string, mixed>|null
	 */
	public function step( int $index ): ?array {
		$steps = $this->steps();
		if ( ! isset( $steps[ $index ] ) ) {
			return null;
		}
		return array_merge(
			array(
				'title'  => '',
				'setup'  => array(),
				'open'   => null,
				'checks' => array(),
				'then'   => array(),
			),
			$steps[ $index ]
		);
	}
}
