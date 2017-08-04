<?php

namespace enrol_arlo;

use stdClass;
use enrol_arlo\utility\dml;
use enrol_arlo\Arlo\AuthAPI\Resource\Contact;
use enrol_arlo\Arlo\AuthAPI\Enum\ContactStatus;

require_once($CFG->dirroot . '/user/lib.php');

class user extends \core_user {
    /** @var MATCH_BY_CODE_PRIMARY match by idnumber */
    const MATCH_BY_CODE_PRIMARY = 1;

    /** @var MATCH_BY_USER_DETAILS match by firstname, lastname and email */
    const MATCH_BY_USER_DETAILS = 2;

    /** @var int MATCH_BY_AUTO match using MATCH_BY_USER_DETAILS then MATCH_BY_CODE_PRIMARY */
    const MATCH_BY_AUTO = 3;

    /** @var int MATCH_BY_DEFAULT default user match method to use. */
    const MATCH_BY_DEFAULT = 2;

    /** @var $plugin enrolment plugin instance. */
    private static $plugin;

    /** @var \progress_trace  */
    private static $trace;

    /** @var $userrecord stdClass */
    private $userrecord;

    /** @var $contactrecord stdClass */
    private $contactrecord;
    
    /** @var $contactresource Contact */
    private $contactresource;

    public function __construct(\progress_trace $trace = null) {
        if (is_null(static::$trace)) {
            static::$trace = new \null_progress_trace();
        } else {
            static::$trace = $trace;
        }
        self::$plugin = new \enrol_arlo_plugin();
    }

    /**
     * @return Contact
     */
    public function get_contact_resource() {
        return $this->contactresource;
    }

    /**
     * Helper function to return dummy noreply user record.
     *
     * @return stdClass
     */
    protected static function get_dummy_user_record() {
        global $CFG;

        $noreplyaddressdefault = 'noreply@' . get_host_from_url($CFG->wwwroot);
        $noreplyaddress = empty($CFG->noreplyaddress) ? $noreplyaddressdefault : $CFG->noreplyaddress;

        $dummyuser = new stdClass();
        $dummyuser->id = self::NOREPLY_USER;
        $dummyuser->email = $noreplyaddress;
        $dummyuser->firstname = get_string('noreplyname');
        $dummyuser->username = 'noreply';
        $dummyuser->lastname = '';
        $dummyuser->confirmed = 1;
        $dummyuser->suspended = 0;
        $dummyuser->deleted = 0;
        $dummyuser->picture = 0;
        $dummyuser->auth = 'manual';
        $dummyuser->firstnamephonetic = '';
        $dummyuser->lastnamephonetic = '';
        $dummyuser->middlename = '';
        $dummyuser->alternatename = '';
        $dummyuser->imagealt = '';
        return $dummyuser;
    }

    /**
     * @param Contact $contactresource
     * @return bool
     */
    public function load_by_resource(Contact $contactresource) {
        global $DB;

        self::load_contact_resource($contactresource);
        // Alias up fields.
        $aliaseduserfields      = dml::alias(static::get_user_fields(), 'u', 'user_');
        $aliasedcontactfields   = dml::alias(static::get_contact_fields(), 'ac', 'contact_');
        // Add user and contact fields together to be used in SQL.
        $fields = "$aliaseduserfields, $aliasedcontactfields ";
        $sql = "SELECT $fields
                  FROM {enrol_arlo_contact} ac 
                  JOIN {user} u ON  u.id = ac.userid
                 WHERE u.deleted = 0
                   AND ac.platform = :platform
                   AND ac.sourceguid = :sourceguid";
        // Conditions required: platform and sourceguid.
        $platform   = self::$plugin->get_config('platform');
        $guid       = $this->contactresource->UniqueIdentifier;
        $conditions = array('platform' => $platform, 'sourceguid' => $guid);
        $record     = $DB->get_record_sql($sql, $conditions);
        if ($record) {
            $unaliasedrecord    = dml::unalias($record);
            $userrecord         = (object) $unaliasedrecord['user'];
            $contactrecord      = (object) $unaliasedrecord['contact'];
            self::load_user_record($userrecord);
            self::load_contact_record($contactrecord);
            return true;
        }
        return false;
    }

