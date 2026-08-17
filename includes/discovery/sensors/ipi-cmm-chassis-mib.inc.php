<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage discovery
 * @copyright  (C) Adam Armstrong
 *
 */

// IPI-CMM-CHASSIS-MIB
// Here DOM sensors for OcNOS os, currently not possible convert to definitions
// because used multichannel sensors (ie for 40G/100G interfaces)
// and hard port entity associatiation

$cmmTrans    = snmpwalk_multipart_oid($device, "cmmTransEEPROMTable", [], $mib);
$cmmTransDDM = snmpwalk_multipart_oid($device, "cmmTransDDMTable", [], $mib);
print_debug_vars($cmmTrans);
print_debug_vars($cmmTransDDM);

// IPI-CMM-CHASSIS-MIB::cmmTransType.1.1 = INTEGER: qsfp(2)
// IPI-CMM-CHASSIS-MIB::cmmTransNoOfChannels.1.1 = INTEGER: 4
// IPI-CMM-CHASSIS-MIB::cmmTransidentifier.1.1 = INTEGER: qsfp28-or-later(18)
// IPI-CMM-CHASSIS-MIB::cmmTransSFPextendedidentifier.1.1 = INTEGER: not-applicable(-100002)
// IPI-CMM-CHASSIS-MIB::cmmTransQSFPextendedidentifier.1.1 = BITS: 16 80 00 00 powerclass4-3dot5wmax(3) cdrpresent-in-tx(5) cdrpresent-in-rx(6) powerclass6-4dot5wmax(8)
// IPI-CMM-CHASSIS-MIB::cmmTransconnectortype.1.1 = INTEGER: lucent-connector(8)
// IPI-CMM-CHASSIS-MIB::cmmTransEthCompliance.1.1 = INTEGER: unavailable(-100001)
// IPI-CMM-CHASSIS-MIB::cmmTransExtEthCompliance.1.1 = INTEGER: eec-100gbase-lr4-or-25gbase-lr(3)
// IPI-CMM-CHASSIS-MIB::cmmTransSonetCompliance.1.1 = BITS: 00 00 00 00
// IPI-CMM-CHASSIS-MIB::cmmTransFiberChnlLinkLen.1.1 = BITS: 00 00 00 00
// IPI-CMM-CHASSIS-MIB::cmmTransFiberChnlTransTech.1.1 = BITS: 00 00 00 00
// IPI-CMM-CHASSIS-MIB::cmmTransFiberChnlTransMedia.1.1 = BITS: 00 00 00 00
// IPI-CMM-CHASSIS-MIB::cmmTransSFPFiberChnlSpeed.1.1 = BITS: F8 00 00 00 fcs-3200mbps(0) fcs-1600mbps(1) fcs-1200mbps(2) fcs-800mbps(3) fcs-400mbps(4)
// IPI-CMM-CHASSIS-MIB::cmmTransQSFPFiberChnlSpeed.1.1 = BITS: 00 00 00 00
// IPI-CMM-CHASSIS-MIB::cmmTransSFPInfiniBandCompliance.1.1 = INTEGER: not-applicable(-100002)
// IPI-CMM-CHASSIS-MIB::cmmTransSFPEsconCompliance.1.1 = INTEGER: not-applicable(-100002)
// IPI-CMM-CHASSIS-MIB::cmmTransSfpPlusCableTech.1.1 = INTEGER: not-applicable(-100002)
// IPI-CMM-CHASSIS-MIB::cmmTransEncoding.1.1 = INTEGER: enc-64b-or-66b(7)
// IPI-CMM-CHASSIS-MIB::cmmTransLengthKmtrs.1.1 = INTEGER: 10 km
// IPI-CMM-CHASSIS-MIB::cmmTransLengthMtrs.1.1 = INTEGER: -100002 100 m
// IPI-CMM-CHASSIS-MIB::cmmTransLengthOM1.1.1 = INTEGER: 0 10 m
// IPI-CMM-CHASSIS-MIB::cmmTransLengthOM2.1.1 = INTEGER: 0 10 m
// IPI-CMM-CHASSIS-MIB::cmmTransLengthOM3.1.1 = INTEGER: 0 10 m
// IPI-CMM-CHASSIS-MIB::cmmTransLengthOM4.1.1 = INTEGER: 0 10 m
// IPI-CMM-CHASSIS-MIB::cmmTransVendorName.1.1 = STRING: FS
// IPI-CMM-CHASSIS-MIB::cmmTransVendorOUI.1.1 = STRING: 0x64 0x9d 0x99
// IPI-CMM-CHASSIS-MIB::cmmTransVendorPartNumber.1.1 = STRING: QSFP28-BLR4-100G
// IPI-CMM-CHASSIS-MIB::cmmTransVendorRevision.1.1 = STRING: 01
// IPI-CMM-CHASSIS-MIB::cmmTransCheckCode.1.1 = STRING: "0x49"
// IPI-CMM-CHASSIS-MIB::cmmTransCheckCodeExtended.1.1 = STRING: "0xb3"
// IPI-CMM-CHASSIS-MIB::cmmTransNominalBitRate.1.1 = INTEGER: 255 100MBd
// IPI-CMM-CHASSIS-MIB::cmmTransBitRateMax.1.1 = INTEGER: -100002
// IPI-CMM-CHASSIS-MIB::cmmTransBitRateMin.1.1 = INTEGER: -100002
// IPI-CMM-CHASSIS-MIB::cmmTransVendorSerialNumber.1.1 = STRING: G2140570553
// IPI-CMM-CHASSIS-MIB::cmmTransDateCode.1.1 = STRING: 220411
// IPI-CMM-CHASSIS-MIB::cmmTransDDMSupport.1.1 = INTEGER: yes(1)
// IPI-CMM-CHASSIS-MIB::cmmTransMaxCaseTemp.1.1 = INTEGER: 70  0.01 C
// IPI-CMM-CHASSIS-MIB::cmmTransSFPOptionsImp.1.1 = BITS: F8 00 00 00 reserved(0) power-level3(1) paging(2) internal-retimer-or-cdr(3) cooled-laser-transmitter(4)
// IPI-CMM-CHASSIS-MIB::cmmTransQSFPOptionsImp.1.1 = BITS: 3D 7F BD 00 tx-inputequalization-fixed-programmable(2) tx-outputemphasis-fixed-programmable(3) tx-outputamplitude-fixed-programmable(4) tx-cdr-on-or-off-contr
// ollable(5) rx-cdr-on-or-off-controllable(7) tx-cdr-lossoflock(9) rx-cdr-lossoflock(10) rx-squelch-disable(11) rx-output-disable(12) tx-squelch-disable(13) tx-squelch(14) page2-provided(15) page1-provided(16) ratese
// lect-fixed(18) tx-disable(19) tx-fault(20) tx-squelch-to-reduce-pave(21) tx-loss-of-signal(23)
// IPI-CMM-CHASSIS-MIB::cmmTransPresence.1.1 = INTEGER: present(1)
// IPI-CMM-CHASSIS-MIB::cmmTransFrontPanelPortNumber.1.1 = INTEGER: 0
// IPI-CMM-CHASSIS-MIB::cmmTransXFPextendedidentifier.1.1 = BITS: F8 00 00 00 powerlevel1-1dot5wmax(0) powerlevel2-2dot5wmax(1) powerlevel3-3dot5wmax(2) powerlevel4-over3dot5w(3) cdr-none(4)
// IPI-CMM-CHASSIS-MIB::cmmTransXFP10GEthCompliance.1.1 = BITS: F8 00 00 00 xec-10gbase-sr(0) xec-10gbase-lr(1) xec-10gbase-er(2) xec-10gbase-lrm(3) xec-10gbase-sw(4)
// IPI-CMM-CHASSIS-MIB::cmmTransXFP10GFiberChnCompliance.1.1 = BITS: F8 00 00 00 xfcc-1200-mx-sn-I(0) xfcc-1200-sm-ll-l(1) xfcc-exended-reach-1550nm(2) xfcc-exen-reach-1300nm-fp(3) 4
// IPI-CMM-CHASSIS-MIB::cmmTransXFP10GCopperLinksRsvd.1.1 = BITS: F8 00 00 00 xcl-rsvd(0) 1 2 3 4
// IPI-CMM-CHASSIS-MIB::cmmTransXFPLowerSpeedLinks.1.1 = BITS: F8 00 00 00 xlsl-1000base-sx(0) xlsl-1000base-lx(1) xlsl-2xfc-mmf(2) xlsl-2xfc-smf(3) xlsl-oc48-sr(4)
// IPI-CMM-CHASSIS-MIB::cmmTransXFPSonetInterconnect.1.1 = BITS: F8 00 00 00 xsi-i-64-lr(0) xsi-i-64-l(1) xsi-i-64-2r(2) xsi-i-64-2(3) xsi-i-64-3(4)
// IPI-CMM-CHASSIS-MIB::cmmTransXFPSonetShortHaul.1.1 = BITS: F8 00 00 00 xssh-s-64-l(0) xssh-s-64-2a(1) xssh-s-64-2b(2) xssh-s-64-3a(3) xssh-s-64-3b(4)
// IPI-CMM-CHASSIS-MIB::cmmTransXFPSonetLongHaul.1.1 = BITS: F8 00 00 00 xslh-l-64-l(0) xslh-l-64-2a(1) xslh-l-64-2b(2) xslh-l-64-2c(3) xslh-l-64-3(4)
// IPI-CMM-CHASSIS-MIB::cmmTransXFPSonetVeryLongHaul.1.1 = BITS: F8 00 00 00 xsvh-v-64-2a(0) xsvh-v-64-2b(1) xsvh-v-64-3(2) 3 4
// IPI-CMM-CHASSIS-MIB::cmmTransXFPOptionsImp.1.1 = BITS: F8 00 00 00 xfp-vps(0) xfp-tx-disable(1) xfp-p-down(2) xfp-vps-lv(3) xfp-vps-bypass(4)
// IPI-CMM-CHASSIS-MIB::cmmTransXFPVoltageAuxMonitor.1.1 = BITS: F8 00 00 00 xfp-vcc-5(0) xfp-vcc-3(1) xfp-vcc-2(2) 3 4
// IPI-CMM-CHASSIS-MIB::cmmTransXFPEncoding.1.1 = BITS: F8 00 00 00 enc-rsvd0(0) enc-rsvd1(1) enc-rsvd2(2) enc-rz(3) enc-nrz(4)
// IPI-CMM-CHASSIS-MIB::cmmTransWavelength.1.1 = INTEGER: -100002 0.001 nm
// IPI-CMM-CHASSIS-MIB::cmmTransChannelNumber.1.1 = INTEGER: -100002
// IPI-CMM-CHASSIS-MIB::cmmTransGridSpacing.1.1 = INTEGER: -100002 0.01 GHz
// IPI-CMM-CHASSIS-MIB::cmmTransLaserFirstFrequency.1.1 = INTEGER: -100002 0.01 GHz
// IPI-CMM-CHASSIS-MIB::cmmTransLaserLastFrequency.1.1 = INTEGER: -100002 0.01 GHz

