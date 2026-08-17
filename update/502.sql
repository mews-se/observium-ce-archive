CREATE INDEX vlan_mac_lookup ON vlans_fdb (vlan_id, deleted, mac_address, device_id);
