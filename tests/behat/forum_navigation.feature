@format @format_simple @mod_forum
Feature: Forum navigation in the Simple format
  In order to move around forums without a breadcrumb
  As a student
  I need a single Q&A discussion to open directly and the forum title to lead back to its list

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections | enablecompletion |
      | Course 1 | C1        | simple | 1           | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A student opens a Q&A forum's only discussion straight from the course page
    Given the following "activities" exist:
      | activity | name            | intro                  | course | idnumber | section | type  | completion | completionview |
      | forum    | Weekly question | Answer before Friday.  | C1     | qanda1   | 1       | qanda | 2          | 1              |
    And the following "mod_forum > discussions" exist:
      | user     | forum  | name          | subject       | message                     |
      | teacher1 | qanda1 | What is grace | What is grace | Define grace in a sentence. |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "Weekly question" "link"
    Then I should see "Define grace in a sentence."
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And "Student 1" user has completed "Weekly question" activity

  Scenario: A teacher still opens the Q&A forum's list of discussions
    Given the following "activities" exist:
      | activity | name            | intro                 | course | idnumber | section | type  |
      | forum    | Weekly question | Answer before Friday. | C1     | qanda1   | 1       | qanda |
    And the following "mod_forum > discussions" exist:
      | user     | forum  | name          | subject       | message                     |
      | teacher1 | qanda1 | What is grace | What is grace | Define grace in a sentence. |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I click on "Weekly question" "link"
    Then I should see "Answer before Friday."
    And I should not see "Define grace in a sentence."

  @javascript
  Scenario: The forum title on a discussion page leads back to the list of discussions
    Given the following "activities" exist:
      | activity | name        | intro             | course | idnumber | section |
      | forum    | Class forum | Talk freely here. | C1     | forum1   | 1       |
    And the following "mod_forum > discussions" exist:
      | user     | forum  | name         | subject      | message             |
      | teacher1 | forum1 | First topic  | First topic  | First topic words.  |
      | teacher1 | forum1 | Second topic | Second topic | Second topic words. |
    And I log in as "student1"
    And I am on the "Class forum" "forum activity" page
    And I follow "First topic"
    And I should see "First topic words."
    And I hover "a.simple-forum-backlink" "css_element"
    And I should see "Return to Forum Index"
    When I click on "a.simple-forum-backlink" "css_element"
    Then I should see "Talk freely here."
    And I should see "Second topic"
