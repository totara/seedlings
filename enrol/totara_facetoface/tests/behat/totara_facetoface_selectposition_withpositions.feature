@enrol @totara @enrol_totara_facetoface
Feature: Add a face to face
  In order to run a seminar
  As a teacher
  I need to create a face to face activity

  Background:
    Given the following "users" exists:
      | username | firstname | lastname | email               |
      | teacher1 | Terry1    | Teacher1 | teacher1@moodle.com |
      | student1 | Sam1      | Student1 | student1@moodle.com |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exists:
      | user | course | role |
      | teacher1 | C1 | editingteacher |

    And I log in as "admin"
    And I expand "Site administration" node
    And I expand "Plugins" node
    And I expand "Activity modules" node
    And I expand "Face-to-face" node
    And I follow "General Settings"
    And I set the following fields to these values:
      | Select position on signup | 1 |
    And I press "Save changes"

    And I expand "Enrolments" node
    And I follow "Manage enrol plugins"
    And I click on "Enable" "link" in the "Face-to-face direct enrolment" "table_row"

    And I expand "Hierarchies" node
    And I expand "Positions" node
    And I follow "Manage positions"
    And I press "Add new position framework"
    And I set the following fields to these values:
      | Name | PosHierarchy1 |
    And I press "Save changes"
    And I follow "PosHierarchy1"
    And I press "Add new position"
    And I set the following fields to these values:
      | Name | Position1 |
    And I press "Save changes"
    And I press "Return to position framework"
    And I press "Add new position"
    And I set the following fields to these values:
      | Name | Position2 |
    And I press "Save changes"
    And I expand "Users" node
    And I expand "Accounts" node
    And I follow "Browse list of users"
    And I follow "Sam1 Student1"
    And I expand "Positions" node
    And I follow "Primary position"
    And I press "Choose position"
    And I click on "Position1" "link_or_button"
    And I click on "OK" "link_or_button"
    And I press "Update position"
    And I follow "Secondary position"
    And I press "Choose position"
    And I click on "Position2" "link_or_button"
    And I click on "OK" "link_or_button"
    And I press "Update position"
    And I log out

    And I log in as "teacher1"
    And I follow "Course 1"
    And I add "Face-to-face direct enrolment" enrolment method with:
      | Custom instance name | Test student enrolment |
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name        | Test facetoface name        |
      | Description | Test facetoface description |
      | Select position on signup | 1             |
    And I follow "View all sessions"
    And I follow "Add a new session"
    And I set the following fields to these values:
      | datetimeknown | Yes |
      | timestart[0][day] | 1 |
      | timestart[0][month] | 1 |
      | timestart[0][year] | 2020 |
      | timestart[0][hour] | 11 |
      | timestart[0][minute] | 00 |
      | timefinish[0][day] | 1 |
      | timefinish[0][month] | 1 |
      | timefinish[0][year] | 2020 |
      | timefinish[0][hour] | 12 |
      | timefinish[0][minute] | 00 |
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: Add and configure a facetoface activity with a single session and position asked for but not mandated then
  sign in as user with two positions and check attendee list reflects this and the selected position can be updated
    And I log in as "student1"
    And I click on "Courses" "link_or_button" in the "Navigation" "block"
    And I click on ".rb-display-expand" "css_element"
    And I click on "[name$='_sid']" "css_element" in the "1 January 2020" "table_row"
    And I set the following fields to these values:
      | Select a position | Position2 |
    And I press "Enrol"
    Then I should see "Topic 1"
    And I log out

    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "View all sessions"
    And I follow "Attendees"
    And I should see "Position2"

  @javascript
  Scenario: Add and configure a facetoface activity with a single session and position asked for but not mandated then
  sign in as user with two positions and check attendee list reflects this and the selected position can be updated
    And I log in as "student1"
    And I follow "Course 1"
    And I set the following fields to these values:
      | sid               | Yes       |
      | Select a position | Position2 |
    And I press "Sign-up"
    Then I should see "Your booking has been completed."
    And I log out

    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "View all sessions"
    And I follow "Attendees"
    And I should see "Position2"