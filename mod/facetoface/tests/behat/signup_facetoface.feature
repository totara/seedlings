@mod @mod_facetoface @totara
Feature: Sign up to a face to face
  In order to attend a seminar
  As a student
  I need to sign up to a face to face session

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
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name        | Test facetoface name        |
      | Description | Test facetoface description |
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
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: Sign up to a session and unable to sign up to a full session
    When I log in as "student1"
    And I follow "Course 1"
    And I should see "Sign-up"
    And I follow "Sign-up"
    And I press "Sign-up"
    And I should see "Your booking has been completed."
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I should not see "Sign-up"