<?php

namespace Drupal\stop14_taxonomy_browser\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "taxonomy_viewer",
 *   label = @Translation("REST Taxonomy Viewer"),
 *   uri_paths = {
 *     "canonical" = "/rest/{vocab}/{tid}/{max_depth}"
 *   }
 * )
 */
class TaxonomyResource extends ResourceBase
{

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  protected $entityTypeManager;

  public function __construct(
    array                      $configuration,
                               $plugin_id,
                               $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    array                      $serializer_formats,
    LoggerInterface            $logger,
    AccountProxyInterface      $current_user)
  {

    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('itsb'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return JsonResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($vocab,$tid,$max_depth) {
    return $this->getTermList($vocab,$tid,$max_depth);
  }

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return CacheableJsonResponse
   *   The HTTP response object.
   */
  public function post($vocab,$tid,$max_depth) {

    return $this->getTermList($vocab,$tid,$max_depth);
  }

  /**
   * @param $vocab
   *   The vocabulary name as per \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree()
   *   Can be a vocabulary machine name or vid
   * @param $tid
   *   The vocabulary tid as per \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree().
   *   Use “0” for all tids in the vocabulary
   * @param $max_depth
   *   The depth of results to return
   *   Use “0” to return all levels
   *
   * @return CacheableJsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTermList($vocab, $tid, $max_depth) {
    $max_depth = $max_depth === "0" || $max_depth === 0 ? null : $max_depth; // ::loadTree method expects a null value to retrieve all depths
    $items = [];
    $i = 0;

    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocab,$tid,$max_depth) as $item) {
      $children = [];
      $j=0;
      foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocab,$item->tid,1) as $child) {
        $childsortkey = $this->generateSortKey($child->name,$j++,['General']);
        $children[$childsortkey] = $child;
      }
      ksort($children);
      $item->children = array_values($children); // Embed immediate children
      $item->childCount = count($children); // Provide number of children
      $item->childRoute = "/rest/{$vocab}/{$item->tid}/1"; // Provide route to generate child list
      $item->content = $this->get_nodes_by_term($tid);
      $item->contentCount = count($item->content);
      $item->contentRoute = "/rest/term/". $item->tid; // Provide route to generate content list
      $item->hasChildren = $item->contentCount != 0 || $item->childCount != 0;
      $sortkey = $this->generateSortKey($item->name,$i++,['General']);
      $items[$sortkey] = $item;
    }

    $content_items = [];

    foreach ($this->get_nodes_by_term($tid) as $nobjs) {

      /** @var \Drupal\node\Entity\Node $node */

      $node = $this->entityTypeManager->getStorage('node')->load($nobjs->nid);
      $content_items[$this->generateSortKey($node->getTitle())] = [
        'title' => $node->getTitle(),
        'id'  => $node->id(),
        'href'  => \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$node->id())
      ];
    }

    ksort($items);
    ksort($content_items);
    $sorted_items['terms'] = array_values($items);
    $sorted_items['content'] = array_values($content_items);

    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
    $term_label_build = [
      '#theme'=> 'taxonomy_browser_label',
      '#label' => $term->label()
    ];

    $sorted_items['parent'] = [
      'label' => $term->label(),
      'tid' => $tid,
      'build' => $term_label_build // Can't pass rendered build file through CacheableJsonResponse without caching error. @todo – figure out how to prerender.
    ];

    // Prepare route for caching. Invalidate when terms are updated.
    $sorted_items['#cache'] = [
      'max-age' => 600,
      'contexts' => [
        'url',
      ],
      'tags' => [
        'taxonomy_term_list','node_list'
      ]
    ];

    $response = new CacheableJsonResponse($sorted_items);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($sorted_items));

    return $response;

  }

  /** Utilities — @todo – move to helper class */

  private function get_nodes_by_term($tid) {
    $query = \Drupal::database()->select('taxonomy_index', 'ti');
    $query->fields('ti', ['nid']);
    $query->condition('ti.tid', $tid);
    $nobjs = $query->execute()->fetchAll();
    return $nobjs;
  }

  /**
   * @param $string
   *   A sortable string
   * @param $iterator
   *   An optional iterator to sort matching strings in order of appearance
   * @param array $sticky
   *   An array of strings that should be sorted at the top of a list (alphabetical afterwards)
   * @return string
   */
  private function generateSortKey($string, $iterator=0, $sticky = []): string
  {
    $string = str_replace(' ','',$string); // remove spaces
    $key =  preg_replace('/[^A-Za-z0-9\-]/', '', $string) . "_{$iterator}";
    if (in_array(trim($string),$sticky)) {
      $key = "000-{$key}"; // Force to start of list
    }
    return strtolower($key);
  }
}
