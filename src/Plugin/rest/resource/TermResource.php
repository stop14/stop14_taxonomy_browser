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
use Drupal\node\Entity\Node;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "taxonomy_browser_term_content_resource",
 *   label = @Translation("REST Term Content Listing"),
 *   uri_paths = {
 *     "canonical" = "/rest/term/{tid}"
 *   }
 * )
 */
class TermResource extends ResourceBase
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
  public function get($tid) {
    return $this->getContentList($tid);
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
  public function post($tid) {

    return $this->getContentList($tid);
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
  public function getContentList($tid) {

    $items = [];

    /** @var \Drupal\node\Entity\Node $node */

    foreach ($this->get_nodes_by_term($tid) as $nobjs) {
      $node = $this->entityTypeManager->getStorage('node')->load($nobjs->nid);
      $title_with_markup = $node->getTitle();
      /* if($node->get('field_display_title')->getValue()){
        $title_with_markup = [
          '#type' => 'markup',
          "#markup" => check_markup($node->get('field_display_title')->getValue(), $node->get('field_display_title')->format)
        ];
      } */
      $items[$this->generateSortKey($node->getTitle())] = [
        'title' => $title_with_markup,
        'id'  => $node->id(),
        'displayTitle' => check_markup($node->get('field_display_title')->getValue(), $node->get('field_display_title')->format),
        'href'  => \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$node->id())
      ];
    }

    ksort($items);
    $sorted_items['content'] = array_values($items);

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
    return $key;
  }
}
