
Arlo for Moodle - enrolment plugin
==============================

Make administration a breeze. Arlo automates the tasks you once did by hand, or in a dozen different packages. Fully 
automated online registration. Communications and calendar appointments. Invoices and reports. Arlo does the work for 
you, so you can relax, knowing everything is under control. [Take a tour](https://www.arlo.co/tour)

Arlo makes managing training and events easy! 
Get started today with a [30 day risk-free trial.](https://www.arlo.co/try-arlo)


## Installation

1. The plugin is installed as any other Moodle plugin.

2. Unzip source to enrol/arlo folder on your Moodle server.
In your Moodle site (as admin) go to Settings > Site administration > Notifications (you should get a message saying 
the plugin is installed).

## Release Notes

### Version 5.2.0 (2026061100)
* Moodle 5.2 compatibility. Deprecated API calls replaced across the plugin.
* Supported Moodle versions are now 4.5 through 5.2 (4.5, 5.0, 5.1 and 5.2).
* Registration matching now keys on user and enrolment instance, and skips resources that have not
  changed since the last sync. Stops the outcome push / membership poll cycle from repeatedly
  unenrolling and re-enrolling learners.
* Fix Arlo connection status icons on the settings page.
* Contact merge failures where both Arlo contacts resolve to the same Moodle user can now be marked
  as complete from the unsuccessful enrolments page, which retries the enrolment.
* Event and Online activity selectors on the enrolment instance form are autocomplete fields, each
  shown only for the matching type.
* New "Enable Arlo authentication configuration" site setting. "Disable force password change" now
  defaults to enabled.

## Support

https://support.arlo.co/


## Reporting bugs

Your issue should contain a title and a clear description of the issue. You should also include as much relevant 
information as possible and a code sample that demonstrates the issue. The goal of a bug report is to make it easy for 
to replicate the bug and develop a fix.

Please report bugs to:

(Arlo Support) <support@arlo.co>