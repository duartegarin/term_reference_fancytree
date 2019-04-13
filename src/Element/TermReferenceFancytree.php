<?php

namespace Drupal\term_reference_fancytree\Element;

use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\Html;
use Drupal\taxonomy\Entity\Term;

/**
 * Term Reference Tree Form Element.
 *
 * @FormElement("term_reference_fancytree")
 */
class TermReferenceFancytree extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processTree'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function processTree(&$element, FormStateInterface $form_state, &$complete_form) {

    if (!empty($element['#vocabulary'])) {

      // Get the top level nodes and auto-expanded nodes.
      $list = TermReferenceFancytree::getTopLevelNodes($element);
      $expandedNodes = TermReferenceFancytree::getExpandedNodes($element);

      // Attach our libary and settings.
      $element['#attached']['library'][] = 'term_reference_fancytree/tree';
      $element['#attached']['drupalSettings']['term_reference_fancytree'][$element['#id']]['tree'][] = [
        'id' => $element['#id'],
        'name' => $element['#name'],
        'source' => $list,
        // We pass default values to Javascript so we can have them selected.
        'default_values' => $element['#default_value'],
        // We pass the parent terms we want to auto-expand.
        'expanded' => $expandedNodes,
      ];

      // Create HTML wrappers.
      $element['tree'] = [];
      $element['tree']['#prefix'] = '<div id="' . $element['#id'] . '">';
      $element['tree']['#suffix'] = '</div>';
    }

    return $element;
  }

  /**
   * Returns the term parents that should be expanded by default.
   *
   * These are term parents that contain selected children.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   List of terms that should be expanded.
   */
  public static function getExpandedNodes(array $element) {

    // Load a list with the default values.
    $default_values = [];
    foreach ($element['#default_value'] as $default_value) {

      $default_values[] = $default_value['target_id'];
    }
    $default_values = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($default_values);

    // Create a list of unique term parents for our default values.
    // These parent terms will need to be expanded.
    $expanded = [];
    foreach ($default_values as $default_value) {
      $node['parents'] = $default_value->get('parent')->getValue();
      $node['vid'] = $default_value->get('vid')->getValue()[0]['target_id'];
      array_push($expanded, $node);
    }
    // Return the list without duplicates.
    return array_unique($expanded, SORT_REGULAR);
  }

  /**
   * Function that returns the top level nodes for the tree.
   *
   * If multiple vocabularies, it will return the vocabulary names, otherwise
   * it will return the top level terms.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   The nested JSON array with the top level nodes.
   */
  public static function getTopLevelNodes(array $element) {
    // If we have more than one vocabulary, we load the vocabulary names as
    // the initial level.
    if (count($element['#vocabulary']) > 1) {
      return TermReferenceFancytree::getVocabularyNamesJsonArray($element['#vocabulary']);
    }
    // Otherwise, we load the list of terms on the first level.
    else {
      $taxonomy_vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load(reset($element['#vocabulary'])->id());
      // Load the terms in the first level.
      $terms = TermReferenceFancytree::loadTerms($taxonomy_vocabulary, 0);
      // Convert the terms list into the Fancytree JSON format.
      return TermReferenceFancytree::getNestedListJsonArray($terms, $element['#default_value']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    $selected_terms = [];
    // Ensure our input is not empty and loop through the input values for
    // submission.
    // @Todo check if we need this.
    if (is_array($input) && !empty($input)) {
      foreach ($input as $tid) {
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
        if ($term) {
          $selected_terms[] = $tid;
        }
      }
    }
    return $selected_terms;
  }

  /**
   * Load one single level of terms, sorted by weight and alphabet.
   */
  public static function loadTerms($vocabulary, $parent = 0) {
    try {
      $query = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $vocabulary->id())
        ->condition('parent', $parent)
        ->sort('weight')
        ->sort('name');

      $tids = $query->execute();
      return \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
    }
    catch (QueryException $e) {
      // This site is still using the pre-Drupal 8.5 database schema, where
      // https://www.drupal.org/project/drupal/issues/2543726 was not yet
      // committed to Drupal core.
      // @todo Remove both the try/catch wrapper and the code below the catch-
      // statement once the module only supports Drupal 8.5 or
      // newer.
    }

    $database = \Drupal::database();
    $query = $database->select('taxonomy_term_data', 'td');
    $query->fields('td', ['tid']);
    $query->condition('td.vid', $vocabulary->id());
    $query->join('taxonomy_term_hierarchy', 'th', 'td.tid = th.tid AND th.parent = :parent', [':parent' => $parent]);
    $query->join('taxonomy_term_field_data', 'tfd', 'td.tid = tfd.tid');
    $query->orderBy('tfd.weight');
    $query->orderBy('tfd.name');

    $result = $query->execute();

    $tids = [];
    foreach ($result as $record) {
      $tids[] = $record->tid;
    }

    return \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
  }

  /**
   * Helper function that transforms a flat taxonomy tree in a nested array.
   */
  public static function getNestedList($tree = [], $max_depth = NULL, $parent = 0, $parents_index = [], $depth = 0) {
    foreach ($tree as $term) {
      foreach ($term->parents as $term_parent) {
        if ($term_parent == $parent) {
          $return[$term->id()] = $term;
        }
        else {
          $parents_index[$term_parent][$term->id()] = $term;
        }
      }
    }

    foreach ($return as &$term) {
      if (isset($parents_index[$term->id()]) && (is_null($max_depth) || $depth < $max_depth)) {
        $term->children = TermReferenceFancytree::getNestedList($parents_index[$term->id()], $max_depth, $term->id(), $parents_index, $depth + 1);
      }
    }

    return $return;
  }

  /**
   * Function that generates the nested list for the JSON array structure.
   */
  public static function getNestedListJsonArray($terms, $default_values) {
    $items = [];
    if (!empty($terms)) {
      foreach ($terms as $term) {
        $item = [
          'title' => Html::escape($term->getName()),
          'key' => $term->id(),
        ];

        // Checking the term against the default values and if present, mark as
        // selected.
        if (is_numeric(array_search($term->id(), array_column($default_values, 'target_id')))) {
          $item['selected'] = TRUE;
        }

        if (isset($term->children) || TermReferenceFancytree::getChildCount($term->id()) >= 1) {
          // If the given terms array is nested, directly process the terms.
          if (isset($term->children)) {
            $item['children'] = TermReferenceFancytree::getNestedListJsonArray($term->children, $default_values);
          }
          // It the term has children, but they are not present in the array,
          // mark the item for lazy loading.
          else {
            $item['lazy'] = TRUE;
          }
        }
        $items[] = $item;
      }
    }
    return $items;
  }

  /**
   * Function that generates a list of vocabulary names in JSON.
   */
  public static function getVocabularyNamesJsonArray($vocabularies) {
    $items = [];
    if (!empty($vocabularies)) {
      foreach ($vocabularies as $vocabulary) {
        $item = [
          'title' => Html::escape($vocabulary->get('name')),
          'key' => $vocabulary->id(),
          'vocab' => TRUE,
          'unselectable' => TRUE,
          'lazy' => TRUE,
          'folder' => TRUE,
        ];
        $items[] = $item;
      }
    }
    return $items;
  }

  /**
   * Helper function that returns the number of child terms.
   */
  public static function getChildCount($tid) {
    static $tids = [];

    if (!isset($tids[$tid])) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = Term::load($tid);
      $tids[$tid] = count(static::getTermStorage()->loadTree($term->bundle(), $tid, 1));

    }

    return $tids[$tid];
  }

  /**
   * Function to get term storage.
   *
   * @return \Drupal\taxonomy\TermStorageInterface
   *   The term storage.
   */
  protected static function getTermStorage() {
    return \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  }

}
