<?php

namespace Drupal\cgov_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cgov_core\Services\CgovNavigationManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'Breadcrumb' block.
 *
 * @Block(
 *  id = "cgov_breadcrumb",
 *  admin_label = @Translation("Cgov Breadcrumb"),
 *  category = @Translation("Cgov Digital Platform"),
 * )
 */
class Breadcrumb extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Cgov Navigation Manager Service.
   *
   * @var \Drupal\cgov_core\Services\CgovNavigationManagerInterface
   */
  protected $navMgr;

  /**
   * Constructs an LanguageBar object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\cgov_core\Services\CgovNavigationManagerInterface $navigationManager
   *   Cgov navigation service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CgovNavigationManagerInterface $navigationManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->navMgr = $navigationManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cgov_core.cgov_navigation_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowed();
  }

  /**
   * Get breadcrumbs to render.
   *
   * Using navigation service, return
   * array of breadcrumbs to render.
   *
   * @return array
   *   Array of breadcrumbs.
   */
  public function getBreadcrumbs() {
    $navRoot = $this->navMgr->getNavRoot('field_breadcrumb_root');
    // We don't want to render anything if there is the current
    // request is not associated with the Site Section vocabulary tree.
    if ($navRoot) {
      $breadcrumbs = [$navRoot];
      $child = $navRoot;
      // We only want to go as far as one level below the current site
      // section.
      while ($child && !$child->isCurrentSiteSection()) {
        $children = $child->getChildren(['isInActivePath']);
        $child = NULL;
        // We should only ever find one child in the active path.
        // Otherwise this is NULL and the while loop exits.
        if (count($children)) {
          $child = $children[0];
          // Requirement: Do not include breadcrumb for active page.
          if ($child && !$child->isCurrentSiteSection()) {
            $breadcrumbs[] = $child;
          }
        }
      }
      $formattedBreadcrumbs = array_map(function ($breadcrumb) {
        return [
          'href' => $breadcrumb->getHref(),
          'label' => $breadcrumb->getLabel(),
        ];
      }, $breadcrumbs);

      // Requirement: If the only breadcrumb is the root, we
      // don't want to render any breadcrumbs.
      if (count($formattedBreadcrumbs) === 1 && $formattedBreadcrumbs[0]['href'] === '/') {
        $formattedBreadcrumbs = [];
      }
      return $formattedBreadcrumbs;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $breadcrumbs = $this->getBreadcrumbs();
    $build = [
      '#type' => 'block',
      '#cache' => ['contexts' => ['url.path']],
      'breadcrumbs' => $breadcrumbs,
    ];

    return $build;
  }

}
