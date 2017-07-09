<?php

namespace enrol_arlo;

use stdClass;
use enrol_arlo\utility\dml;
use enrol_arlo\Arlo\AuthAPI\Resource\Contact;
use enrol_arlo\Arlo\AuthAPI\Enum\ContactStatus;

require_once($CFG->dirroot . '/user/lib.php');

class user extends \core_user {
    /**
     * @var MATCH_BY_CODE_PRIMARY match by idnumber
     */
    const MATCH_BY_CODE_PRIMARY = 1;
    /**
     * @var MATCH_BY_USER_DETAILS match by firstname, lastname and email
     */
    const MATCH_BY_USER_DETAILS = 2;
    /**
     * @var int MATCH_BY_AUTO match using MATCH_BY_USER_DETAILS then MATCH_BY_CODE_PRIMARY
     * */
    const MATCH_BY_AUTO = 3;
    /**
     * @var int MATCH_BY_DEFAULT default user match method to use.
     */
    const MATCH_BY_DEFAULT = 2;

    /** @var $plugin enrolment plugin instance. */
    private static $plugin;
    /** @var \progress_trace  */
    private $trace;

    private $userrecord;
    private $contactrecord;
    private $contactresource;

    protected $contactfields = array('id', 'plaform', 'userid', 'sourceid', 'sourceguid', 'sourcecreated', 'sourcemodified');

    public function __construct(\progress_trace $trace = null) {
        // Setup trace.
        if (is_null($trace)) {
            $this->trace = new \null_progress_trace();
        } else {
            $this->trace = $trace;
        }
        self::$plugin = enrol_get_plugin('arlo');
    }

    public function create(Contact $contactresource = null) {
        global $DB;

        if (self::exists()) {
            throw new \moodle_exception('Already exists');
        }
        if (!is_null($contactresource)) {
            self::load_contact_resource($contactresource);
        }
        $contactresource = $this->contactresource;
        $plugin = self::$plugin;

        $match = false;
        $matches = self::get_matches();
        if (1 == count($matches)) {
            $match = reset($matches);
        } else if (count($matches) > 1) {
            // Send message.
            $params = array(
                'firstname' => $contactresource->FirstName,
                'lastname' => $contactresource->LastName,
                'email' => $contactresource->Email,
                'idnumber' => $contactresource->CodePrimary,
                'count' => count($matches)
            );
            alert::create('error_duplicateusers', $params, true)->send();
        }

        // Don't touch anything on Match just clone. Else create user.
        if ($match) {
            $trigger            = false;
            $user               = clone($match);
        } else {
            $trigger            = true;
            $user               = static::get_dummy_user_record();
            $user->auth         = $plugin->get_config('authplugin');
            $user->username     = self::generate_username($contactresource->FirstName, $contactresource->LastName);
            $user->firstname    = $contactresource->FirstName;
            $user->lastname     = $contactresource->LastName;
            $user->email        = $contactresource->Email;
            $user->phone1       = $contactresource->PhoneHome;
            $user->phone2       = $contactresource->PhoneMobile;
            $user->idnumber     = $contactresource->CodePrimary;
            $user->id           = user_create_user($user, true, false);
            // Set create password flag.
            set_user_preference('enrol_arlo_createpassword', 1, $user->id);
        }
        // Create Contact association.
        $contact                  = new stdClass();
        $contact->platform        = $plugin->get_config('platform');
        $contact->userid          = $user->id;
        $contact->sourceid        = $contactresource->ContactID;
        $contact->sourceguid      = $contactresource->UniqueIdentifier;
        $contact->sourcecreated   = $contactresource->CreatedDateTime;
        $contact->sourcemodified  = $contactresource->LastModifiedDateTime;
        $contact->modified        = time();
        $contact->id              = $DB->insert_record('enrol_arlo_contact', $contact);

        self::load_user_record($user);
        self::load_contact_record($contact);

        if ($trigger) {
            \core\event\user_created::create_from_userid($user->id)->trigger();
        }
        return $this;
    }

    public function exists() {
        if (empty($this->userrecord) || empty($this->contactrecord)) {
            return false;
        }
        return true;
    }

