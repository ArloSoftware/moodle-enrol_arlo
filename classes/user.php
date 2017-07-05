<?php

namespace enrol_arlo;

use stdClass;
use enrol_arlo\utility\dml;
use enrol_arlo\Arlo\AuthAPI\Resource\Contact;

class user extends \core_user {
    private $recorduser;
    private $recordcontact;
    private $contactresource;

    protected $contactfields = array('id', 'plaform', 'userid', 'sourceid', 'sourceguid', 'sourcecreated', 'sourcemodified');

    public static function get_user_fields() {
        $userfields = array();
        foreach (get_object_vars(static::get_dummy_user_record()) as $key => $value) {
            $userfields[] = $key;
        }
        return $userfields;
    }

    public static function get_contact_fields() {
        $contactfields = array(
            'id', 'platform', 'userid', 'sourceid', 'sourceguid', 'sourcecreated', 'sourcemodified'
        );
        return $contactfields;
    }

    public function create() {

    }

    public static function get_by_guid($guid) {
        global $DB;
        if (empty($guid) || !is_string($guid)) {
            throw new \moodle_exception('GUID must be non empty string');
        }
        // Alias up fields.
        $aliaseduserfields = dml::alias(static::get_user_fields(), 'u', 'user_');
        $aliasedcontactfields = dml::alias(static::get_contact_fields(), 'ac', 'contact_');
        $fields = "$aliaseduserfields, $aliasedcontactfields ";
        $sql = "SELECT $fields
                  FROM {enrol_arlo_contact} ac 
                  JOIN {user} u ON  u.id = ac.userid
                 WHERE ac.platform = :platform 
                   AND ac.sourceguid = :sourceguid";
        $platform = get_config('enrol_arlo', 'platform');
        $conditions = array('platform' => $platform, 'sourceguid' => $guid);
        $record = $DB->get_record_sql($sql, $conditions);
        $user = new user();
        if ($record) {
            die('die');
        }
        return $user;
    }

    public function load_user_record(stdClass $user) {
    }
    public function load_contact_record(stdClass $contact) {
    }
    public function load_resource(Contact $contact) {
        $this->contactresource = $contact;
    }

    protected function can_generate_username() {}
    public static function generate_username() {}
    public function match_against_arlo_contact(){}

    public function isempty() {
        return true;
    }
}