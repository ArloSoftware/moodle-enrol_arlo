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

require_once(__DIR__ . '/../../config.php');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the request body
    $webhookhandler = new enrol_arlo\input\webhook_handler();
    if (!$webhookhandler->webhook_is_enable()) {
        http_response_code(401);
        echo 'Webhook is not enable';
        exit;
    }
    $requestbody = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_ARLO_SIGNATURE'];
    $webhookhandler->validatesignature($signature, $requestbody);
    $webhookhandler->process_events(json_decode($requestbody)->events);

    http_response_code(200);
    echo 'Successful request';
} else {
    // Send a 405 Method Not Allowed response
    http_response_code(405);
    header('Allow: POST');
    echo 'Method Not Allowed';
}