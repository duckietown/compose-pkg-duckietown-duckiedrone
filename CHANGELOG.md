## 2.2.6 (July 16, 2026)
  - feat: implement multi-sensor Time-of-Flight renderer and update mission configuration
  - Merge pull request #13 from duckietown/copilot/dtsw-8068-add-stabilized-mode-and-dependent-assets
  - chore: address renderer review follow-ups
  - fix: trim renderer topic configuration in php
  - fix: resolve master merge conflicts
  - fix: add resize observer for control rendering and fix stale comments
  - refactor the Joystick UI for stabilized mode
  - add stabilized mode
  - fix(dashboard): clear legacy control canvas redraws
  - fix(dashboard): preserve legacy joystick controls
  - Initial plan
  - refactor the Joystick UI for stabilized mode
  - add stabilized mode

## 2.2.4 (July 10, 2026)
  - Merge pull request #11 from duckietown/DTSW-7986-Update-Dashboard
  - feat(dashboard): add Altitude and Motors PWM widgets to the dashboard

## 2.2.3 (July 02, 2026)
  - Merge pull request #10 from duckietown/DTSW-7986-Update-Dashboard
  - fix(dashboard): add missing semicolons in heartbeats monitor logic
  - fix(dashboard): update Duckiedrone control and heartbeat monitor for MAVROS integration

## 2.2.2 (May 22, 2026)
  - Added PX4 gyro and accelerometer calibration controls to the IMU dashboard block
  - Updated the default Duckiedrone mission to use the PX4 IMU calibration services
  - Removed the legacy yaw reset controls from the IMU block

## 2.2.1 (April 20, 2026)
  - fix(arming): unify button styling and switch to CSS-grid layout (ARM/DISARM | FLIGHT MODE | ACTIONS) so the stacked mode buttons no longer clip
  - fix(mission): bump Mavros_Arming block to rows:2 to accommodate the vertical LOITER/ALTITUDE/OFFBOARD stack

## 2.2.0 (April 20, 2026)
  - feat(arming): 3-way flight-mode selector (LOITER / ALTITUDE / OFFBOARD) replaces the OFFBOARD/ALTITUDE checkbox
  - fix(arming): suppress state-sync change events on page load (prevents spurious set_mode calls that pushed the drone out of LOITER)
  - fix(mission): use absolute `/mavros/*` paths in the default mission so widgets work under rosbridge's `~` namespace on virtual drones
  - docs: rewrite README with widget authoring guide, dev workflow, and known-issue notes

## 2.1.0 (January 22, 2026)
  - Merge pull request #4 from Tuxliri/update-arming-widget-DTSW-7558
  - feat(arming): complete widget refactor with improved UX
  - Update control topics and parameters for MAVROS integration; adjust frequency and add max roll/pitch setting
  - Update arming and mode services to use MAVROS endpoints; add takeoff and emergency kill buttons

## 2.0.3 (October 03, 2024)
  - Increased controll authority on roll and pitch
  - Fixed arming service for kill switch

## 2.0.1 (October 03, 2024)
  - Merge pull request #3 from duckietown:DTSW-6251-implement-force-disarm-in-dashboard
  - Implemented kill switch service
  - Implemented disarm on mavros

## 2.0.0 (September 17, 2024)
  - Added devcontainer configuration
  - Moved arming/mode widget to mavros

## 1.0.12 (April 26, 2024)


## 1.0.11 (aprile 26, 2024)
  - Updated imu display panel

## 1.0.10 (July 13, 2022)
  - added heartbeat override

## 1.0.9 (July 13, 2022)
  - fixed bug in default mission databases

## 1.0.8 (July 11, 2022)
  - added Calibrate IMU button service to default mission

## 1.0.7 (July 09, 2022)
  - updated default mission

## 1.0.6 (July 09, 2022)
  - updated duckiedrone default mission

## 1.0.5 (July 09, 2022)
  - integrated virtual joystick; added third "FLY" switch

## 1.0.4 (July 07, 2022)
  - fixed bug with default mission specs file

## 1.0.3 (July 07, 2022)
  - bug fix in UI block Duckiedrone_Arming.php

## 1.0.2 (July 06, 2022)
  - added drone missions

## 1.0.1 (July 06, 2022)
  - added more block renderers

## 1.0.0 (November 06, 2020)


## ----------------
