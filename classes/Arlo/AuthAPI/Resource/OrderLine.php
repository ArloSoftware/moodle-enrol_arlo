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
 * OrderLine resource definition for enrol_arlo.
 *
 * @package     enrol_arlo
 * @author      Donald Barrett <donaldb@skills.org.nz>
 * @copyright   2022 onwards, Skills Consulting Group Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link        https://developer.arlo.co/doc/api/2012-02-01/auth/resources/contactemployment
 */

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class OrderLine
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class OrderLine extends AbstractResource {
    /**
     * @var int $OrderLineID An integer value that uniquely identifies this resource within the platform.
     */
    public $OrderLineID;

    /**
     * @var int $LineNumber An integer value identifying the number of this line within the order.
     */
    public $LineNumber;

    /**
     * The base amount for the item, supporting up to 4 decimal places of precision.
     * This amount may be tax inclusive or exclusive depending on the LineAmountsTaxInclusive setting on the parent order.
     * @var float $UnitAmount
     */
    public $UnitAmount;

    /**
     * The quantity multiplier for the line.
     * @var int $Quantity
     */
    public $Quantity;

    /**
     * The total amount of any discounts applied to the line. Omitted if the line has no associated discounts.
     * @var float $DiscountAmount
     */
    public $DiscountAmount;

    /**
     * The amount of tax for the line, rounded to 2 decimal places of precision. Omitted if the line has no associated tax.
     * @var float $TaxAmount
     */
    public $TaxAmount;

    /**
     * The total for the line, inclusive of any discount amount, rounded to 2 decimal places of precision.
     * This amount may be tax inclusive or exclusive depending on the LineAmountsTaxInclusive setting on the parent order.
     * @var float $LineAmount
     */
    public $LineAmount;

    /**
     * @var string $AccountCode The revenue or income account code for the line.
     */
    public $AccountCode;

    /**
     * @var string $TaxAccountCode The tax account code for the line. Omitted if the line has no associated tax.
     */
    public $TaxAccountCode;

    /**
     * Reference to the Registration resource this orderline is for.
     * Included only if there is a registration associated with the line.
     * @var Registration $Registration
     */
    public $Registration;

    /**
     * Reference to the Event this orderline is for.
     * Included only if the line is associated with an event run privately for an organisation.
     * @var Event $Event
     */
    public $Event;

    /** @var Order $Order The parent Order that owns this instance. */
    public $Order;
}