foreach ($cmmTrans as $unit => $unit_entry) {
    foreach ($unit_entry as $trans_index => $trans_entry) {
        // Skip not installed
        if ($trans_entry['cmmTransPresence'] === 'notpresent') {
            continue;
        }

        $multilane = $trans_entry['cmmTransNoOfChannels'] > 1;
        //$multilane = safe_count($cmmTransDDM[$unit][$trans_index]) > 1;

        // FIXME. Probably different port association on different vendor hardware.. very annoying
        // Ie, index 1.45 -> ifName xe45
        // cmmTransFrontPanelPortNumber 0 -> ifName ce0
        $trans_port   = NULL;
        $trans_number = $trans_entry['cmmTransFrontPanelPortNumber'] ?? $trans_index;
        $trans_sql    = 'SELECT * FROM `ports` WHERE `device_id` = ? AND `ifIndex` > 1000 AND `deleted` = 0 AND `ifType` = ? AND `ifName` REGEXP ?';
        //if (!$multilane) {
            // Try detect port
            $trans_pattern = '^[a-z]{2,}' . $trans_number . '$'; // xe48, ce48
            $trans_port = dbFetchRow($trans_sql, [ $device['device_id'], 'ethernetCsmacd', $trans_pattern ]);
            //print_debug_vars($trans_port);
        //}

        foreach ($cmmTransDDM[$unit][$trans_index] as $lane => $entry) {
            if (empty($trans_port)) {
                // Multilane tranceivers
                // Ie, index 1.49.1 -> ifName xe49/1
                $trans_pattern = '^[a-z]{2,}' . $trans_number . '/' . $lane . '$'; // xe48/1, ce48/4
                $port = dbFetchRow($trans_sql, [ $device['device_id'], 'ethernetCsmacd', $trans_pattern ]);
                //print_debug_vars($port);
            } else {
                $port = $trans_port;
            }

            $options = [ 'entPhysicalIndex' => $trans_number ];
            if ($port) {
                $options['measured_class']            = 'port';
                $options['measured_entity']           = $port['port_id'];
                $options['entPhysicalIndex_measured'] = $port['ifIndex'];
                $options['measured_entity_label']     = $port['port_label'];
                $options['port_label']                = $port['port_label'];
                print_debug('Linked to port ' . $port['port_id']);
                print_debug_vars($port);

            } else {
                // Unassociated port
                $options['measured_class']            = 'fiber';
                $options['measured_entity_label']     = "Port $trans_number";
                $options['port_label']                = "Port $trans_number";
            }
            $name     = $options['port_label'];
            if ($multilane) {
                // For multilane append lane number
                $name .= ' Lane ' . $lane;
            }
            // Append extended transceiver info
            $name_ext = ' (' . $trans_entry['cmmTransVendorName'] . ' ' . $trans_entry['cmmTransVendorPartNumber'] . ' ' . $trans_entry['cmmTransLengthKmtrs'] . 'km)';

            $index = "$unit.$trans_index.$lane";

            $descr    = $name . ' Temperature' . $name_ext;
            $oid_name = 'cmmTransTemperature';
            $oid_num  = '.1.3.6.1.4.1.36673.100.1.2.3.1.2.' . $index;
            $scale    = 0.01;
            //$type     = $mib . '-' . $oid_name;
            $value = $entry[$oid_name];

            if ($value > -100000) { // '-100001' indicates unavailable
                $limits = $options;
                if ($entry['cmmTransTempCriticalThresholdMax'] > -100000) {
                    $limits['limit_high'] = $entry['cmmTransTempCriticalThresholdMax'] * $scale;
                }
                if ($entry['cmmTransTempAlertThresholdMax'] > -100000) {
                    $limits['limit_high_warn'] = $entry['cmmTransTempAlertThresholdMax'] * $scale;
                }
                if ($entry['cmmTransTempAlertThresholdMin'] > -100000) {
                    $limits['limit_low_warn'] = $entry['cmmTransTempAlertThresholdMin'] * $scale;
                }
                if ($entry['cmmTransTempCriticalThresholdMin'] > -100000) {
                    $limits['limit_low'] = $entry['cmmTransTempCriticalThresholdMin'] * $scale;
                }

                discover_sensor_ng($device, 'temperature', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, $limits);
            }

            $descr    = $name . ' Voltage' . $name_ext;
            $oid_name = 'cmmTransVoltage';
            $oid_num  = '.1.3.6.1.4.1.36673.100.1.2.3.1.7.' . $index;
            $scale    = 0.001;
            //$type     = $mib . '-' . $oid_name;
            $value = $entry[$oid_name];

            if ($value > -100000) { // '-100001' indicates unavailable
                $limits = $options;
                if ($entry['cmmTransVoltCriticalThresholdMax'] > -100000) {
                    $limits['limit_high'] = $entry['cmmTransVoltCriticalThresholdMax'] * $scale;
                }
                if ($entry['cmmTransVoltAlertThresholdMax'] > -100000) {
                    $limits['limit_high_warn'] = $entry['cmmTransVoltAlertThresholdMax'] * $scale;
                }
                if ($entry['cmmTransVoltAlertThresholdMin'] > -100000) {
                    $limits['limit_low_warn'] = $entry['cmmTransVoltAlertThresholdMin'] * $scale;
                }
                if ($entry['cmmTransVoltCriticalThresholdMin'] > -100000) {
                    $limits['limit_low'] = $entry['cmmTransVoltCriticalThresholdMin'] * $scale;
                }

                discover_sensor_ng($device, 'voltage', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, $limits);
            }

            $descr    = $name . ' Bias Current' . $name_ext;
            $oid_name = 'cmmTransLaserBiasCurrent';
            $oid_num  = '.1.3.6.1.4.1.36673.100.1.2.3.1.12.' . $index;
            $scale    = 0.001;
            //$type     = $mib . '-' . $oid_name;
            $value = $entry[$oid_name];

            if ($value > -100000) { // '-100001' indicates unavailable
                $limits = $options;
                if ($entry['cmmTransLaserBiasCurrCriticalThresholdMax'] > -100000) {
                    $limits['limit_high'] = $entry['cmmTransLaserBiasCurrCriticalThresholdMax'] * $scale;
                }
                if ($entry['cmmTransLaserBiasCurrAlertThresholdMax'] > -100000) {
                    $limits['limit_high_warn'] = $entry['cmmTransLaserBiasCurrAlertThresholdMax'] * $scale;
                }
                if ($entry['cmmTransLaserBiasCurrAlertThresholdMin'] > -100000) {
                    $limits['limit_low_warn'] = $entry['cmmTransLaserBiasCurrAlertThresholdMin'] * $scale;
                }
                if ($entry['cmmTransLaserBiasCurrCriticalThresholdMin'] > -100000) {
                    $limits['limit_low'] = $entry['cmmTransLaserBiasCurrCriticalThresholdMin'] * $scale;
                }

                discover_sensor_ng($device, 'current', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, $limits);
            }

            $descr    = $name . ' TX Power' . $name_ext;
            $oid_name = 'cmmTransTxPower';
            $oid_num  = '.1.3.6.1.4.1.36673.100.1.2.3.1.17.' . $index;
            $scale    = 0.001;
            //$type     = $mib . '-' . $oid_name;
            $value = $entry[$oid_name];

            if ($value >= -100000) { // '-100001' indicates unavailable
                $limits = $options;
                if ($entry['cmmTransTxPowerCriticalThresholdMax'] >= -100000) {
                    $limits['limit_high'] = $entry['cmmTransTxPowerCriticalThresholdMax'] * $scale;
                }
                if ($entry['cmmTransTxPowerAlertThresholdMax'] >= -100000) {
                    $limits['limit_high_warn'] = $entry['cmmTransTxPowerAlertThresholdMax'] * $scale;
                }
                if ($entry['cmmTransTxPowerAlertThresholdMin'] >= -100000) {
                    $limits['limit_low_warn'] = $entry['cmmTransTxPowerAlertThresholdMin'] * $scale;
                }
                if ($entry['cmmTransTxPowerCriticalThresholdMin'] >= -100000) {
                    $limits['limit_low'] = $entry['cmmTransTxPowerCriticalThresholdMin'] * $scale;
                }

                discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, $limits);
            }

            $descr    = $name . ' RX Power' . $name_ext;
            $oid_name = 'cmmTransRxPower';
            $oid_num  = '.1.3.6.1.4.1.36673.100.1.2.3.1.22.' . $index;
            $scale    = 0.001;
            //$type     = $mib . '-' . $oid_name;
            $value = $entry[$oid_name];

            if ($value >= -100000) { // '-100001' indicates unavailable
                $limits = $options;
                if ($entry['cmmTransRxPowerCriticalThresholdMax'] >= -100000) {
                    $limits['limit_high'] = $entry['cmmTransRxPowerCriticalThresholdMax'] * $scale;
                }
                if ($entry['cmmTransRxPowerAlertThresholdMax'] >= -100000) {
                    $limits['limit_high_warn'] = $entry['cmmTransRxPowerAlertThresholdMax'] * $scale;
                }
                if ($entry['cmmTransRxPowerAlertThresholdMin'] >= -100000) {
                    $limits['limit_low_warn'] = $entry['cmmTransRxPowerAlertThresholdMin'] * $scale;
                }
                if ($entry['cmmTransRxPowerCriticalThresholdMin'] >= -100000) {
                    $limits['limit_low'] = $entry['cmmTransRxPowerCriticalThresholdMin'] * $scale;
                }

                discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, $limits);
            }

        }
    }
}

// EOF
