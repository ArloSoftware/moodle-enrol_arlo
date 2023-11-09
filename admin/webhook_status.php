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
 * @package   enrol_arlo
 *
 * @author    2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @copyright Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_arlo\form\admin\webhook_form;
use enrol_arlo\input\webhook_handler;

require_once(__DIR__ . '/../../../config.php');

// Set up the page
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/enrol/arlo/webhook_status.php');
$PAGE->set_title("Arlo webhook status");
$PAGE->set_heading("Arlo webhook status");

// Define any necessary variables
$webhookhandler = new webhook_handler();
$form = new webhook_form();

// Output the view content
echo $OUTPUT->header();

if ($webhookhandler->webhook_is_enable()) {
    if  ($webhookhandler->validatedwebhookid()) {
        echo get_string('webhookenabled', 'enrol_arlo');
    } else {
        if ($data = $form->get_data()) {
            $webhookhandler->createwebhook($data);
        } else {
            echo get_string('webhookinactive', 'enrol_arlo');
            $form->display();
        }
    }
} else {
    echo get_string('webhookdisabled', 'enrol_arlo');
}

echo $OUTPUT->footer();
