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
 * Order resource definition for enrol_arlo.
 *
 * @package     enrol_arlo
 * @author      Donald Barrett <donaldb@skills.org.nz>
 * @copyright   2022 onwards, Skills Consulting Group Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link        https://developer.arlo.co/doc/api/2012-02-01/auth/resources/contactemployment
 */

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Order
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Order extends AbstractResource {
    /** @var int $OrderID An integer value that uniquely identifies this resource within the platform. */
    public $OrderID;

    /** @var string $UniqueIdentifier A GUID value that uniquely identifies this resource across any platform. */
    public $UniqueIdentifier;

    /** @var string $Code 	A string representing a code of the order. */
    public $Code;

    /** @var string $ReferenceCode 	A custom reference code (such as a purchase number), up to 256 characters. */
    public $ReferenceCode;

    /** @var string $Date The date of the order. */
    public $Date;

    /** @var string $DueDate The due date of any payments for the order. */
    public $DueDate;

    /** @var bool $LineAmountsTaxInclusive Determines whether line amounts for the order are tax inclusive. */
    public $LineAmountsTaxInclusive;

    /**
     * The expected payment method for the order, usually set when the order was created.
     * This should be used for informational purposes only, and may differ to the actual payment method used for the order.
     * @var string $ExpectedPaymentMethod
     */
    public $ExpectedPaymentMethod;

    /** @var float $SubTotal The total of the order, excluding any tax amount. */
    public $SubTotal;

    /** @var float $TotalTax The total amount of tax for all lines on the order. */
    public $TotalTax;

    /** @var float $Total The total of the order, including tax. */
    public $Total;

    /** @var string $CurrencyCode Three-letter alpha code of the currency the order has been created in. */
    public $CurrencyCode;

    /**
     * A UTC DateTime value indicating when the order was approved. Omitted if the order has not been approved.
     * @var string $ApprovedDateTime
     */
    public $ApprovedDateTime;

    /**
     * A UTC DateTime value indicating when an invoice for the order was recorded as sent. Omitted if no invoice has been sent.
     * @var string $MarkedAsInvoiceSentDateTime
     */
    public $MarkedAsInvoiceSentDateTime;

    /**
     * A UTC DateTime value indicating when the order was marked as fully paid. Omitted if the order has not been fully paid.
     * @var string $MarkedAsPaidDateTime
     */
    public $MarkedAsPaidDateTime;

    /**
     * A UTC DateTime value indicating when the order was cancelled. Omitted if the order has not been cancelled.
     * @var string $CancelledDateTime
     */
    public $CancelledDateTime;

    /**
     * An OrderStatus value representing the current state of this order,
     * such as awaiting approval, expired, completed or cancelled.
     * @var string $Status
     */
    public $Status;

    /** @var string $CreatedDateTime A UTC DateTime value indicating when this resource was created. This value is read-only. */
    public $CreatedDateTime;

    /**
     * A UTC DateTime value indicating when this resource was last modified. This value is read-only.
     * @var string $LastModifiedDateTime
     */
    public $LastModifiedDateTime;

    /**
     * Reference to a Contact resource that represents the billed individual for this order.
     * NOTE: Orders billed to organisations will still have a billing contact.
     * @var Contact $BillToContact
     */
    public $BillToContact;

    /**
     * Reference to a Organisation resource that represents the billed organisation for this order.
     * Omitted if the order is to be billed to a private individual.
     * @var Organisation $BillToOrganisation
     */
    public $BillToOrganisation;

    /** @var OrderLines $Lines Reference to an OrderLines resource that contains a collection of lines related to this order. */
    public $Lines;
}
