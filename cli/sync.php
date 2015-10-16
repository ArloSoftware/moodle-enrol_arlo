<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @author      Troy Williams
 * @package     enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir  . '/clilib.php'); // CLI only functions.
require_once($CFG->dirroot . '/enrol/arlo/locallib.php');

// We may need a lot of memory here.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'non-interactive'   => false,
        'courseid'          => 0,
        'verbose'           => false,
        'help'              => false
    ),
    array(
        'v' => 'verbose',
        'h' => 'help'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Arlo Synchronisation

Please note you must execute this script with the same uid as apache!

Options:
--non-interactive     No interactive questions or confirmations
--courseid            Course identifier to process
-v, --verbose         Print verbose progress information
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php enrol/arlo/cli/sync.php
"; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

// Ensure errors are well explained.
set_debugging(DEBUG_DEVELOPER, true);

if (!enrol_is_enabled('arlo')) {
    cli_error(get_string('pluginnotenabled', 'enrol_ldap'), 2);
}

$interactive = empty($options['non-interactive']);
if ($interactive) {
    $prompt = "Synchronize Arlo enrolments? type y (means yes) or n (means no)";
    $input = cli_input($prompt, '', array('n', 'y'));
    if ($input == 'n') {
        mtrace('Ok.');
        exit;
    }
}
// Emulate normal session - we use admin account by default.
cron_setup_user();

if (empty($options['verbose'])) {
    $trace = new null_progress_trace();
} else {
    $trace = new text_progress_trace();
}
$courseid = $options['courseid'];
$result = enrol_arlo_sync($trace, $courseid);
exit($result);
