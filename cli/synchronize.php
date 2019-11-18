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

define('CLI_SCRIPT', true);
require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');         // CLI only functions.
require_once($CFG->libdir.'/cronlib.php');
require_once($CFG->dirroot.'/enrol/arlo/lib.php');

// We may need a lot of memory here.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'non-interactive'   => false,
        'verbose'           => false,
        'help'              => false,
        'manual'            => false
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
    $help = "
Options:
--non-interactive     No interactive questions or confirmations
--manual              Manual override
-v, --verbose         Print verbose progess information
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php synchronize.php
";
    echo $help;
    die;
}

// Ensure errors are well explained.
set_debugging(DEBUG_DEVELOPER, true);

if (!enrol_is_enabled('arlo')) {
    cli_error(get_string('pluginnotenabled', 'enrol_arlo'), 2);
}

cron_setup_user();

if (empty($options['verbose'])) {
    $trace = new null_progress_trace();
} else {
    $trace = new text_progress_trace();
}

$manualoverride = $options['manual'];
$time = null;
if ($manualoverride) {
    $time = time() + 1800; // 30 minutes in the future.
}
$interactive = empty($options['non-interactive']);
if ($interactive) {
    cli_writeln('Run Arlo/Moodle synchronisation');
    $prompt = get_string('cliyesnoprompt', 'admin');
    $input = cli_input($prompt, '',
        array(get_string('clianswerno', 'admin'), get_string('cliansweryes', 'admin')));
    if ($input == get_string('clianswerno', 'admin')) {
        exit(1);
    }
}
enrol_arlo\api::run_site_jobs($trace);
enrol_arlo\api::run_scheduled_jobs('enrolment', 'memberships', $time, null, $trace);
enrol_arlo\api::run_scheduled_jobs('enrolment', 'outcomes', $time, null, $trace);
enrol_arlo\api::run_scheduled_jobs('enrolment', 'contacts', $time, null, $trace);
enrol_arlo\api::run_cleanup();
$manager = new enrol_arlo\manager();
$manager->process_expirations();
$manager->process_email_queue();
exit(0);
