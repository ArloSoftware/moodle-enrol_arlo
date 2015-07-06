<?php
/**
 * @package     Arlo Moodle Integration
 * @subpackage  enrol_arlo
 * @author 		Corey Davis
 * @copyright   2015 LearningWorks Ltd <http://www.learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2015070601;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2014050800;        // Requires this Moodle version
$plugin->component = 'enrol_arlo';      // Full name of the plugin (used for diagnostics)
$plugin->maturity = MATURITY_STABLE;  // [MATURITY_STABLE | MATURITY_RC | MATURITY_BETA | MATURITY_ALPHA]
$plugin->release  = '1.0.0';
$plugin->dependencies = array(
    'local_arlo' => 2015070601,
);
$plugin->cron      = 0;