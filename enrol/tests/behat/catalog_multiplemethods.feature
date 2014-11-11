@enrol
Feature: Users can auto-enrol themself in courses where self enrolment is allowed
  In order to participate in courses
  As a user
  I need to auto enrol me in courses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
      | student1 | Student | 1 | student1@asd.com |
      | student2 | Student | 2 | student2@asd.com |
      | student3 | Student | 3 | student3@asd.com |
    And the following "courses" exists:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exists:
      | user | course | role |
      | teacher1 | C1 | editingteacher |

    Given     I log in as "admin"
    And I expand "Site administration" node
    And I expand "Plugins" node
    And I expand "Enrolments" node
    And I follow "Manage enrol plugins"
    And I click on "Enable" "link" in the "Face-to-face direct enrolment" "table_row"
    And I log out

    Given I log in as "teacher1"

    Given I follow "Course 1"
    When I add "Self enrolment" enrolment method with:
      | Enrolment key | moodle_rules |
      | Use group enrolment keys | Yes |
    And I follow "Groups"
    And I press "Create group"
    And I fill the moodle form with:
      | Group name | Group 1 |
      | Enrolment key | Test-groupenrolkey1 |
    And I press "Save changes"

    Given I follow "Course 1"
    And I turn editing mode on
    And I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Description | Test forum description |
    And I click on "Edit settings" "link" in the "Administration" "block"
    And I fill the moodle form with:
      | Allow guest access | Yes |
      | Password | moodle_rules |
    And I press "Save changes"

    Given I follow "Course 1"
    And I add a "Face-to-face" to section "1" and I fill the form with:
      | Name        | Test facetoface name 2        |
      | Description | Test facetoface description 2 |
      | Approval required | 0                     |
    And I follow "Test facetoface name 2"
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
    And I follow "Course 1"
    And I add "Face-to-face direct enrolment" enrolment method with:
      | Custom instance name | Test student enrolment |

    Given I log out


  @javascript
  Scenario: Self-enrolment through course catalog requiring a group enrolment key
    When I log in as "student1"
    And I follow "Find Learning"
    And I click on ".rb-display-expand" "css_element"
    And I fill the moodle form with:
      | Enrolment key | Test-groupenrolkey1 |
    And I press "Enrol with - Self enrolment"
    Then I should see "Topic 1"
    And I should not see "Enrolment options"
    And I should not see "Enrol me in this course"
    And I log out

    When I log in as "student2"
    And I follow "Find Learning"
    And I click on ".rb-display-expand" "css_element"
    Then I should see "Guest access"
    And I fill the moodle form with:
      | Password | moodle_rules |
    And I press "Enrol with - Guest access"
    And I should see "Test forum name"
    And I log out

    When I log in as "student3"
    And I should see "Courses" in the "Navigation" "block"
    And I click on "Courses" "link_or_button" in the "Navigation" "block"
    And I click on ".rb-display-expand" "css_element"
    And I click on "[name$='_sid']" "css_element" in the "1 January 2020" "table_row"
    And I press "Enrol with - Face-to-face direct enrolment"
    Then I should see "Topic 1"
