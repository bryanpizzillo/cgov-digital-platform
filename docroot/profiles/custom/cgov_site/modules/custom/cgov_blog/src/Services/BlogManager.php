<?php

namespace Drupal\cgov_blog\Services;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Blog Manager Service.
 */
class BlogManager implements BlogManagerInterface {

  /**
   * An entity query.
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route matcher.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatcher;

  /**
   * Constructor for BlogManager object.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   An entity query.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_matcher
   *   The route matcher.
   */
  public function __construct(
    QueryFactory $entity_query,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_matcher
  ) {
    $this->entityQuery = $entity_query;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatcher = $route_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentEntity() {
    // BlogManagerInterface implementation.
    $params = $this->routeMatcher->getParameters()->all();
    foreach ($params as $param) {
      if ($param instanceof ContentEntityInterface) {
        // If you find a ContentEntityInterface stop iterating and return it.
        return $param;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSeriesEntity() {
    $currEntity = $this->getCurrentEntity();
    $currId = $currEntity->id();
    $currBundle = $currEntity->bundle();

    // If this is a series.
    switch ($currBundle) {
      case 'cgov_blog_series':
        $seriesNode = $currEntity;
        break;

      case 'cgov_blog_post':
        $seriesNodeId = $this->getNodeStorage()->load($currId)->field_blog_series->target_id;
        $seriesNode = $this->getNodeStorage()->load($seriesNodeId);
        break;

      default:
        $seriesNode = NULL;
        break;
    }
    return $seriesNode;
  }

  /**
   * Get the Blog Series ID.
   */
  public function getSeriesId() {
    $series = $this->getSeriesEntity();
    return $series->id();
  }

  /**
   * Get the Blog Series ID.
   */
  public function getSeriesCategories() {
    $taxonomy = $this->getTaxonomyStorage()->loadTree('cgov_blog_topics');
    return $taxonomy;
  }

  /**
   * Get the Blog Featured content.
   */
  public function getBlogFeaturedContent() {
    return '';
  }

  /**
   * Create a new node storage instance.
   *
   * @return Drupal\Core\Entity\EntityStorageInterface
   *   The node storage or NULL.
   */
  public function getNodeStorage() {
    $node_storage = $this->entityTypeManager->getStorage('node');
    return isset($node_storage) ? $node_storage : NULL;
  }

  /**
   * Create a new node storage instance.
   *
   * @return Drupal\Core\Entity\EntityStorageInterface
   *   The taxonomy storage or NULL.
   */
  public function getTaxonomyStorage() {
    $taxonomy_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    return isset($taxonomy_storage) ? $taxonomy_storage : NULL;
  }

  /**
   * Return query results based on date posted.
   *
   * @param string $type
   *   Content type or bundle.
   * @param string $lang
   *   Language of current node.
   */
  public function getNodesByPostedDateDesc($type, $lang) {
    $query = $this->entityQuery->get('node');
    $query->condition('status', 1);
    $query->condition('type', $type);
    // Squery->condition('langcode', 'en');.
    $query->sort('field_date_posted', 'DESC');
    $nids = $query->execute();
    return $nids;
  }

}