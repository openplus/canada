<?php

namespace Drupal\canada_experiments_ext_migration\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\AliasStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\panelizer\PanelizerInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Canada Experiments subscriber for controller requests.
 */
class MigrationSubscriber implements EventSubscriberInterface {

  /**
   * The database object.
   *
   * @var object
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The session manager service.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * The cache tag invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $invalidator;

  /**
   * The Panelizer service.
   *
   * @var \Drupal\panelizer\PanelizerInterface
   */
  protected $panelizer;

  /**
   * @var \Drupal\user\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * The path alias storage.
   *
   * @var \Drupal\Core\Path\AliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * Constructs a new MigrationSubscriber.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager service.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   UUID service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $invalidator
   *   The cache tag invalidator.
   * @param \Drupal\panelizer\PanelizerInterface $panelizer
   *   The Panelizer service.
   * @param \Drupal\user\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   * @param \Drupal\Core\Path\AliasStorageInterface $alias_storage
   *   The path alias storage.
   */
  public function __construct(Connection $database,
                              EntityManagerInterface $entity_manager,
                              EntityTypeManagerInterface $entity_type_manager,
                              ConfigFactoryInterface $config_factory,
                              SessionManagerInterface $session_manager,
                              Session $session,
                              AccountInterface $current_user,
                              UuidInterface $uuid_service,
                              CacheTagsInvalidatorInterface $invalidator,
                              PanelizerInterface $panelizer,
                              SharedTempStoreFactory $tempstore,
                              AliasStorageInterface $alias_storage) {
    $this->database = $database;
    $this->entityManager = $entity_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory;
    $this->sessionManager = $session_manager;
    $this->session = $session;
    $this->currentUser = $current_user;
    $this->uuidService = $uuid_service;
    $this->invalidator = $invalidator;
    $this->panelizer = $panelizer;
    $this->tempstore = $tempstore;
    $this->aliasStorage = $alias_storage;
  }

  /**
   * Code to run after a migration has been imported.
   */
  public function onMigrationPostImport(MigrateImportEvent $event) {

  }

  /**
   * Code to run after a migration row has been saved.
   */
  public function onMigrationPreRowSave(MigratePreRowSaveEvent $event) {

  }

  /**
   * Code to run after a migration row has been saved.
   */
  public function onMigrationPostRowSave(MigratePostRowSaveEvent $event) {
    // Landing Page logic.
    if ($event->getMigration()->id() == 'canada_node_landing_page' ||
        $event->getMigration()->id() == 'canada_node_landing_page_translation') {
      // Set front page to panelized "homepage".
      $name = $event->getRow()->getSourceProperty('name');
      // Bug in homepage detection logic prevents using alias.
      $destinationIds = $event->getDestinationIdValues();
      if ($name == 'homepage') {
        $this->config->getEditable('system.site')
          ->set('page.front', '/node/' . $destinationIds[0])
          ->save(TRUE);
      }
    }

    // Block logic for queue assignment.
    if ($event->getMigration()->id() == 'canada_block_spotlight') {
      $sourceBid = $event->getRow()->getSourceProperty('bid');
      $destBid = $event->getDestinationIdValues();
      if (!empty($sourceBid)) {
        switch ($sourceBid) {
          case 'feature_block_1':
          case 'feature_block_2':
          case 'feature_block_3':
            $this->entityQueueCreate('front_page', $destBid);
            break;
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PRE_ROW_SAVE] = 'onMigrationPreRowSave';
    $events[MigrateEvents::POST_ROW_SAVE] = 'onMigrationPostRowSave';
    $events[MigrateEvents::POST_IMPORT] = 'onMigrationPostImport';
    return $events;
  }

  /**
   * Add a specific entityqueue.
   */
  public function entityQueueCreate($queue, $destBid) {
    $entity_subqueue = $this->entityManager->getStorage('entity_subqueue')->load($queue);
    $items = $entity_subqueue->get('items')->getValue();
    $items[] = ['target_id' => $destBid[0]];
    $entity_subqueue->set('items', $items);
    $entity_subqueue->save();
  }

  /**
   * Add a menu link with dependency support.
   */
  public function menuLinkDependency($title, $link, $translations, $destBid, $weight = 0, $menu = 'main') {
    $menu_link_content = $this->entityManager->getStorage('menu_link_content')->create([
      'title' => $title,
      'link' => ['uri' => 'internal:/node/' . $destBid[0]],
      'menu_name' => (!empty($translations)) ? $menu . '-fr' : $menu,
      'langcode' => (!empty($translations)) ? 'fr' : 'en',
      'parent' => $link,
      'weight' => $weight,
    ]);
    $menu_link_content->save();
    $this->database->update('menu_link_content_data')
      ->fields(['link__uri' => 'entity:node/' . $destBid[0]])
      ->condition('id', $menu_link_content->id())
      ->execute();
    return $menu_link_content;
  }

}
