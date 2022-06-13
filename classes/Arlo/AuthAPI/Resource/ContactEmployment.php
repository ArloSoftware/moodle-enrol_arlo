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
 * ContactEmployment resource definition for enrol_arlo.
 *
 * @package     enrol_arlo
 * @author      Donald Barrett <donaldb@skills.org.nz>
 * @copyright   2022 onwards, Skills Consulting Group Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link        https://developer.arlo.co/doc/api/2012-02-01/auth/resources/contactemployment
 */

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class ContactEmployment
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class ContactEmployment extends AbstractResource {
    /**
     * A string describing the position of this individual within the organisation, up to 64 characters long.
     * @var string $Position
     */
    public $Position;

    /**
     * A string describing the department this individual is associated with in the organisation, up to 64 characters long.
     * @var string $Department
     */
    public $Department;

    /**
     * A string describing the branch of the organisation that employs this individual, up to 64 characters long.
     * @var string $Branch
     */
    public $Branch;

    /**
     * A string describing the business region this individual is associated with, up to 64 characters long.
     * @var string $BusinessRegion
     */
    public $BusinessRegion;

    /**
     * A string describing the location of the business this individual is associated with, up to 64 characters long.
     * @var string $BusinessLocation
     */
    public $BusinessLocation;

    /**
     * A string describing the area of the business this individual is associated with, up to 64 characters long.
     * @var string $BusinessArea
     */
    public $BusinessArea;

    /**
     * A string describing the type of employment such as Contractor or Consultant, up to 64 characters long.
     * @var string $EmploymentStatus
     */
    public $EmploymentStatus;

    /**
     * @var Contact $Contact Reference to the Contact resource representing the individual associated with this resource.
     */
    public $Contact;

    /**
     * Reference to the Organisation resource representing the organisation associated with this resource.
     * @var Organisation $Organisation
     */
    public $Organisation;

    /** @var Contact $Manager Reference to a Contact resource representing the manager of this individual. */
    public $Manager;
}
