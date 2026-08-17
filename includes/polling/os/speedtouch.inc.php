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

// Filthy hack to get software version. may not work on anything but 585v7 :)
if (preg_match('@([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)@i', snmp_get_oid($device, 'IF-MIB::ifDescr.101'), $matches)) {
    $version = $matches[1];
}

// EOF
