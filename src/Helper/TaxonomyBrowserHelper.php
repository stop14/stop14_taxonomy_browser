<?php

namespace Drupal\stop14_taxonomy_browser\Helper;

use Drupal\taxonomy\TermInterface;

class TaxonomyBrowserHelper {

  /**
   * {@inheritdoc}
   */
  public function getNodesByTerm($tid) {
    $query = \Drupal::database()->select('taxonomy_index', 'ti');
    $query->fields('ti', ['nid']);
    $query->condition('ti.tid', $tid);
    $nobjs = $query->execute()->fetchAll();
    return $nobjs;
  }

  public function getTermChildren($tid,$max_depth=0) {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
    return \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($term->bundle(),$tid,$max_depth);
  }

  public function getVocabTree($bundle,$max_depth=0) {
    return \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($bundle,0,$max_depth);
  }

  public function getTermDepth($arg) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $arg instanceof TermInterface ? $arg : \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($arg);
    $parents = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($term->id());
    return count($parents);
  }

  /**
   * Returns the value of field_display_name if it exists. Otherwise returns the standard label.
   *
   * @param $arg  Takes tid or term object
   *
   * @return \Drupal\Component\Render\MarkupInterface|\Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */

  public function getTermDisplayName($arg) {
    $term = $arg instanceof TermInterface ? $arg : \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($arg);
    if ($term->hasField('field_display_name')) {
      if (!empty($term->get('field_display_name')->getValue())) {
        return check_markup($term->get('field_display_name')->getValue()[0]['value'],$term->get('field_display_name')->getValue()[0]['format']);
      }
    }
    return $term->label();
  }


  /**
   * @param $string
   *   A sortable string
   * @param $iterator
   *   An optional iterator to sort matching strings in order of appearance
   * @param array $sticky
   *   An array of strings that should be sorted at the top of a list (alphabetical afterwards)
   *
   * @return string
   */
  public function generateSortKey($string, $iterator = 0, $sticky = []): string {
    $string = str_replace(' ', '', strip_tags($string)); // remove spaces
    $key = preg_replace('/[^A-Za-z0-9\-]/', '', $string) . "_{$iterator}";
    if (in_array(trim($string), $sticky)) {
      $key = "000-{$key}"; // Force to start of list
    }
    return strtolower($key);
  }

}
