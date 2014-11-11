@enrol @totara @enrol_totara_facetoface
Feature: Users are forced to get manager approval where required

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
      | student1 | Student | 1 | student1@asd.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |

    And I log in as "admin"
    And I expand "Site administration" node
    And I expand "Plugins" node
    And I expand "Enrolments" node
    And I follow "Manage enrol plugins"
    And I click on "Enable" "link" in the "Face-to-face direct enrolment" "table_row"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name        | Test facetoface name        |
      | Description | Test facetoface description |
      | Approval required | 1                     |
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

    And I log in as "teacher1"
    And I follow "Course 1"
    When I add "Face-to-face direct enrolment" enrolment method with:
      | Custom instance name | Test student enrolment |
    And I log out

  @javascript
  Scenario: Should be unable to enrol using face to face direct without a manager
    Given I log in as "teacher1"
    And I follow "Course 1"
    When I add "Face-to-face direct enrolment" enrolment method with:
      | Custom instance name | Test student enrolment |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I should see "Direct enrolment is not available to you because you are not assigned a manager."

  @javascript
  Scenario: A user with a manager can request access, withdraw request and be granted access
    Given I log in as "admin"
    And I expand "Site administration" node
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
    And I expand "Users" node
    And I expand "Accounts" node
    And I follow "Browse list of users"
    And I follow "Student 1"
    And I expand "Positions" node
    And I follow "Primary position"
    And I press "Choose position"
    And I click on "Position1" "link_or_button"
    And I click on "OK" "link_or_button" in the "div[aria-describedby='position']" "css_element"
    And I press "Choose manager"
    And I click on "Teacher 1" "link_or_button"
    And I click on "OK" "link_or_button" in the "div[aria-describedby='manager']" "css_element"
    And I press "Update position"
    And I log out

    When I log in as "student1"
    And I follow "Course 1"
    And I check "sid"
    And I press "Sign-up"
    Then I should see "Your booking has been completed but requires approval from your manager."
    And I log out

    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "View all sessions"
    And I follow "Attendees"
    And I follow "Approval required"
    Then I should see "Student 1"
    And I log out

    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "It is not possible to sign up for these sessions (manager request already pending)."
    And I follow "Withdraw pending request"
    And I press "Confirm"
    Then I should see "Enrolment options"
    And I log out

    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "View all sessions"
    And I follow "Attendees"
    And I follow "Cancellations"
    Then I should see "Student 1"
    And I log out

    When I log in as "student1"
    And I follow "Course 1"
    And I check "sid"
    And I press "Sign-up"
    Then I should see "Your booking has been completed but requires approval from your manager."
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "View all sessions"
    And I follow "Attendees"
    And I follow "Approval required"
    And I click on "input[value='2']" "css_element" in the "Student 1" table row
    And I press "Update requests"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    Then I should see "Topic 1"
