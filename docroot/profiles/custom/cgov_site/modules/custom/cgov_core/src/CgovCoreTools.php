<?php

namespace Drupal\cgov_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper service for various cgov installation tasks.
 *
 * @package Drupal\cgov_core
 */
class CgovCoreTools {
  const DEFAULT_ROLES = ['content_author', 'content_editor', 'advanced_editor'];

  const DEFAULT_PERMISSIONS = [
    'create [content_type] content',
    'delete any [content_type] content',
    'delete own [content_type] content',
    'edit any [content_type] content',
    'edit own [content_type] content',
    'revert [content_type] revisions',
    'translate [content_type] node',
    'view [content_type] revisions',
    // Excluded: 'delete [content_type] revisions'.
  ];

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language negotiator.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface
   */
  protected $negotiator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Our Language Negotiation settings.
   *
   * This would normally be in language.types.yml, but due to
   *   https://www.drupal.org/project/drupal/issues/2666998 it cannot be
   *   imported.
   *
   * @var array
   */
  private $cgovLangTypes = [
    'language_interface' => [
      'enabled' => [
        'language-user-admin' => "-10",
        'language-selected' => "12",
      ],
      'method_weights' => [
        'language-user-admin' => "-10",
        'language-url' => "-8",
        'language-session' => "-4",
        'language-user' => "-4",
        'language-browser' => "-2",
        'language-selected' => "12",
      ],
    ],
    'language_content' => [
      'enabled' => [
        'language-url' => "-8",
        'language-selected' => "12",
      ],
      'method_weights' => [
        'language-content-entity' => "-9",
        'language-url' => "-8",
        'language-session' => "-4",
        'language-user' => "-4",
        'language-browser' => "-2",
        'language-interface' => "9",
        'language-selected' => "12",
      ],
    ],
  ];

  /**
   * Constructs a CgovCoreTools object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\language\LanguageNegotiatorInterface $negotiator
   *   The language negotiation methods manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LanguageNegotiatorInterface $negotiator) {
    $this->negotiator = $negotiator;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Installs language types configuration until it is fixed in core.
   *
   * See https://www.drupal.org/project/drupal/issues/2666998 .
   */
  public function installLanguageNegotiation() {

    // Gets editable types config. Only used for *some* config changes.
    $typesConfig = $this->configFactory->getEditable('language.types');

    // Set the method weights for each negotiation type.
    foreach ($this->cgovLangTypes as $type => $typeConf) {
      $typesConfig->set(
        'negotiation.' . $type . '.method_weights',
        $typeConf['method_weights']
      )->save();
    }

    // Updates the configuration based on the given language types.
    $this->negotiator->updateConfiguration(array_keys($this->cgovLangTypes));

    // Set the enabled methods.
    foreach ($this->cgovLangTypes as $type => $typeConf) {
      $this->negotiator->saveConfiguration(
        $type,
        $typeConf['enabled']
      );
    }
  }

  /**
   * Add Permissions to a role.
   *
   * @param array $rolePermissions
   *   Array of [ RoleID => [ PermissionsList ] ] of permissions to add.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException exception
   *   Expects role->save() to work.
   */
  public function addRolePermissions(array $rolePermissions) {
    // Get Role entities.
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $roles = $role_storage->loadMultiple(array_keys($rolePermissions));

    // Add all permissions.
    foreach ($rolePermissions as $roleId => $permissionId) {
      foreach ($permissionId as $perm) {
        $roles[$roleId]->grantPermission($perm);
      }
      $roles[$roleId]->save();
    }

  }

  /**
   * Add Permissions to a role.
   *
   * @param string $type_name
   *   Content type name to be added.
   * @param mixed $roles
   *   Roles to be added, as string or array.
   * @param mixed $permissions
   *   Permissions to be added, as string or array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException exception.
   *   Expects getStorage to work.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Core\Entity\EntityStorageException exception
   *   Expects role->save() to work.
   */
  public function addContentTypePermissions($type_name, $roles = DEFAULT_ROLES, $permissions = NULL) {
    // Define Common roles and permissions.
    $rolePerms['content_author'] = DEFAULT_PERMISSIONS;
    $rolePerms['content_editor'] = [];
    $rolePerms['advanced_editor'] = [];
    $rolePerms['layout_manager'] = DEFAULT_PERMISSIONS;

    // Convert $roles string to array if needed.
    if (!is_array($roles)) {
      $roles = [$roles];
    }

    // Get Role entities.
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $roleObjects = $role_storage->loadMultiple($roles);

    if (count($roleObjects) != count($roles)) {
      // Role not found, display error message.
      print "Role(s) [" . implode(', ', $roles) . "] not found in " . __FUNCTION__ . "\n";
    }
    else {
      // Get all role objects.
      foreach ($roleObjects as $role_name => $roleObj) {
        echo "ROLE $role_name: \n";

        // Get permissions to assign.
        // If permissions are passed as a parameter, use that.
        if ($permissions) {
          // Convert to array if a string.
          if (!is_array($permissions)) {
            $perms = [$permissions];
          }
          else {
            $perms = $permissions;
          }
        }
        else {
          // No permissions passed, get list of permissions to use.
          if (isset($rolePerms[$role_name])) {
            // Get role-specific permissions.
            $perms = $rolePerms[$role_name];
          }
          else {
            // Load default permissions.
            $perms = DEFAULT_PERMISSIONS;
          }
        }

        // Update all the permissions.
        foreach ($perms as &$perm) {
          $perm = str_replace("[content_type]", "$type_name", $perm);
          // Grant Permission.
          print "Granting [$perm] to [$role_name]\n";
          $roleObj->grantPermission($perm);
          $roleObj->save();
        }
        echo "PERMS(strrpl): ";
        print_r($perms);
      }
    }
  }

}
