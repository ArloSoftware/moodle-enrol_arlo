
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

## Upgrading from version 3.1.1 to version 3.1.9 and higher

Version 3.1.9 and higher of the enrolment plugin is no longer dependant on the local plugin.

1. Perform a full site backup. Information on how to perform a site backup can be found at [MoodleDocs.](https://docs.moodle.org/31/en/Site_backup)

2. Unzip source to enrol/arlo folder on your Moodle server.
In your Moodle site (as admin) go to Settings > Site administration > Notifications (you should get a message saying 
the plugin is upgraded).

3. Go to Administration > Site Administration > Plugins > Local plugins > Manage local plugins.
Then click the Uninstall link opposite the Arlo plugin.

4. Use a file manager to remove/delete the actual plugin directory as instructed, otherwise Moodle will reinstall it next 
time you access the site administration.

## Release Notes
### Version 3.9.2 (2020073112)
* Bugfig issue #154. Incorrect scope error prevented enrolment task from completing in some instances resulting in enrolments 
  not being added.

## Support

https://support.arlo.co/


## Reporting bugs

Your issue should contain a title and a clear description of the issue. You should also include as much relevant 
information as possible and a code sample that demonstrates the issue. The goal of a bug report is to make it easy for 
to replicate the bug and develop a fix.

Please report bugs to:

(Arlo Support) <support@arlo.co>