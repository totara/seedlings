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
      | student2 | Sam2      | Student2 | student2@moodle.com |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exists:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |

  @javascript
  Scenario: Student cannot declare interest where not enabled
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name              | Test facetoface name        |
      | Description       | Test facetoface description |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I should not see "Declare interest"

  @javascript
  Scenario: Student can declare and withdraw interest where enabled
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name                                                               | Test declareinterestfullybooked |
      | Description                                                        | Test facetoface description     |
      | Approval required                                                  | 1                               |
      | Enable "Declare Interest" option                                   | 1                               |
    And I click on "View all sessions" "link" in the "declareinterestfullybooked" activity
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
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I should see "Declare interest"
    And I follow "Declare interest"
    And I set the following fields to these values:
      | Reason for interest: | Test reason |
    And I press "Confirm"
    And I should see "Withdraw interest"
    And I follow "Withdraw interest"
    And I press "Confirm"
    And I should see "Declare interest"

  @javascript
  Scenario: Student cannot declare interest until all sessions are fully booked if setting enabled.
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name                                                               | Test declareinterestfullybooked |
      | Description                                                        | Test facetoface description     |
      | Enable "Declare Interest" option                                   | 1                               |
      | Show "Declare Interest" link only if all sessions are closed       | 1                               |
    And I click on "View all sessions" "link" in the "declareinterestfullybooked" activity
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
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I should not see "Declare interest"
    And I follow "Sign-up"
    And I press "Sign-up"
    And I should see "Your booking has been completed."
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I should see "Declare interest"

  @javascript
  Scenario: Student cannot declare interest if overbooking is enabled.
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name                                                               | Test declareinterestfullybooked |
      | Description                                                        | Test facetoface description     |
      | Enable "Declare Interest" option                                   | 1                               |
      | Show "Declare Interest" link only if all sessions are closed       | 1                               |
    And I click on "View all sessions" "link" in the "declareinterestfullybooked" activity
    And I follow "Add a new session"
    And I set the following fields to these values:
      | Allow overbooking     | Yes  |
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
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I should not see "Declare interest"
    And I follow "Sign-up"
    And I press "Sign-up"
    And I should see "Your booking has been completed."
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I should not see "Declare interest"

  @javascript
  Scenario: Staff can view who has expressed interest
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name                                                               | Test f2f 1                      |
      | Description                                                        | Test facetoface description     |
      | Enable "Declare Interest" option                                   | 1                               |
    And I click on "View all sessions" "link" in the "Test f2f 1" activity
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
    And I press "Save changes"
    And I follow "Course 1"
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name                                                               | Test f2f 2                      |
      | Description                                                        | Test facetoface description     |
      | Enable "Declare Interest" option                                   | 1                               |
    And I click on "View all sessions" "link" in the "Test f2f 2" activity
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
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I click on "Declare interest" "link" in the "Test f2f 1" activity
    And I set the following fields to these values:
      | Reason for interest: | Test reason 1 |
    And I press "Confirm"
    And I click on "Declare interest" "link" in the "Test f2f 2" activity
    And I set the following fields to these values:
      | Reason for interest: | Test reason 2 |
    And I press "Confirm"
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I click on "Declare interest" "link" in the "Test f2f 1" activity
    And I set the following fields to these values:
      | Reason for interest: | Test reason 3 |
    And I press "Confirm"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test f2f 1"
    And I follow "Declared interest report"
    And I should see "Test reason 1"
    And I should not see "Test reason 2"
    And I should see "Test reason 3"
    And I follow "Course 1"
    And I follow "Test f2f 2"
    And I follow "Declared interest report"
    And I should not see "Test reason 1"
    And I should see "Test reason 2"
    And I should not see "Test reason 3"