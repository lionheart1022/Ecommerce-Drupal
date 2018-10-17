<?php

namespace Drupal\dcom_odoo_inventory\Exception;

/**
 * Exception thrown when product variation is missing the Odoo sync ID.
 */
class MissingOdooIdException extends SyncException {}
