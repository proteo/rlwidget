<?php

namespace Drupal\rlwidget\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'revision_log_widget' widget.
 *
 * @FieldWidget(
 *   id = "revision_log_widget",
 *   label = @Translation("Revision Log Widget"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class RevisionLogWidget extends StringTextareaWidget implements ContainerFactoryPluginInterface {

  /**
   * Active user object.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $user;

  /**
   * Create the widget instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The symfony container.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The the plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\rlwidget\Plugin\Field\FieldWidget\RevisionLogWidget
   *   The widget.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user')
    );
  }

  /**
   * Constructs a RevisionLogWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountProxy $user
   *   The current user.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountProxy $user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display_mode' => 'show',
      'collapsed' => FALSE,
      'default' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $element['display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Field visibility'),
      '#description' => $this->t('"Based on permissions" will display the field to users with permission "%perm: Control Revision Messages".', [
        '%perm' => $this->fieldDefinition->getTargetEntityTypeId(),
      ]),
      '#options' => [
        'show' => $this->t('Visible (default)'),
        'hide' => $this->t('Hidden'),
        'permission' => $this->t('Based on user permissions'),
      ],
      '#default_value' => $settings['display_mode'],
    ];
    $element['collapsed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsed'),
      '#default_value' => $settings['collapsed'],
      '#description' => $this->t('Collapses the field by default.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[revision_log][settings_edit_form][settings][display_mode]"]' => ['!value' => 'hide'],
        ],
      ],
    ];
    $element['rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Rows'),
      '#default_value' => $this->getSetting('rows'),
      '#required' => TRUE,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="fields[revision_log][settings_edit_form][settings][display_mode]"]' => ['!value' => 'hide'],
        ],
      ],
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[revision_log][settings_edit_form][settings][display_mode]"]' => ['!value' => 'hide'],
        ],
      ],
    ];
    $element['default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default'),
      '#default_value' => $settings['default'],
      '#description' => $this->t('Default value for revision log.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[revision_log][settings_edit_form][settings][display_mode]"]' => ['!value' => 'hide'],
        ],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = [];

    switch ($settings['display_mode']) {
      case 'hide':
        $visibility = $this->t('Hidden');
        break;

      case 'permission':
        $visibility = $this->t('Based on user permissions');
        break;

      default:
        $visibility = $this->t('Visible');
    }

    $summary[] = $this->t('Visibility: @visibility', ['@visibility' => $visibility]);

    if ($settings['display_mode'] != 'hide') {
      $collapsed = $settings['collapsed'] ? $this->t('Yes') : $this->t('No');
      $summary[] = $this->t('Collapsed: @collapsed', ['@collapsed' => $collapsed]);

      $summary[] = $this->t('Number of rows: @rows', ['@rows' => $this->getSetting('rows')]);

      $placeholder = $this->getSetting('placeholder');
      if (!empty($placeholder)) {
        $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
      }

      $default = $this->getSetting('default');
      if (!empty($default)) {
        $summary[] = $this->t('Default value: %default', [
          '%default' => $default,
        ]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $node = $form_state->getFormObject()->getEntity();

    // Our customizations make sense only when creating new content.
    if ($node->isNew()) {
      $settings = $this->getSettings();

      switch ($settings['display_mode']) {
        case 'hide':
          $show = FALSE;
          break;

        case 'permission':
          $show = $this->user->hasPermission('access revision field');
          break;

        default:
          $show = TRUE;
      }

      if (!$show) {
        $element['value']['#type'] = 'hidden';
      }
      else {
        $element['#theme_wrappers']['details'] = [
          '#title' => $element['#title'],
          '#summary_attributes' => [
            'class' => [
              'claro-details__summary--accordion-item',
            ],
          ],
          '#attributes' => [
            'open' => !$settings['collapsed'],
            'class' => [
              'accordion__item',
              'claro-details--accordion-item',
            ],
          ],
          '#value' => NULL,
          '#description' => $element['#description'],
          '#required' => $element['#required'],
          '#errors' => NULL,
          '#disabled' => !empty($element['#disabled']),
        ];

        $element['value']['#title'] = NULL;
        $element['value']['#description'] = NULL;

        if (!empty($settings['default'])) {
          $element['value']['#default_value'] = $settings['default'];
        }

        if (!empty($settings['placeholder'])) {
          $element['value']['#attributes']['placeholder'] = $settings['placeholder'];
        }
      }
    }

    return $element;
  }

}
