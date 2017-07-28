<?php

namespace enrol_arlo\Arlo\AuthAPI;

class Filter {
    private $resourceField;
    private $operator;
    private $value;
    private $exportAsOffset;

    /**
     * Create and return a Filter class that can be method
     * chained.
     *
     * @return Filter
     */
    public static function create() {
        return new Filter();
    }

    /**
     * Get ResourceField.
     *
     * @return mixed
     */
    public function getResourceField() {
        return $this->resourceField;
    }

    /**
     * Set ResourceField to compare.
     *
     * Example:
     *          Resgistration/LastModifiedDateTime
     *
     * @param $resourceField
     * @return $this
     */
    public function setResourceField($resourceField) {
        $this->resourceField = $resourceField;
        return $this;
    }

    /**
     * @param $operator
     * @return $this
     * @throws \Exception
     */
    public function setOperator($operator) {
        $operator = strtolower($operator);
        switch ($operator) {
            case 'eq':
            case 'ne':
            case 'gt':
            case 'ge':
            case 'le':
            case 'lt':
                break;
            default:
                throw new \Exception('Operator ' . $operator . ' not supported');
        }
        $this->operator = $operator;
        return $this;
    }

    /**
     * Set a DateTime as value.
     *
     * @param \DateTime $date
     * @param bool $exportAsOffset
     * @return $this
     */
    public function setDateValue($date, $exportAsOffset = false) {
        $this->datevalue = $date;
        $this->exportAsOffset = $exportAsOffset;
        return $this;
    }

    /**
     * Set simple value.
     *
     * @param $value
     * @return $this
     */
    public function setValue($value) {
        $this->value = $value;
        return $this;
    }

    /**
     * Export as complete comparsion string.
     *
     * Example:
     *          Contact/CreatedDateTime gt datetime('2000-01-01T00:00:0000')
     *
     * @return string
     * @throws \Exception
     */
    public function export() {
        $export = '';
        if (is_null($this->resourceField)) {
            throw new \Exception('Filter missing resourceField');
        }
        if (is_null($this->operator)) {
            throw new \Exception('Filter missing operator');
        }
        if (is_null($this->value) && is_null($this->datevalue)) {
            throw new \Exception('Filter missing value');
        }
        $export .= $this->resourceField . ' ';
        $export .= $this->operator . ' ';
        if (isset($this->datevalue)) {
            if ($this->exportAsOffset) {
                $export .= "datetimeoffset('". $this->datevalue.  "')";
            } else {
                $export .= "datetime('". $this->datevalue.  "')";
            }

        } else {
            $export .= $this->value;
        }
        return $export;
    }
}