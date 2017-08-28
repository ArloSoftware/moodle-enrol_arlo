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
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/cronlib.php');
require_once($CFG->dirroot.'/enrol/arlo/lib.php');

// We may need a lot of resource.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'help'              => false
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
    $help = "
CLI sync for processing emails

This script is meant to be called from a cronjob to process emails in queue.

Sample cron entry:
Every 5 minutes
* / 5 * * * * \$sudo -u www-data /usr/bin/php /var/www/moodle/enrol/arlo/cli/processemailqueue.php

Notes:
- It is required to use the web server account when executing PHP CLI scripts.
- You need to change the \"www-data\" to match the apache user account
- Use \"su\" if \"sudo\" not available
- If you have a large number of users, you may want to raise the memory limits by passing -d memory_limit=256M
- For debugging & better logging, you are encouraged to use in the command line:
  -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0

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

define('ENROL_ARLO_CLI_EMAIL_PROCESSING', true);

$manager = new enrol_arlo\manager(
    new \text_progress_trace()
);
$manager->process_email_queue();
exit(0);
