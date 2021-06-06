<?php

namespace Drupal\term_reference_fancytree\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * A term reference tree widget.
 *
 * @FieldWidget(
 *   id = "term_reference_fancytree",
 *   label = @Translation("Term Reference Fancytree"),
 *   field_types = {"entity_reference"},
 *   multiple_values = TRUE
 * )
 */
class TreeWidget extends WidgetBase implements WidgetInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'select_parents' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $element['select_parents'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select Parents'),
      '#default_value' => $this->getSetting('select_parents'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = [];
    $summary[] = $this->t('Select Parents: @select_parents', array('@select_parents' => $this->getSetting('select_parents')));
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Obtain the target vocabularies from the field settings.
    $handler_settings = $this->getFieldSetting('handler_settings');
    if (isset($handler_settings['target_bundles'])) {
      $vocabularies = Vocabulary::loadMultiple($handler_settings['target_bundles']);
    }
    else {
      $vocabularies = Vocabulary::loadMultiple();
    }
    // Define element settings.
    $element['#type'] = 'term_reference_fancytree';
    $element['#default_value'] = $items->getValue();
    $element['#vocabulary'] = $vocabularies;
    $element['#select_parents'] = $this->getSetting('select_parents');

    return $element;
  }
}
