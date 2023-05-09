<?php
namespace Drupal\stop14_taxonomy_browser\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\Cache;

define('ENTRIES_BY_TOPICS_RENDER_CACHE_KEY','ebtrenderkey');

/**
 * Provides route responses generating a full list of topics. See also REST Entry Viewer.
 */
class StaticTopicalEntryViewer extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */



  public function EntriesByTopics($rendertype='static') {

    // Return Static version of the page to spare memory errors

    if ($rendertype=='static') {

      $markup_static = ['#theme'=>'entries_by_topics_static_cache'];

      return [
        '#markup' => \Drupal::service('renderer')->render($markup_static)
      ];

    }

    $result = [];



    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('topics') as $item) {
      $parents = array_reverse(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadAllParents($item->tid), TRUE);
      $r = &$result;
      foreach ($parents as $k => $v) {
        if (isset($r[$k])) {
          $r[$k] = array_replace($r[$k], [$k => $v->label()]);
        }
        else {
          $r[$k] = [$k => $v->label()];
        }
        $r = &$r[$k];
      }
    }

    $markup = '';

    // Return cached version of the page, or build a new cache.


    if ($cache = \Drupal::cache()->get(ENTRIES_BY_TOPICS_RENDER_CACHE_KEY)) {
      $markup = $cache->data;
    } else {
      $markup = $this->MakeTopicList($result);
      $markup .=  "<div><em>Cached on " . date('Y-m-d H:i:s') . ".</em></div>";
      \Drupal::cache()->set(ENTRIES_BY_TOPICS_RENDER_CACHE_KEY,$markup,Cache::PERMANENT);
    }


    return [
      '#markup' => $markup
    ];
  }

  protected function MakeTopicList($array)
  {


    $out = '';

    $out .= "<ul>";

    foreach($array as $k => $v) {
      if (is_array($v)) {
        $out .= $this->MakeTopicList($v);
        continue;
      }

      $term = $v;
      $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['field_topics' => $k,]);

      $out .= "<li data-tid='{$k}'>" . $v . "</li>";

      if ($nodes) {
        $out .= "<ul>";

        foreach ($nodes as $node) {
          $out .= "<li><a href='/node/". $node->id() ."'>". $node->getTitle() . "</a></li>";
        }

        $out .= "</ul>";
      }

    }

    $out .= "</ul>";


    return $out;
  }
}


