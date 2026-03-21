@format @format_simple
Feature: Simple format course display and navigation
  In order to use the Simple course format
  As a teacher
  I need to create and navigate a course using the Simple format

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | simple | 3           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Teacher can view the course in Simple format
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    Then I should see "Course 1"

  Scenario: View the default section 0 name
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I navigate to "Settings" in current page administration
    Then I should see "Simple format"

  Scenario: Teacher can add activities to the course
    Given the following "activities" exist:
      | activity | name            | intro           | course | idnumber | section |
      | page     | Test Page       | Page content    | C1     | page1    | 1       |
      | assign   | Test Assignment | Assign content  | C1     | assign1  | 1       |
      | url      | Test URL        | URL description | C1     | url1     | 1       |
    And I log in as "teacher1"
    When I am on "Course 1" course homepage
    Then I should see "Test Page"
    And I should see "Test Assignment"
    And I should see "Test URL"

  Scenario: Student can view course content
    Given the following "activities" exist:
      | activity | name       | intro        | course | idnumber | section |
      | page     | Study Page | Page content | C1     | page1    | 1       |
    And I log in as "student1"
    When I am on "Course 1" course homepage
    Then I should see "Course 1"

  Scenario: Course format is set to Simple
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    Then I should see "Unit 1"
    And I should see "Unit 2"
    And I should see "Unit 3"
