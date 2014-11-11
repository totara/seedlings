@mod @mod_facetoface @totara
Feature: Manager approval
  In order to control seminar attendance
  As a manager
  I need to authorise seminar signups

  Background:
    Given the following "users" exists:
      | username | firstname | lastname | email               |
      | teacher1 | Terry1    | Teacher1 | teacher1@moodle.com |
      | student1 | Sam1      | Student1 | student1@moodle.com |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exists:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name                               | Test facetoface name        |
      | Description                        | Test facetoface description |
      | Approval required                  | 1                           |
      | Self Approval Terms and Conditions | Test terms and conditions   |
    And I follow "View all sessions"
    And I follow "Add a new session"
    And I set the following fields to these values:
      | datetimeknown         | Yes  |
      | timestart[0][day]     | 1    |
      | timestart[0][month]   | 1    |
      | timestart[0][year]    | 2020 |
      | timestart[0][hour]    | 11   |
      | timestart[0][minute]  | 00   |
      | timefinish[0][day]    | 1    |
      | timefinish[0][month]  | 1    |
      | timefinish[0][year]   | 2020 |
      | timefinish[0][hour]   | 12   |
      | timefinish[0][minute] | 00   |
      | capacity              | 1    |
      | Self Approval         | 1    |
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: Student signs up and self approves
    When I log in as "student1"
    And I follow "Course 1"
    And I should see "Sign-up"
    And I follow "Sign-up"
    And I should see "This session requires manager approval to book."
    And I press "Sign-up"
    And I should see "Required"
    And I follow "Self Approval Terms and Conditions"
    And I should see "Test terms and conditions"
    And I press "Close"
    And I set the following fields to these values:
      | id_selfapprovaltc | 1 |
    And I press "Sign-up"
    And I should see "Your booking has been completed."