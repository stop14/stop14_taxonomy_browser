<?php

namespace Drupal\stop14_taxonomy_browser\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\stop14_taxonomy_browser\Helper\TaxonomyBrowserHelper;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "taxonomy_browser_block",
 *   admin_label = @Translation("Taxonomy Browser"),
 *   category = @Translation("Taxonomy UI")
 * )
 */
class TaxonomyViewerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $config = $this->getConfiguration();

    if (!array_key_exists('taxonomy',$config)){
      return [
        'content' => [
          '#markup' => '<p class="warning">Administrators: No taxonomy selected.</p>',
        ],
      ];
    }

    // Set initial route. "All" is a default value set in the form below to indicate no selection.
    // An initial term of 0 will give the base (zero-depth) terms for this vocabulary.

    $initial_term = !empty($config['initial_term']) && $config['initial_term'] != 'all' ? $config['initial_term'] : 0;

    $build['content'] = [
      '#theme' => 'taxonomy_browser',
      '#taxonomy' => $config['taxonomy'],
      '#initialRoute'=> TAXONOMY_BROWSER_ENDPOINT_ROUTE .'/'. $config['taxonomy'] .'/'. $initial_term . '/1',
      '#endpoint' => TAXONOMY_BROWSER_ENDPOINT_ROUTE .'/'. $config['taxonomy'],
      '#attached' =>[
        'library' => [
          'stop14_taxonomy_browser/taxonomy_browser',
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    // Allow users to select node type to paginate

    $taxonomies = [];

    /* @var $node_type NodeType */
    foreach(\Drupal\taxonomy\Entity\Vocabulary::loadMultiple() as $taxonomy) {
      $taxonomies[$taxonomy->get('vid')] = $taxonomy->label();
    }

    asort($taxonomies);

    $initial_terms = [];

    // Load initial terms if taxonomy exists.

    if (array_key_exists('taxonomy',$config)) {
      $taxonomy_helper = new TaxonomyBrowserHelper;
      foreach ((array)$taxonomy_helper->getVocabTree($config['taxonomy'],1) as $term) {
        $initial_terms[$term->tid] = $term->name;
      }
    }

    $form['taxonomy'] = [
      '#type' => 'select',
      '#title' => $this->t('Taxonomy'),
      '#description' => $this->t('Choose the active vocabulary for this block. '),
      '#options' => $taxonomies,
      '#empty_option' => "Select",
      '#empty_value' => 'none',
      '#default_value' => $config['taxonomy'] ?? 'none',
      '#ajax' => [
        'callback' => [$this, 'ajaxGetTermOptions'],
        'disable-refocus' => FALSE,
        'event' => 'change',
        'wrapper' => 'edit-output',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Finding initial terms...'),
        ],
      ],
    ];

    $has_initial_term = array_key_exists('initial_term',$config) && !empty ($config['initial_term']);

    $form['initial_term'] = [
      '#type' => 'select',
      '#title' => $this->t('Intial Term'),
      '#description' => $this->t('Choose an initial term for this route or leave empty to use the whole taxonomy. '),
      '#options' => $initial_terms,
      // '#disabled' => $has_initial_term ? false : true,
      '#empty_option' => "Select a taxonomy above",
      '#empty_value' => 'all',
      '#default_value' => $has_initial_term ? $config['initial_term'] : 'all',
      '#validated' => true,
      '#prefix' => '<div id="edit-output">',
      '#suffix' => '</div>',
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['taxonomy'] = $values['taxonomy'];
    $this->configuration['initial_term'] = $values['initial_term'];

  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function ajaxGetTermOptions(array &$form, FormStateInterface $form_state) {

    // $form_state->getValue('taxonomy') returns null, field value expected
    // as per https://www.drupal.org/docs/drupal-apis/javascript-api/ajax-forms
    // Using $form_state->getValues()['settings'] instead.
    // @todo: debug

    $values = $form_state->getValues();
    $initial_term_field = &$form['settings']['initial_term'];

    if ($vocab = $values['settings']['taxonomy']) {
      $taxonomy_helper = new TaxonomyBrowserHelper;

      $options = ['all' => 'All terms'];

      foreach ((array)$taxonomy_helper->getVocabTree($vocab,1) as $i => $term) {
        $options[$term->tid] = $term->name;
      }

      // $field['#disabled'] = false;
      $initial_term_field['#options'] = $options;

    }

    return $initial_term_field;

  }

}