    /**
     * Create/Match a user. Attempts to match Arlo contact against a Moodle users.
     * It will join Arlo contact to a Moodle account either existing or newly created.
     *
     * @param Contact|null $contactresource
     * @return $this
     * @throws \moodle_exception
     */
    public function create(Contact $contactresource = null) {
        global $DB, $CFG;

        if (self::exists()) {
            throw new \moodle_exception('Already exists');
        }
        if (!is_null($contactresource)) {
            self::load_contact_resource($contactresource);
        }
        // See if can match Arlo contact against a Moodle user.
        $match = false;
        $matches = self::get_matches();
        if (1 == count($matches)) {
            $match = reset($matches);
            self::trace('Perfect match.');
        } else if (count($matches) > 1) {
            // Send message.
            $params = array(
                'firstname' => self::get_contact_resource()->FirstName,
                'lastname'  => self::get_contact_resource()->LastName,
                'email'     => self::get_contact_resource()->Email,
                'idnumber'  => self::get_contact_resource()->CodePrimary,
                'count'     => count($matches)
            );
            alert::create('error_duplicateusers', $params)->send();
        }
        // Don't touch anything on Match just clone. Else create user.
        if ($match) {
            $trigger            = false;
            $user               = clone($match);
        } else {
            $trigger            = true;
            $user               = self::get_dummy_user_record();
            $user->auth         = self::$plugin->get_config('authplugin', 'manual');
            $contactfirstname   = self::get_contact_resource()->FirstName;
            $contactlastname    = self::get_contact_resource()->LastName;
            $contactemail       = self::get_contact_resource()->Email;
            $user->username     = self::generate_username($contactfirstname, $contactlastname, $contactemail);
            $user->firstname    = (string) self::get_contact_resource()->FirstName;
            $user->lastname     = (string) self::get_contact_resource()->LastName;
            $user->email        = (string) self::get_contact_resource()->Email;
            $user->phone1       = (string) self::get_contact_resource()->PhoneHome;
            $user->phone2       = (string) self::get_contact_resource()->PhoneMobile;
            $user->idnumber     = (string) self::get_contact_resource()->CodePrimary;
            $user->mnethostid   = $CFG->mnet_localhost_id;
            $user->id           = user_create_user($user, true, false);
            // Set create password flag.
            set_user_preference('enrol_arlo_createpassword', 1, $user->id);
        }
        // Create Contact association.
        $contact                  = new stdClass();
        $contact->platform        = self::$plugin->get_config('platform');
        $contact->userid          = $user->id;
        $contact->sourceid        = (int)    self::get_contact_resource()->ContactID;
        $contact->sourceguid      = (string) self::get_contact_resource()->UniqueIdentifier;
        $contact->sourcecreated   = (string) self::get_contact_resource()->CreatedDateTime;
        $contact->sourcemodified  = (string) self::get_contact_resource()->LastModifiedDateTime;
        $contact->modified        = time();
        $contact->id              = $DB->insert_record('enrol_arlo_contact', $contact);
        // Load user record.
        self::load_user_record($user);
        // Load contact record.
        self::load_contact_record($contact);
        // Trigger event for newly created users.
        if ($trigger) {
            \core\event\user_created::create_from_userid($user->id)->trigger();
        }
        return $this;
    }

