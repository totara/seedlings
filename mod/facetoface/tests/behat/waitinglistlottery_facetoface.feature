@mod @mod_facetoface @totara
Feature: Play waiting list lottery
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

    And I log in as "admin"
    And I expand "Site administration" node
    And I expand "Plugins" node
    And I expand "Activity modules" node
    And I expand "Face-to-face" node
    And I follow "General Settings"
    And I set the following fields to these values:
      | Enable everyone on waiting list option | Yes  |
      | Enable waitlist lottery                 | Yes  |
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: The second student to sign up to the session should go on waiting list

    Given I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name              | Test facetoface name        |
      | Description       | Test facetoface description |
    And I follow "View all sessions"
    And I follow "Add a new session"
    And I set the following fields to these values:
      | datetimeknown                  | Yes  |
      | timestart[0][day]              | 1    |
      | timestart[0][month]            | 1    |
      | timestart[0][year]             | 2020 |
      | timestart[0][hour]             | 11   |
      | timestart[0][minute]           | 00   |
      | timefinish[0][day]             | 1    |
      | timefinish[0][month]           | 1    |
      | timefinish[0][year]            | 2020 |
      | timefinish[0][hour]            | 12   |
      | timefinish[0][minute]          | 00   |
      | capacity                       | 2    |
      | Allow overbooking              | 1    |
      | Send all bookings to the waiting list | 1    |
    And I press "Save changes"
    And I log out

    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Sign-up"
    And I should see "You will be added to the waiting list for this session"
    And I press "Sign-up"
    And I should see "Your booking has been completed."
    And I log out

    When I log in as "student2"
    And I follow "Course 1"
    And I follow "Sign-up"
    And I should see "You will be added to the waiting list for this session"
    And I press "Sign-up"
    And I should see "Your booking has been completed."
    And I log out

    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test facetoface name"
    And I follow "Attendees"
    And I follow "Wait-list"
    Then I should see "Sam1 Student1"
    Then I should see "Sam2 Student2"
    And I set the following fields to these values:
      | menuf2f-actions | Play Lottery |
    And I should see "Please select one or more users"
    And I click on "Close" "link_or_button"
    And I click on "All" "link"
    And I set the following fields to these values:
      | menuf2f-actions | Play Lottery |
    And I click on "OK" "link_or_button"
    And I should see "Successfully updated attendance"
    Then I should not see "Sam1 Student1"
    Then I should not see "Sam2 Student2"
    And I follow "Attendees"
    Then I should see "Sam1 Student1"
    Then I should see "Sam2 Student2"
