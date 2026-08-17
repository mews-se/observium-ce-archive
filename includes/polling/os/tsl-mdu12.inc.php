<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     poller
 * @copyright  (C) Adam Armstrong
 *
 */

// Too old system, unknown compatibility
[ $hardware, $version ] = explode(' ', snmp_get_oid($device, 'mdu12Ident.0', 'TSL-MIB'));

// EOF
