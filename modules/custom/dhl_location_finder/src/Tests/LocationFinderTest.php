<?php

namespace Drupal\Tests\dhl_location_finder\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the DHL Location Finder functionality.
 *
 * @group dhl_location_finder
 */
class LocationFinderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dhl_location_finder'];

  /**
   * Tests the form submission.
   */
  public function testFormSubmission() {
    // Create a user with permission to access the content.
    $user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($user);

    // Visit the form page.
    $this->drupalGet('/dhl_location_finder');

    // Ensure the form exists.
    $this->assertSession()->statusCodeEquals(200);

    // Submit the form with test values.
    $edit = [
      'country' => 'NL',
      'city' => 'Zwaagdijk',
      'postal_code' => '1684NG',
    ];
    $this->submitForm($edit, 'Find Locations');

    // Ensure the results are displayed.
    $this->assertSession()->pageTextContains('locationName');
  }

}
