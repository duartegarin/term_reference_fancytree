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
      'select_all' => FALSE,
      'select_children' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['select_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select all'),
      '#description' => $this->t('Display "Select all" link.'),
      '#default_value' => $this->getSetting('select_all'),
    ];

    $form['select_children'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Select children'),
      '#description' => $this->t('Select children terms when parent is selected. Note: Select children flag can affect performance since it will load all the children terms and also select them.'),
      '#default_value' => $this->getSetting('select_children'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $select_all = $this->getSetting('select_all') ? 'Yes' : 'No';
    $summary[] = $this->t('Select all: @select_all', ['@select_all' => $select_all]);

    $select_children = $this->getSetting('select_children') ? 'Yes' : 'No';
    $summary[] = $this->t('Select children: @select_children', ['@select_children' => $select_children]);

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
    $element['#select_all'] = $this->getSetting('select_all');
    $element['#select_children'] = $this->getSetting('select_children');

    return $element;
  }

}
