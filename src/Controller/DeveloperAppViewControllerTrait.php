<?php

namespace Drupal\apigee_edge\Controller;

use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trait for developer app view controllers.
 */
trait DeveloperAppViewControllerTrait {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $configFactory, DateFormatterInterface $date_formatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $configFactory;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Gets the view render array for a given developer app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return array
   *   The render array.
   */
  protected function getRenderArray(DeveloperAppInterface $developer_app): array {
    $config = $this->configFactory->get('apigee_edge.appsettings');
    $build = [
      '#cache' => [
        'contexts' => $developer_app->getCacheContexts(),
        'tags' => $developer_app->getCacheTags(),
      ],
    ];
    $build['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Details'),
      '#collapsible' => FALSE,
      '#attributes' => [
        'class' => [
          'items--inline',
          'apigee-edge-developer-app-view',
        ],
      ],
    ];

    $build['#attached']['library'][] = 'apigee_edge/apigee_edge.components';
    $build['#attached']['library'][] = 'apigee_edge/apigee_edge.view';

    $details_primary_elements = [
      'displayName' => [
        'label' => $this->t('@devAppLabel name', ['@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel()]),
        'value_type' => 'plain',
      ],
      'callbackUrl' => [
        'label' => $this->t('Callback URL'),
        'value_type' => 'plain',
      ],
      'description' => [
        'label' => $this->t('Description'),
        'value_type' => 'plain',
      ],
    ];

    $details_secondary_elements = [
      'status' => [
        'label' => $this->t('@devAppLabel status', ['@devAppLabel' => $this->entityTypeManager->getDefinition('developer_app')->getSingularLabel()]),
        'value_type' => 'status',
      ],
      'createdAt' => [
        'label' => $this->t('Created'),
        'value_type' => 'date',
      ],
      'lastModifiedAt' => [
        'label' => $this->t('Last updated'),
        'value_type' => 'date',
      ],
    ];

    $build['details']['primary_wrapper'] = $this->getContainerRenderArray($developer_app, $details_primary_elements);
    $build['details']['primary_wrapper']['#type'] = 'container';
    $build['details']['primary_wrapper']['#attributes']['class'] = ['wrapper--primary'];
    $build['details']['secondary_wrapper'][] = $this->getContainerRenderArray($developer_app, $details_secondary_elements);
    $build['details']['secondary_wrapper']['#type'] = 'container';
    $build['details']['secondary_wrapper']['#attributes']['class'] = ['wrapper--secondary'];

    if ($config->get('associate_apps')) {
      $credential_elements = [
        'consumerKey' => [
          'label' => $this->t('Consumer Key'),
          'value_type' => 'plain',
        ],
        'consumerSecret' => [
          'label' => $this->t('Consumer Secret'),
          'value_type' => 'plain',
        ],
        'issuedAt' => [
          'label' => $this->t('Issued'),
          'value_type' => 'date',
        ],
        'expiresAt' => [
          'label' => $this->t('Expires'),
          'value_type' => 'date',
        ],
        'status' => [
          'label' => $this->t('Key Status'),
          'value_type' => 'status',
        ],
      ];
      foreach ($developer_app->getCredentials() as $credential) {
        $build['credential'][$credential->getConsumerKey()] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Credential'),
          '#collapsible' => FALSE,
          '#attributes' => [
            'class' => [
              'items--inline',
              'apigee-edge-developer-app-view',
            ],
          ],
        ];

        $build['credential'][$credential->getConsumerKey()]['primary_wrapper'] = $this->getContainerRenderArray($credential, $credential_elements);
        $build['credential'][$credential->getConsumerKey()]['primary_wrapper']['#type'] = 'container';
        $build['credential'][$credential->getConsumerKey()]['primary_wrapper']['#attributes']['class'] = ['wrapper--primary'];

        $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['#type'] = 'container';
        $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['#attributes']['class'] = ['wrapper--secondary'];
        $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['title'] = [
          '#type' => 'label',
          '#title_display' => 'before',
          '#title' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
        ];

        foreach ($credential->getApiProducts() as $product) {
          /** @var \Drupal\apigee_edge\Entity\ApiProduct $api_product_entity */
          $api_product_entity = ApiProduct::load($product->getApiproduct());

          $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['api_product_list_wrapper'][$product->getApiproduct()] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'api-product-list-row',
                'clearfix',
              ],
            ],
          ];
          $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['api_product_list_wrapper'][$product->getApiproduct()]['name'] = [
            '#prefix' => '<span class="api-product-name">',
            '#markup' => Xss::filter($api_product_entity->getDisplayName()),
            '#suffix' => '</span>',
          ];

          $status = '';
          if ($product->getStatus() === 'approved') {
            $status = 'enabled';
          }
          elseif ($product->getStatus() === 'revoked') {
            $status = 'disabled';
          }
          elseif ($product->getStatus() === 'pending') {
            $status = 'pending';
          }

          $build['credential'][$credential->getConsumerKey()]['secondary_wrapper']['api_product_list_wrapper'][$product->getApiproduct()]['status'] = [
            '#type' => 'status_property',
            '#value' => $status,
          ];
        }
      }
    }

    return $build;
  }

  /**
   * Returns the render array of a container for a given entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   The entity.
   * @param array $elements
   *   The elements of the container.
   *
   * @return array
   *   The render array.
   */
  protected function getContainerRenderArray(EntityInterface $entity, array $elements): array {
    $build = [];
    $ro = new \ReflectionObject($entity);
    $hidden_value_types = [
      'consumerKey',
      'consumerSecret',
    ];
    foreach ($elements as $element => $settings) {
      $getter = 'get' . ucfirst($element);
      if (!$ro->hasMethod($getter)) {
        $getter = 'is' . ucfirst($element);
      }
      if ($ro->hasMethod($getter)) {
        $build[$element]['wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'item-property',
            ],
          ],
        ];
        $build[$element]['wrapper']['label'] = [
          '#type' => 'label',
          '#title' => $settings['label'],
          '#title_display' => 'before',
        ];

        if ($settings['value_type'] === 'plain') {
          $secret_attribute = '<span>';
          if (in_array($element, $hidden_value_types)) {
            $secret_attribute = '<span class="secret" data-secret-type="' . $element . '">';
          };
          $build[$element]['wrapper']['value'] = [
            '#prefix' => $secret_attribute,
            '#markup' => Xss::filter(call_user_func([$entity, $getter])),
            '#suffix' => '</span>',
          ];
        }
        elseif ($settings['value_type'] === 'date') {
          $value = call_user_func([$entity, $getter]) !== '-1' ? $this->dateFormatter->format(call_user_func([$entity, $getter]) / 1000, 'custom', 'D, m/d/Y - H:i', drupal_get_user_timezone()) : 'Never';
          $build[$element]['wrapper']['value'] = [
            '#markup' => Xss::filter($value),
          ];
        }
        elseif ($settings['value_type'] === 'status') {
          $build[$element]['wrapper']['value'] = [
            '#type' => 'status_property',
            '#value' => Xss::filter(call_user_func([$entity, $getter])),
          ];
        }
      }
    }
    return $build;
  }

  /**
   * Builds a translatable page title by using values from args as replacements.
   *
   * @param array $args
   *   An associative array of replacements.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   *
   * @see \Drupal\Core\StringTranslation\StringTranslationTrait::t()
   */
  protected function pageTitle(array $args = []): TranslatableMarkup {
    return $this->t('@name @devAppLabel', $args);
  }

}