    /**
     * Exists if have a user and associated contact record.
     *
     * @return bool
     */
    public function exists() {
        if (!empty($this->userrecord) && !empty($this->contactrecord)) {
            if ($this->userrecord->id == $this->contactrecord->userid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Scheme for generating usernames.
     *
     * Order:
     *
     *  1. first 3 letters of firstname + first 3 letters of lastname + random 3 digit number
     *  2. email address before @ symbol
     *  3. email address before @ symbol + random 3 digit number
     *  4. full email address
     *  5. full email address + random 3 digit number
     *
     * @param $firstname
     * @param $lastname
     * @param $email
     * @return mixed|string
     * @throws \moodle_exception
     */
    public static function generate_username($firstname, $lastname, $email) {
        global $DB;

        // Clean all variables as USERNAMES since going to be used in contructing username.
        $firstname  = clean_param($firstname, PARAM_USERNAME);
        $lastname   = clean_param($lastname, PARAM_USERNAME);
        $email      = clean_param($email, PARAM_USERNAME);
        $local      = strstr($email, '@', true);

        $tries = 0;
        $exists = true;
        while ($exists) {
            ++$tries;
            switch($tries) {
                case 1;
                    $username = \core_text::strtolower(\core_text::substr($firstname, 0 , 3) .
                        \core_text::substr($lastname, 0 , 3) . rand(0, 3));
                    break;
                case 2:
                    $username = $local;
                    break;
                case 3:
                    $username = $local + rand(0, 3);
                    break;
                case 4:
                    $username = $email;
                case 5:
                    $username = $email + rand(0, 3);
                default:
                    throw new \moodle_exception('Generate username could not failed');
            }
            $exists = $DB->get_record('user', array('username' => $username));
        }
        return $username;
    }

    /**
     * @return array
     */
    public static function get_contact_fields() {
        $contactfields = array(
            'id', 'platform', 'userid', 'sourceid', 'sourceguid', 'sourcecreated', 'sourcemodified'
        );
        return $contactfields;
    }

    /**
     * Return formatted fullname.
     *
     * @return string
     */
    public function get_user_fullname(){
        if (self::exists()){
            return fullname($this->userrecord);
        }
        return '';
    }

    /**
     * @return array
     */
    public static function get_user_fields() {
        $userfields = array();
        foreach (get_object_vars(static::get_dummy_user_record()) as $key => $value) {
            $userfields[] = $key;
        }
        return $userfields;
    }

    /**
     * Get user record id.
     *
     * @return int
     */
    public function get_user_id() {
        return $this->userrecord->id;
    }

    /**
     * @param $user
     */
    private function load_user_record($user) {
        $this->userrecord = (object) $user;
    }

    /**
     * @param $contact
     */
    private function load_contact_record($contact) {
        $this->contactrecord = (object) $contact;
    }

    /**
     * @param Contact $contact
     * @return Contact
     */
    private function load_contact_resource(Contact $contact) {
        return $this->contactresource = $contact;
    }

    /**
     * Apply matching schemes based on configuration.
     *
     * @return array
     * @throws \moodle_exception
     */
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

    /**
     * Returns any matches agains idnumber.
     *
     * @param $codeprimary
     * @return array
     */
    protected function match_against_arlo_code_primary($codeprimary) {
        global $DB;

        $conditions = array('idnumber' => $codeprimary);
        $records = $DB->get_records('user', $conditions);
        return $records;
    }

    /**
     * Returns any matches in Moodle against firstname, lastname and email.
     *
     * @param $firstname
     * @param $lastname
     * @param $email
     * @return array
     */
    protected function match_against_arlo_user_details($firstname, $lastname, $email) {
        global $DB;

        $firstname  = trim($firstname);
        $lastname   = trim($lastname);
        $email      = trim($email);

        $conditions = array('firstname' => $firstname, 'lastname' => $lastname, 'email' => $email);
        $records = $DB->get_records('user', $conditions);
        return $records;
    }

    /**
     * Update Moodle user and contact records based on change in Arlo contact record.
     *
     *  - Updates user record firstname, lastname and email.
     *  - Update contact record sourcecreated and sourcemodified.
     *
     * @param Contact|null $contactresource
     * @return bool
     * @throws \coding_exception
     */
    public function update(Contact $contactresource = null) {
        global $DB;
        if (!self::exists()) {
            throw new \coding_exception('Records do not exist');
        }
        if (!is_null($contactresource)) {
            self::load_contact_resource($contactresource);
        }
        $contactrecord = $this->contactrecord;
        $contactrecord->sourcecreated = self::get_contact_resource()->CreatedDateTime;
        $contactrecord->sourcemodified = self::get_contact_resource()->LastModifiedDateTime;
        // Update timing information on enrol contact record.
        $DB->update_record('enrol_arlo_contact', $contactrecord);
        $userrecord = $this->userrecord;
        $userrecord->firstname = self::get_contact_resource()->FirstName;
        $userrecord->lastname = self::get_contact_resource()->LastName;
        $userrecord->email = self::get_contact_resource()->Email;
        // Update name details for user record.
        $DB->update_record('user', $userrecord);
        \core\event\user_updated::create_from_userid($userrecord->id)->trigger();
        return true;
    }

    /**
     * Output a progress message.
     *
     * @param $message the message to output.
     * @param int $depth indent depth for this message.
     */
    private function trace($message, $depth = 0) {
        self::$trace->output($message, $depth);
    }
}
