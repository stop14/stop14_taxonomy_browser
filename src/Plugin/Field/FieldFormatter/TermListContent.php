<?php

namespace Drupal\stop14_taxonomy_browser\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\stop14_taxonomy_browser\Helper\TaxonomyBrowserHelper;

/**
 * Plugin implementation of the thumbnail field formatter.
 *
 * @FieldFormatter(
 *   id = "term_list_content",
 *   label = @Translation("List Related Content – NOT READY FOR USE"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class TermListContent extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $processed = [];
    $taxonomyHelper = new TaxonomyBrowserHelper();

    foreach($items->getValue() as $delta => $target) {

      if (!key_exists('target_id', $target)) {
        continue;
      }

      $ids = [];

      foreach ($taxonomyHelper->getNodesByTerm($target['target_id']) as $nobj) {
        $ids[] = $nobj->nid;
      }

      foreach (Node::loadMultiple($ids) as $node) {
        $item = [];
        $item['#url'] = \Drupal::service('path_alias.manager')
          ->getAliasByPath('/node/' . $node->id());
        $item['#bundle'] = $node->getType();
        $item['#sort'] = $taxonomyHelper->generateSortKey($node->hasField('field_key_title') ? $node->get('field_key_title')
          ->getValue()[0]['value'] : $node->getTitle());
        $item['#markup'] = $this->displayTitle($node, $item["#url"]);
        $processed[] = $item;
      }

      $element[$delta] = [
        '#type' => 'term_list_content',
        '#theme' => 'taxonomy_browser_term_content_list',
        '#items' => array_values($processed),
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /*
   * An end case requires the field_display_title be set for HTML title support.
   * This function uses the field value if it exists, or falls back to the title.
   * @todo: Refactor this.
   */

  protected function displayTitle($node_object,$url=null){
    $return_var = 0;
    /** @var \Drupal\node\NodeInterface $node_object */
    if(empty($node_object->get('field_display_title')->value)){
      $return_var = $node_object->get('title')->value;
    }else{
      $return_var = $node_object->get('field_display_title')->value;
    }

    if ($url) {
      $return_var = sprintf("<a class='display-title' href='%s'>%s</a>",$url,$return_var);
    }
    return $return_var;
  }

}
