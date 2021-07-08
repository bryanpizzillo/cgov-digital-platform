<?php

namespace Drupal\cgov_core;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\Core\Database\Connection;

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

  const MEDIA_PERMISSIONS = [
    'create [content_type] media',
    'delete any [content_type] media',
    'delete own [content_type] media',
    'edit any [content_type] media',
    'edit own [content_type] media',
    'translate [content_type] media',
  ];

  const PROD_AH_SITE_ENVS = [
    '01live',
    'prod',
  ];

  const TEST_AH_SITE_ENVS = [
    '01test',
    'test',
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

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
        'language-url' => "-8",
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
   * @param \Drupal\language\LanguageNegotiatorInterface $negotiator
   *   The language negotiation methods manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LanguageNegotiatorInterface $negotiator,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection
  ) {

    $this->negotiator = $negotiator;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
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
   * Links a media type to a workflow.
   *
   * See https://github.com/NCIOCPL/cgov-digital-platform/issues/127.
   */
  public function attachMediaTypeToWorkflow($type_name, $workflow_name) {
    $workflows = $this->entityTypeManager->getStorage('workflow')
      ->loadMultiple();
    $workflow = $workflows[$workflow_name];
    $workflow->getTypePlugin()->addEntityTypeAndBundle('media', $type_name);
    $workflow->save(TRUE);
  }

  /**
   * Links a block content type to a workflow.
   *
   * See https://github.com/NCIOCPL/cgov-digital-platform/issues/1732.
   */
  public function attachBlockContentTypeToWorkflow($type_name, $workflow_name) {
    $workflows = $this->entityTypeManager->getStorage('workflow')
      ->loadMultiple();
    $workflow = $workflows[$workflow_name];
    $workflow->getTypePlugin()
      ->addEntityTypeAndBundle('block_content', $type_name);
    $workflow->save(TRUE);
  }

  /**
   * Creates a new role.
   *
   * Roles cannot be managed by features because we do not store permissions
   * in the yml configs, but use addRolePermissions. Imports of roles wipe
   * out the permissions. Call this from your install_hook.
   *
   * NOTE: This defaults to creating non-admin roles.
   *
   * @param string $id
   *   The machine name of the role.
   * @param string $label
   *   The label for the role.
   * @param int $weight
   *   The weight for the role, whatever that may mean?
   *
   * @throws \Drupal\Core\Entity\EntityStorageException exception
   *   Expects role->save() to work.
   */
  public function addRole($id, $label, $weight) {
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $role = $role_storage->create([
      'id' => $id,
      'label' => $label,
      'weight' => $weight,
    ]);
    $role->save();
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
  public function addContentTypePermissions($type_name, $roles = self::DEFAULT_ROLES, $permissions = NULL) {
    // Define Common roles and permissions.
    $rolePerms['content_author'] = self::DEFAULT_PERMISSIONS;
    $rolePerms['content_editor'] = [];
    $rolePerms['advanced_editor'] = [];
    $rolePerms['layout_manager'] = self::DEFAULT_PERMISSIONS;

    // Convert $roles string to array if needed.
    if (!is_array($roles)) {
      $roles = [$roles];
    }

    // Get Role entities.
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $roleObjects = $role_storage->loadMultiple($roles);

    if (count($roleObjects) != count($roles)) {
      // Role not found, display error message.
      echo "Role(s) " . implode(', ', $roles) . " not found in " . __FUNCTION__ . "\n";
    }
    else {
      // Get all role objects.
      foreach ($roleObjects as $role_name => $roleObj) {
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
            $perms = self::DEFAULT_PERMISSIONS;
          }
        }

        // Update all the permissions.
        foreach ($perms as &$perm) {
          // Replace placeholders.
          $perm = str_replace('[content_type]', $type_name, $perm);

          // Grant Permission.
          $roleObj->grantPermission($perm);
          $roleObj->save();
        }
      }
    }
  }

  /**
   * Add Media Permissions to a role.
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
  public function addMediaTypePermissions($type_name, $roles = self::DEFAULT_ROLES, $permissions = NULL) {
    // Define Common roles and permissions.
    $rolePerms['content_author'] = self::MEDIA_PERMISSIONS;
    $rolePerms['content_editor'] = [];
    $rolePerms['advanced_editor'] = [];
    $rolePerms['layout_manager'] = self::MEDIA_PERMISSIONS;

    // Convert $roles string to array if needed.
    if (!is_array($roles)) {
      $roles = [$roles];
    }

    // Get Role entities.
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    $roleObjects = $role_storage->loadMultiple($roles);

    if (count($roleObjects) != count($roles)) {
      // Role not found, display error message.
      echo "Role(s) " . implode(', ', $roles) . " not found in " . __FUNCTION__ . "\n";
    }
    else {
      // Get all role objects.
      foreach ($roleObjects as $role_name => $roleObj) {
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
            $perms = self::MEDIA_PERMISSIONS;
          }
        }

        // Update all the permissions.
        foreach ($perms as &$perm) {
          // Replace placeholders.
          $perm = str_replace('[content_type]', $type_name, $perm);

          // Grant Permission.
          $roleObj->grantPermission($perm);
          $roleObj->save();
        }
      }
    }
  }

  /**
   * Remove [content_type] with passed variable in array of parameters.
   *
   * @param string $content_type
   *   Content type to replace [content_type] tokens.
   * @param array $permissions
   *   Array of strings containing permission names with [content_type] tokens.
   *
   * @return array
   *   Permission strings with [placeholder] replaced.
   */
  public function renameContentTypePermissions(string $content_type, array $permissions) {
    foreach ($permissions as &$perm) {
      $perm = str_replace('[content_type]', $content_type, $perm);
    }
    return $permissions;
  }

  /**
   * Get the raw AH_SITE_ENVIRONMENT global variable.
   *
   * @return string
   *   The name of the environment or empty string if variable is not set.
   */
  public function getAhSiteEnvironment() {
    return $_ENV['AH_SITE_ENVIRONMENT'] ?? '';
  }

  /**
   * Get one of our four tier values.
   *
   * @return string
   *   Returns one of the following: 'prod', 'test', 'dev', 'local'.
   */
  public function getCloudEnvironment() {
    $site_env = strtolower($this->getAhSiteEnvironment());

    // Check if site_env matches a prod environment name...
    if (in_array($site_env, self::PROD_AH_SITE_ENVS) ||
      preg_match('/^.*(www-prod-acsf|www-cms).*$/', $site_env)) {
      return CgovEnvironments::PROD;
    }
    // Otherwise, check if site_env matches a test environment name...
    elseif (in_array($site_env, self::TEST_AH_SITE_ENVS)) {
      return CgovEnvironments::TEST;
    }
    // Otherwise, check if site_env matches the dev environment regex...
    elseif (preg_match('/^(\d*(int|dev)|(ode)\d*)$/', $site_env)) {
      return CgovEnvironments::DEV;
    }
    // Finally, return 'local' if the variable is unmatched or empty.
    else {
      return CgovEnvironments::LOCAL;
    }
  }

  /**
   * Check if this is a production environment.
   *
   * @return bool
   *   TRUE if matches prod environment, FALSE otherwise.
   */
  public function isProd() {
    return $this->getCloudEnvironment() == CgovEnvironments::PROD;
  }

  /**
   * Returns an AccessResult for a Entity Reference Field for Filtering.
   *
   * We have a lot of paragraph entities that have an Entity Reference
   * Field that points to a content item and some override fields
   * (title, description, etc) to override the content item's fields
   * when rendering. (e.g. List Items, Related resources, Cards, etc)
   *
   * The access checks will correctly filter out unpublished content
   * items from displaying to anonymous users. However, for these paragraphs
   * that just results in an empty element being displayed. In many cases
   * the template for the paragraph does not generate any markup if there
   * is no content item. However, this can still result in field labels
   * and containers being displayed because the field containing the
   * paragraphs still has entities to display, they are just empty.
   *
   * This helper function can be used within a hook_paragraph_access function
   * to "bubble" up an AccessResult for the content item entity field.
   *
   * NOTE: This should only be done for "view" operations.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check (should be the paragraph).
   * @param string $field_name
   *   The name of the field that contains the content item.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The bubbled access.
   */
  public function filterAccessForDependantEntity(ContentEntityInterface $entity, $field_name) {
    if (!$entity->hasField($field_name)) {
      throw new Exception($field_name . " does not exist on entity");
    }

    $dependant = $entity->get($field_name)->entity;

    // There is no entity in this field.
    if (!$dependant) {
      return AccessResult::forbidden("Non-existant dependant");
    }

    // Gets the dependant's AccessResult. This access check was lifted from
    // EntityReferenceFormatterBase, and is the same check call that would
    // hide the content item.
    $access_result = $dependant->access('view', NULL, TRUE);

    if ($access_result->isAllowed()) {
      // If we are allowed to see the node, then we do not want to influence
      // any access checks.
      return AccessResult::neutral();
    }

    // Setup a forbidden result and copy over caching info for
    // the links.
    $new_res = AccessResult::forbidden("Forbidden list item");
    $new_res->setCacheMaxAge($access_result->getCacheMaxAge());
    $new_res->addCacheTags($access_result->getCacheTags());
    $new_res->addCacheContexts($access_result->getCacheContexts());

    return $new_res;
  }

  /**
   * Get the base URL for the active site's production tier.
   *
   * @todo Add support for other sites.
   */
  public function getProdUrl() {
    return 'https://www.cancer.gov';
  }

  /**
   * Returns an array of references to a specific target.
   *
   * @param \Drupal\cgov_core\EntityInterface $entity
   *   The taxonomy entity.
   * @param array $tax_reference_fields
   *   The fields to search within.
   * @param string $entity_table
   *   The entity prefix to search.
   *
   * @return mixed
   *   The array of references to the term.
   */
  public function getTermRefsFromField(EntityInterface $entity, array $tax_reference_fields, $entity_table = "node") {

    $references = [];
    foreach ($tax_reference_fields as $field) {
      // Query the entity field for references to this term.
      $query = $this->connection
        ->select($entity_table . '__' . $field, 'e');
      $query->addField('e', 'entity_id');
      $query->condition('e.' . $field . '_target_id', $entity->id);
      $result = $query->execute();
      $result_fields = $result->fetchAll();
      foreach ($result_fields as $ref) {
        $references[] = $ref->entity_id;
      }
    }
    return $references;
  }

}
