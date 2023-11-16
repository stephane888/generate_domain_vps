<?php

namespace Drupal\generate_domain_vps;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a domain ssl entity type.
 */
interface DomainSslInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
