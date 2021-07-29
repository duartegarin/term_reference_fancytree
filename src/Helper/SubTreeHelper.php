<?php

namespace Drupal\term_reference_fancytree\Helper;

use Drupal\Core\Url;

/**
 * SubTree helper service.
 */
class SubTreeHelper {

  /**
   * Create a SubTreeHelper object.
   */
  public function __construct() {
  }

  /**
   * Create a static SubTreeHelper object.
   *
   * @return SubTreeHelper
   *   Returns a SubTreeHelper instance.
   */
  public static function create() {
    return new static();
  }

  /**
   * Method that returns the controller's url by route ID.
   *
   * @param string $route
   *   Route ID.
   *
   * @return string
   *   Returns the controller's url.
   */
  public function getUrlPathByRoute(string $route) {
    return Url::fromRoute($route)->toString();
  }

  /**
   * Helper that implements hook_page_attachments().
   *
   * @param string $route
   *   Route ID.
   * @param array $page
   *   Drupal's page attributes.
   *
   * @return array
   *   Updated Drupal's page attributes.
   */
  public function getPageAttachments(string $route, array $page): array {
    $page['#attached']['drupalSettings']['lazyLoadUrl'] = $this->getUrlPathByRoute($route);

    return $page;
  }

}
