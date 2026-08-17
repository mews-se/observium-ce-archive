-- Fix polling time column sizes in devices table
-- Original: double(5,2) max = 999.99s (~16 minutes)
-- New: double(8,2) max = 999999.99s (~11 days)
-- This accommodates devices with long polling times (large switches, many modules, etc.)

ALTER TABLE `devices`
  MODIFY `last_polled_timetaken` double(8,2) DEFAULT NULL,
  MODIFY `last_discovered_timetaken` double(8,2) DEFAULT NULL;
