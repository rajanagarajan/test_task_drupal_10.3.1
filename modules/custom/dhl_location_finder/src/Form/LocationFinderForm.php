<?php

namespace Drupal\dhl_location_finder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Client;
use Symfony\Component\Yaml\Yaml;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;

/**
 * Class LocationFinderForm.
 */
class LocationFinderForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'location_finder_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];
    $form['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Find Location'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $country = $form_state->getValue('country');
    $city = $form_state->getValue('city');
    $postal_code = $form_state->getValue('postal_code');

    $client = new Client();
    $response = $client->request('GET', 'https://api.dhl.com/location-finder/v1/find-by-address', [
      'headers' => [
        'DHL-API-Key' => 'demo-key',
      ],
      'query' => [
        'countryCode' => $country,
        'addressLocality' => $city
      ],
    ]);

    $data = json_decode($response->getBody()->getContents(), TRUE);
    
    $filtered = [];
    foreach ($data['locations'] as $location) {

      // Format the location in the desired structure.
      $formatted_location = [
        'locationName' => $location['name'],
        'address' => [
          'countryCode' => $location['place']['address']['countryCode'],
          'postalCode' => $location['place']['address']['postalCode'],
          'addressLocality' => $location['place']['address']['addressLocality'],
          'streetAddress' => $location['place']['address']['streetAddress'],
        ],
        'openingHours' => [],
      ];
     
      foreach ($location['openingHours'] as $hours) {
        $day= strtolower(basename($hours['dayOfWeek']));
        $formatted_location['openingHours'][$day] = "{$hours['opens']} - {$hours['closes']}";
  
      }
      
      $filtered[] = $formatted_location;
    }

    // Filter out locations with an odd number in their address
    function containsOddNumber($string) {
      preg_match('/\d+/', $string, $matches);
      if (isset($matches[0])) {
          $number = (int)$matches[0];
          // Check if the number is odd
          return $number % 2 !== 0;
      }
      return false;
    }

    // Filter locations that do not work on weekends.
    $filteredLocations = array_filter($filtered, function($location) {
      return isset($location["openingHours"]["saturday"]) && isset($location["openingHours"]["sunday"]);
    });
    
    $filteredLocations = array_values($filteredLocations);

    // Remove locations with odd street addresses
    $filteredLocations = array_filter($filteredLocations, function($location) {
      // Test the function
      if (containsOddNumber($location['address']['streetAddress'])) {
        $is_odd_address  = 0;
      } else {
        $is_odd_address  = 1;
      }
      return $is_odd_address ;
    });

    $output_content = array_values($filteredLocations);   
    
    // No records meet the criteria
    if (empty($filteredLocations)) {
      $output_content = "No records found!";
    }

    $yaml_output = Yaml::dump($output_content, 8, 2);

    // Convert the YAML output to HTML with line breaks
    $yaml_output_html = nl2br(Xss::filter($yaml_output));
    
    // Use Markup service to create safe markup
    $message = Markup::create($yaml_output_html);
    
    // Add the message to the messenger
    \Drupal::messenger()->addMessage($message);
    
  }
}
