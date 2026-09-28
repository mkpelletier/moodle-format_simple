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

  Scenario: Teacher can view course with activities
    Given the following "activities" exist:
      | activity | name            | intro           | course | idnumber | section |
      | page     | Test Page       | Page content    | C1     | page1    | 1       |
    And I log in as "teacher1"
    When I am on "Course 1" course homepage
    Then I should see "Unit 1"

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

  @javascript
  Scenario: A blocks drawer button's tooltip does not stay up over the other button
    Given the following "blocks" exist:
      | blockname    | contextlevel | reference | pagetypepattern | defaultregion |
      | online_users | Course       | C1        | course-view-*   | side-pre      |
    And the following "user preferences" exist:
      | user     | preference               | value |
      | teacher1 | behat_keep_drawer_closed | 1     |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    # The drawer's open and close buttons share a corner, so a tooltip left up on one covers
    # the other.
    And I hover ".drawer-right-toggle [data-toggler='drawers']" "css_element"
    And "//div[contains(@class, 'tooltip') and contains(@class, 'show')][contains(., 'Open block drawer')]" "xpath_element" should exist
    When I click on ".drawer-right-toggle [data-toggler='drawers']" "css_element"
    Then "//div[contains(@class, 'tooltip') and contains(@class, 'show')][contains(., 'Open block drawer')]" "xpath_element" should not exist
    And I hover ".drawer-right [data-action='closedrawer']" "css_element"
    And I click on ".drawer-right [data-action='closedrawer']" "css_element"
    And "//div[contains(@class, 'tooltip') and contains(@class, 'show')][contains(., 'Close block drawer')]" "xpath_element" should not exist
