@local @local_aiquestiongen
Feature: A teacher saves an article and starts question generation
  In order to get draft questions from an article
  As a teacher
  I need to add the article and choose what to generate

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | teacher2 | Tina      | Teacher  | teacher2@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A teacher saves an article and queues generation
    Given I am on the "Course 1" "local_aiquestiongen > upload" page logged in as "teacher1"
    And I should see "Only upload or paste texts that you have the right to use"
    When I set the following fields to these values:
      | Article title | Tom moves house                                |
      | Article text  | Tom was reluctant to leave the town.           |
    And I press "Save article"
    Then I should see "The article was saved."
    And I should see "Article: Tom moves house"
    When I set the following fields to these values:
      | CEFR level                                       | B2 |
      | Number of multiple-choice questions              | 3  |
      | Number of true/false questions                   | 2  |
      | Number of open-ended (essay) questions           | 1  |
    And I press "Generate draft questions"
    Then I should see "Question generation"
    And I should see "Status: Waiting to start"
    And I should see "Generating in the background"

  Scenario: An article needs some text
    Given I am on the "Course 1" "local_aiquestiongen > upload" page logged in as "teacher1"
    When I set the field "Article title" to "Empty article"
    And I press "Save article"
    Then I should see "Paste some text or upload a file."

  Scenario: The number of questions is limited
    Given the following config values are set as admin:
      | maxquestionsperjob | 5 | local_aiquestiongen |
    And the following "local_aiquestiongen > drafts" exist:
      | course | user     | article         | qtype     | stem     |
      | C1     | teacher1 | Tom moves house | truefalse | A draft. |
    And I am on the "Tom moves house" "local_aiquestiongen > generate" page logged in as "teacher1"
    When I set the field "Number of multiple-choice questions" to "4"
    And I set the field "Number of true/false questions" to "4"
    And I press "Generate draft questions"
    Then I should see "The total number of questions must be between 1 and 5."

  Scenario: A student is not offered the generator
    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "AI question generator"

  @javascript
  Scenario: A teacher finds the generator in the course menu
    Given I am on the "Course 1" "course" page logged in as "teacher1"
    When I select "More" from secondary navigation
    Then I should see "AI question generator"