    public static function generate_username($firstname, $lastname) {
        global $DB;
        $firstname = clean_param($firstname, PARAM_USERNAME);
        $lastname  = clean_param($lastname, PARAM_USERNAME);
        $tries = 0;
        $max = 99;
        $exists = true;
        while ($exists) {
            ++$tries;
            if ($tries > 5) {
                $max = 99999999;
            }
            if ($tries > 100) {
                throw new \moodle_exception('Generate username reached maximum tries.');
            }
            $username = \core_text::strtolower(\core_text::substr($firstname, 0 , 3) .
                \core_text::substr($lastname, 0 , 3) . rand(0, $max));
            $exists = $DB->get_record('user', array('username' => $username));
        }
        return $username;
    }

    public static function get_contact_fields() {
        $contactfields = array(
            'id', 'platform', 'userid', 'sourceid', 'sourceguid', 'sourcecreated', 'sourcemodified'
        );
        return $contactfields;
    }

    protected function get_matches() {
        // Match preference.
        $matchuseraccountsby = get_config('enrol_arlo', 'matchuseraccountsby');
        if (empty($matchuseraccountsby)) {
            throw new \moodle_exception('Empty required config var');
        }
        $matches = array();
        // Match by user details.
        if ($matchuseraccountsby == self::MATCH_BY_USER_DETAILS) {
            $matches = self::match_against_arlo_user_details(
                $this->contactresource->FirstName,
                $this->contactresource->LastName,
                $this->contactresource->Email
            );
        }
        // Match by code primary.
        if ($matchuseraccountsby == self::MATCH_BY_CODE_PRIMARY) {
            if (!empty($this->contactresource->CodePrimary)) {
                $matches = self::match_against_arlo_code_primary($this->contactresource->CodePrimary);
            }
        }
        // Auto matching.
        if ($matchuseraccountsby == self::MATCH_BY_AUTO) {
            $matches = self::match_against_arlo_user_details(
                $this->contactresource->FirstName,
                $this->contactresource->LastName,
                $this->contactresource->Email
            );
            if (empty($matches)) {
                if (!empty($this->contactresource->CodePrimary)) {
                    $matches = self::match_against_arlo_code_primary($this->contactresource->CodePrimary);
                }
            }
        }
        return $matches;
    }

    public static function get_user_fields() {
        $userfields = array();
        foreach (get_object_vars(static::get_dummy_user_record()) as $key => $value) {
            $userfields[] = $key;
        }
        return $userfields;
    }

    public function get_id() {
        if (isset($this->userrecord->id)) {
            return $this->userrecord->id;
        }
        return 0;
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
                 WHERE u.deleted = 0
                   AND ac.platform = :platform
                   AND ac.sourceguid = :sourceguid";
        $platform = get_config('enrol_arlo', 'platform');
        $conditions = array('platform' => $platform, 'sourceguid' => $guid);
        $record = $DB->get_record_sql($sql, $conditions);
        $user = new user();
        if ($record) {
            $unaliasedrecord = dml::unalias($record);
            $userrecord = (object) $unaliasedrecord['user'];
            $contactrecord = (object) $unaliasedrecord['contact'];
            $user->load_user_record($userrecord);
            $user->load_contact_record($contactrecord);
        }
        return $user;
    }

    protected function check_record_fields($record, $fields) {}

    private function load_user_record($user) {
        $this->userrecord = (object) $user;
    }

    private function load_contact_record($contact) {
        $this->contactrecord = (object) $contact;
    }

    public function load_contact_resource(Contact $contact) {
        return $this->contactresource = $contact;
    }

    public function match_against_arlo_code_primary($codeprimary) {
        global $DB;

        $conditions = array('idnumber' => $codeprimary);
        $records = $DB->get_records('user', $conditions);
        return $records;
    }

    public function match_against_arlo_user_details($firstname, $lastname, $email) {
        global $DB;

        $firstname  = trim($firstname);
        $lastname   = trim($lastname);
        $email      = trim($email);

        $conditions = array('firstname' => $firstname, 'lastname' => $lastname, 'email' => $email);
        $records = $DB->get_records('user', $conditions);
        return $records;
    }
}