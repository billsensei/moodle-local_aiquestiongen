@local @local_aiquestiongen @javascript
Feature: A teacher reviews AI drafts, approves some, and publishes them to the question bank
  In order to use AI-drafted questions safely
  As a teacher
  I need to check, edit, approve and publish each draft myself

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
    And the following "activities" exist:
      | activity | name        | course | idnumber |
      | qbank    | Course bank | C1     | qb1      |
    And the following "question categories" exist:
      | contextlevel    | reference | name |
      | Activity module | qb1       | EFL  |
    And the following "local_aiquestiongen > drafts" exist:
      | course | user     | article          | qtype       | stem                       | correct | flagged |
      | C1     | teacher1 | Tom moves house  | multichoice | What does reluctant mean?  |         |         |
      | C1     | teacher1 | Tom moves house  | multichoice | Which word means unhappy?  |         |         |
      | C1     | teacher1 | Tom moves house  | multichoice | Broken question here?      |         | 1       |
      | C1     | teacher1 | Tom moves house  | truefalse   | Tom stayed in the town.    | false   |         |

  Scenario: Drafts are listed with their status and anything that needs checking
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    Then I should see "Pending: 4"
    And I should see "Needs checking: 1"
    And I should see "Multiple choice"
    And I should see "True/false"
    And I should see "Tom was reluctant to leave the town" in the "aiqg-source" "region"

  Scenario: A teacher approves, edits and rejects drafts without leaving the page
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    And I click "Approve" on the draft "What does reluctant mean?"
    Then the draft "What does reluctant mean?" should be "approved"
    And I should see "Approved: 1"
    And I should see "Pending: 3"
    When I click "Edit" on the draft "Tom stayed in the town."
    And I set the field "Question text" to "Tom left the town."
    And I click "Save changes" on the draft "Tom stayed in the town."
    Then I should see "Tom left the town."
    And I should not see "Tom stayed in the town."
    When I click "Reject" on the draft "Broken question here?"
    Then the draft "Broken question here?" should be "rejected"
    And I should see "Rejected: 1"
    When I click "Undo" on the draft "Broken question here?"
    Then the draft "Broken question here?" should be "pending"

  Scenario: Approve all skips drafts that need checking
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    And I click "Approve all pending" in the group "Multiple choice"
    Then the draft "What does reluctant mean?" should be "approved"
    And the draft "Which word means unhappy?" should be "approved"
    And the draft "Broken question here?" should be "pending"
    And the draft "Tom stayed in the town." should be "pending"
    And I should see "Approved: 2"

  Scenario: A draft that needs checking says what is wrong
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    Then the draft "Broken question here?" should be "pending"
    And I should see "the question does not have exactly 4 answer options"

  Scenario: The regenerate form can be opened and cancelled
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    And I click "Regenerate" on the draft "What does reluctant mean?"
    Then I should see "What should change?"
    When I click "Cancel" on the draft "What does reluctant mean?"
    Then I should not see "What should change?"
    And the draft "What does reluctant mean?" should be "pending"

  Scenario: Approved drafts are published to the question bank
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    And I click "Approve" on the draft "What does reluctant mean?"
    And I click "Approve" on the draft "Tom stayed in the town."
    And I click "Reject" on the draft "Broken question here?"
    And I press "Publish approved questions"
    Then I should see "Publish to the question bank"
    When I set the field "Question bank and category" to "Course bank / EFL"
    And I press "Publish 2 approved questions"
    Then I should see "2 questions were published to the question bank."
    And I should see "The article was also added as a reading passage."
    And the draft "What does reluctant mean?" should be "published"
    And the draft "Tom stayed in the town." should be "published"
    And the draft "Broken question here?" should be "rejected"
    And the draft "Which word means unhappy?" should be "pending"
    When I am on the "Course bank" "core_question > question bank" page
    And I apply question bank filter "Category" with value "EFL"
    Then I should see "What does reluctant mean?"
    And I should see "Tom stayed in the town."
    And I should see "Reading passage"
    And I should not see "Broken question here?"

  Scenario: A teacher who has used the daily regeneration allowance is refused, and the draft is kept
    Given the following config values are set as admin:
      | dailyregenlimit | 2 | local_aiquestiongen |
    And "teacher1" has already used 2 regenerations today
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    And I click "Regenerate" on the draft "What does reluctant mean?"
    And I click "Regenerate this question" on the draft "What does reluctant mean?" and see the error "You have reached the daily limit of 2 regenerations"
    Then I should not see "The AI provider could not generate text"
    And the draft "What does reluctant mean?" should be "pending"

  Scenario: A teacher below the daily limit reaches the AI, and the draft is kept when it fails
    Given the following config values are set as admin:
      | dailyregenlimit | 2 | local_aiquestiongen |
    And "teacher1" has already used 1 regeneration today
    When I am on the "Tom moves house" "local_aiquestiongen > review" page logged in as "teacher1"
    And I click "Regenerate" on the draft "What does reluctant mean?"
    And I click "Regenerate this question" on the draft "What does reluctant mean?" and see the error "The AI provider could not generate text"
    Then I should not see "daily limit"
    And the draft "What does reluctant mean?" should be "pending"
