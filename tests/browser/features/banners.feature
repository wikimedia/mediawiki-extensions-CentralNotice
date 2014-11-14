@chrome @en.m.wikipedia.beta.wmflabs.org @en.wikipedia.beta.wmflabs.org @firefox
Feature: Crude banner display

  Scenario: Banner one is displayed for low roll
    Given I roll 0.25 for banner choice
    When I view an article
    Then I see banner one

  Scenario: Banner two is displayed for mid roll
    Given I roll 0.50 for banner choice
    When I view an article
    Then I see banner two

  Scenario: No banner is displayed for high roll
    Given I roll 0.75 for banner choice
    When I view an article
    Then I see no banner
