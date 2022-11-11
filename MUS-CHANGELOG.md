### Nov 10 2022 - ARLO-7
Changed the enrolment task.
#### Old Behavior
The task would query the Arlo remote API for each enrollment instance checking for new registrations on each one.
#### New Behavior
It now queries the remote once to retrieve all recent registrations for all events since the last execution.
#### Backend testing instructions
1. Disable the CRON
2. Use admin/cli/scheduled_task to execute the \enrol_arlo\task\enrolments task manually
3. Confirm it comes back without any additional output.
4. Create a new registration for a Moodle course in Arlo.
5. Run the command again and confirm it executes quickly and only outputs information related to the new registration.
6. Re-enable the CRON
#### Normal testing instructions
1. Create a new registration for a Moodle course in Arlo.
2. Wait until next CRON completes and confirm registration was processed successfully into the Moodle course.


### May 13 2022
Changed the enrolment task.
#### Old Behavior
The task would only execute the first 1000 valid scheduled jobs. This meant that new jobs could only be executed after
previous jobs expired.
#### New Behavior
The task now keeps track of the last executed scheduled job in the plugin config table and executes the next X jobs or
resets to the beginning if none are found. Changed the number of jobs to 50 to allow it to run every minute cleanly.
#### Testing instructions
Difficult to test in a dev environment due to the integration. In a production environment, determine how many rows are
in the {enrol_arlo_scheduledjob} table with 0 in the timelastrequest column. After updating wait rows/50 minutes. Verify
there are no (or only very recent) rows with 0 in the timelastrequest column. It is possible jobs expired
(timenorequestsafter and timerequestsafterextension column) or were disabled (disabled column) while the plugin wasn't
functioning and so may never execute.
