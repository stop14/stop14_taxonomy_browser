<?php
/*
 * @file stop14_taxonomy_browser.api
 */


/*
 * Alter the query that alters the information about a particular content item to
 * be displayed in the taxonomy browser. Can be used to modify sort order or
 * the title that is display (for instance in situations where the title requires
 * HTML).
 *
 * function hook_stop14_taxonomy_browser_alter_term_content(&$contentItem,$node) {
 *    $contentItem = [
        'sortkey' => $this->generateSortKey($node->getTitle()),
        'title' => $node->getTitle(),
        'id'  => $node->id(),
        'type' => $node->getType(),
        'displayTitle' => $node->getTitle(),
        'href'  => \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$node->id()),
      ];
 * }
 */
