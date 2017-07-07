<?php
/**
 * Simple file test_custom.php to drop into root of Moodle installation.
 * This is an example of using a sql_table class to format data.
 */
require "../../../config.php";
global $CFG,$PAGE,$OUTPUT;
require "$CFG->libdir/tablelib.php";
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/enrol/arlo/admin/test.php');

$download = optional_param('download', '', PARAM_ALPHA);

$table = new \enrol_arlo\reports\test_table('uniqueid',  array('timelogged', 'type', 'userid', 'delivered', 'extra'));
$table->is_downloading($download, 'test', 'testing123');

if (!$table->is_downloading()) {
    // Only print headers if not asked to download data.
    // Print the page header.
    $PAGE->set_title('Testing');
    $PAGE->set_heading('Testing table class');
    $PAGE->navbar->add('Testing table class', new moodle_url('/enrol/arlo/admin/test.php'));
    echo $OUTPUT->header();
}

// Work out the sql for the table.
$table->set_sql('*', "{enrol_arlo_emaillog}", '1');

$table->define_baseurl("$CFG->wwwroot/enrol/arlo/admin/test.php");

$table->out(10, true);

if (!$table->is_downloading()) {
    $OUTPUT->footer();
}