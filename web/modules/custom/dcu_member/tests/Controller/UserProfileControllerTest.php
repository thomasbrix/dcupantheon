<?php

namespace Drupal\dcu_member\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the dcu_member module.
 */
class UserProfileControllerTest extends WebTestBase {


  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "dcu_member UserProfileController's controller functionality",
      'description' => 'Test Unit for module dcu_member and controller UserProfileController.',
      'group' => 'Other',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests dcu_member functionality.
   */
  public function testUserProfileController() {
    // Check that the basic functions of module dcu_member.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
