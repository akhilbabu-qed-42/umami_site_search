<?php declare(strict_types = 1);

namespace Drupal\umami_site_search\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for umami_site_search routes.
 */
final class UmamiSiteSearchController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function search(): array {

    $build['content'] = [
      '#theme' => 'umami_search_page',
      '#attached' => [
        'library' => 'umami_site_search/site-search',
      ],
    ];

    return $build;
  }

}
