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
        'help'              => false,
        'manual'            => false
    ),
    array(
        'h' => 'help'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"
Options:
--non-interactive     No interactive questions or confirmations
--manual              Manual override
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php synchronize.php
";
    echo $help;
    die;
}

// Ensure errors are well explained.
set_debugging(DEBUG_DEVELOPER, true);

$interactive = empty($options['non-interactive']);

cron_setup_user();

$manualoverride = $options['manual'];

$plugin = enrol_get_plugin('arlo');
$manager = new enrol_arlo\manager(
    new \text_progress_trace()
);
$manager->process_all($manualoverride);
exit(0);
