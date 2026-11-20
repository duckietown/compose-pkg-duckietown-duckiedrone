<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;

class Mavros_Arming extends BlockRenderer {

    static protected $ICON = [
        "class" => "fa",
        "name" => "key"
    ];

    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "arming_service" => [
            "name" => "Arming Service",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/cmd/arming"
        ],
        "kill_switch" => [
            "name" => "Kill Switch Service",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/cmd/command"
        ],
        "set_mode_service" => [
            "name" => "Set Mode Service",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/set_mode"
        ],
        "state_topic" => [
            "name" => "State Topic",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/state"
        ],
        "frequency" => [
            "name" => "Frequency (Hz)",
            "type" => "number",
            "default" => 10,
            "mandatory" => True
        ],
        "background_color" => [
            "name" => "Background color",
            "type" => "color",
            "mandatory" => False,
            "default" => ""
        ]
    ];

    protected static function render($id, &$args) {
        // Only apply an explicit custom color. Transparent / white / empty
        // must not override the shared white .mission-control-item card.
        $bg = trim((string) ($args['background_color'] ?? ''));
        $bg_l = strtolower($bg);
        if ($bg_l === '' || $bg_l === 'transparent' || $bg_l === '#fff' || $bg_l === '#ffffff' || $bg_l === 'white') {
            $bg = '';
        }
        ?>
        <link rel="stylesheet" href="<?php echo Core::getCSSstylesheetURL('drone_mission.css', 'duckietown_duckiedrone') ?>">
        <div class="drone-arm resizable">
            <div class="drone-arm-col">
                <div class="drone-arm-label">Arm / Disarm</div>
                <button type="button"
                        class="drone-arm-ctl drone-arm-arming"
                        id="drone_arming_toggle"
                        aria-pressed="false"
                        title="Arm or disarm the propellers">
                    DISARMED
                </button>
                <div id="arming_status_message" class="drone-arm-status"></div>
            </div>

            <div class="drone-arm-col">
                <div class="drone-arm-label">Flight mode</div>
                <div class="drone-arm-ctl drone-arm-modes" role="group" id="drone_mode_selector">
                    <button type="button" class="drone-arm-mode-btn" data-mode="STABILIZED"
                            title="PX4 STABILIZED — manual attitude control that self-levels; needs no GPS or altitude estimate. Use this for manual flight.">STABILIZED</button>
                    <!-- LOITER / ALTITUDE hidden for now (not in current LX scope); kept in
                         the DOM + JS wiring below so they can be re-enabled later. -->
                    <button type="button" class="drone-arm-mode-btn" data-mode="AUTO.LOITER" style="display: none"
                            title="PX4 AUTO.LOITER — position/altitude hold, safe armable default">LOITER</button>
                    <button type="button" class="drone-arm-mode-btn" data-mode="ALTCTL" style="display: none"
                            title="PX4 ALTCTL — manual stick with altitude hold">ALTITUDE</button>
                    <button type="button" class="drone-arm-mode-btn" data-mode="OFFBOARD"
                            title="PX4 OFFBOARD — external setpoints">OFFBOARD</button>
                </div>
                <div id="mode_status_message" class="drone-arm-status"></div>
            </div>

            <div class="drone-arm-col">
                <div class="drone-arm-label">Actions</div>
                <button type="button"
                        class="drone-arm-ctl drone-arm-kill"
                        id="drone_kill_switch_button"
                        title="Emergency Kill Switch — Force disarm immediately">
                    <i class="fa fa-bolt" aria-hidden="true"></i>
                    KILL
                </button>
                <div class="drone-arm-status" aria-hidden="true"></div>
            </div>
        </div>
        
        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        ?>

        <!-- Include ROS -->
        <script src="<?php echo Core::getJSscriptURL('rosdb.js', 'ros') ?>"></script>

        <script type="text/javascript">
            let _MODE_STABILIZED = 'STABILIZED';
            let _MODE_LOITER = 'AUTO.LOITER';
            let _MODE_ALTITUDE = 'ALTCTL';
            let _MODE_OFFBOARD = 'OFFBOARD';
            let _SELECTABLE_MODES = [_MODE_STABILIZED, _MODE_LOITER, _MODE_ALTITUDE, _MODE_OFFBOARD];

            // Track states
            let isArmed = false;
            let currentMode = null;   // unknown until first /mavros/state message
            let _syncing = false;     // true while programmatically updating toggles — suppress change handlers

            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                let arming_srv = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name : '<?php echo $args['arming_service'] ?>',
                    serviceType : 'mavros_msgs/CommandBool'
                });

                let kill_switch_srv = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name : '<?php echo $args['kill_switch'] ?>',
                    serviceType : 'mavros/CommandLong'
                });

                let set_mode_srv = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name : '<?php echo $args['set_mode_service'] ?>',
                    serviceType : 'mavros_msgs/SetMode'
                });

                function set_arming(arm, callback) {
                    console.log("Setting arming to:", arm);
                    let request = new ROSLIB.ServiceRequest({value: arm});
                    arming_srv.callService(request, function(response) {
                        console.log("Arming service response:", response);
                        if (callback) callback(response);
                    }, function(error) {
                        console.error("Arming service error:", error);
                        if (callback) callback({success: false});
                    });
                }

                function set_mode(mode, callback) {
                    let request = new ROSLIB.ServiceRequest({
                        base_mode: 0,
                        custom_mode: mode
                    });
                    set_mode_srv.callService(request, function(response) {
                        console.log("Mode change result: ", response.mode_sent);
                        if (callback) callback(response);
                    });
                }

                function set_arming_ui(armed) {
                    let btn = $('#<?php echo $id ?> #drone_arming_toggle');
                    btn.toggleClass('is-armed', !!armed)
                       .attr('aria-pressed', armed ? 'true' : 'false')
                       .text(armed ? 'ARMED' : 'DISARMED');
                }

                function set_kill_ui(html) {
                    $('#<?php echo $id ?> #drone_kill_switch_button').html(html);
                }

                function emergency_kill() {
                    console.log("EMERGENCY KILL SWITCH ACTIVATED!");
                    
                    let kill_btn = $('#<?php echo $id ?> #drone_kill_switch_button');
                    kill_btn.prop('disabled', true);
                    set_kill_ui('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>KILLING...');
                    
                    // Force disarm using kill switch command
                    let request = new ROSLIB.ServiceRequest({
                        broadcast: false,
                        command: 400, // MAV_CMD_COMPONENT_ARM_DISARM
                        confirmation: 0,
                        param1: 0.0, // Disarm
                        param2: 21196.0, // Force disarm magic number
                        param3: 0.0,
                        param4: 0.0,
                        param5: 0.0,
                        param6: 0.0,
                        param7: 0.0
                    });
                    
                    kill_switch_srv.callService(request, function(response) {
                        console.log("Kill switch response:", response);
                        
                        if (response.success) {
                            console.log("Emergency kill successful!");
                            isArmed = false;
                            set_arming_ui(false);

                            setTimeout(function() {
                                kill_btn.prop('disabled', false);
                                set_kill_ui('<i class="fa fa-bolt" aria-hidden="true"></i>KILL');
                            }, 1000);
                        } else {
                            console.error("Kill switch failed! Result:", response.result);
                            set_kill_ui('<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>FAILED');
                            
                            setTimeout(function() {
                                kill_btn.prop('disabled', false);
                                set_kill_ui('<i class="fa fa-bolt" aria-hidden="true"></i>KILL');
                            }, 2000);
                        }
                    }, function(error) {
                        console.error("Kill switch service call error:", error);
                        set_kill_ui('<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>ERROR');
                        
                        setTimeout(function() {
                            kill_btn.prop('disabled', false);
                            set_kill_ui('<i class="fa fa-bolt" aria-hidden="true"></i>KILL');
                        }, 2000);
                    });
                }

                function showDashboardPopup(message) {
                    let styles = getComputedStyle(document.documentElement);
                    let popup = $('<div>')
                        .css({
                            'position': 'fixed',
                            'top': '20px',
                            'left': '50%',
                            'transform': 'translateX(-50%)',
                            'background-color': styles.getPropertyValue('--r-bad-bg').trim() || '#fef2f2',
                            'color': styles.getPropertyValue('--r-bad').trim() || '#b91c1c',
                            'border': '1px solid ' + (styles.getPropertyValue('--r-bad-border').trim() || '#fecaca'),
                            'border-radius': '8px',
                            'padding': '15px 20px',
                            'box-shadow': styles.getPropertyValue('--r-shadow').trim() || '0 1px 2px rgba(0,0,0,0.1)',
                            'z-index': '10000',
                            'max-width': '500px',
                            'opacity': '0',
                            'transition': 'opacity 0.3s ease-in-out',
                            'font-size': '14px',
                            'line-height': '1.5'
                        })
                        .html(message);
                    
                    // Append to body
                    $('body').append(popup);
                    
                    // Fade in
                    setTimeout(function() {
                        popup.css('opacity', '1');
                    }, 10);
                    
                    // Fade out and remove after 10 seconds
                    setTimeout(function() {
                        popup.css('opacity', '0');
                        setTimeout(function() {
                            popup.remove();
                        }, 300);
                    }, 10000);
                }

                $('#<?php echo $id ?> #drone_arming_toggle').off().click(function() {
                    if (_syncing) return;
                    let checked = !isArmed;
                    console.log("Arming toggle changed. Setting armed to:", checked);
                    
                    // Clear previous status message
                    $('#<?php echo $id ?> #arming_status_message').text('');
                    set_arming_ui(checked);
                    
                    set_arming(checked, function(response) {
                        if (response.success) {
                            isArmed = checked;
                            console.log("Arming state changed successfully to:", checked);
                            // Clear any error message on success
                            $('#<?php echo $id ?> #arming_status_message').text('');
                        } else {
                            console.error("Failed to change arming state. Result:", response.result);
                            
                            // Show error message to user
                            let errorMsg = "Failed to " + (checked ? "arm" : "disarm");
                            if (response.result) {
                                // Map common error codes to user-friendly messages
                                const errorMessages = {
                                    1: "Command temporarily rejected",
                                    4: "Command denied",
                                    5: "Pre-flight checks failed",
                                    6: "Already in requested state",
                                    7: "Command not supported"
                                };
                                errorMsg += ": " + (errorMessages[response.result] || "Error code " + response.result);
                            }
                            $('#<?php echo $id ?> #arming_status_message').text(errorMsg);
                            
                            // Show dashboard popup for both arming and disarming failures
                            if (checked) {
                                // Arming failed
                                showDashboardPopup(
                                    '<strong>Arming failed.</strong><br><br>' +
                                    errorMsg + '<br><br>' +
                                    'Common reasons:<br>' +
                                    '• The throttle is not at 0. Press Space to reset it.<br>' +
                                    '• PX4 is not receiving the Remote Control commands. Keep this tab in the foreground and check that the JOYSTICK heart is green.<br>' +
                                    '• No flight mode is active. Click STABILIZED and wait for it to highlight.<br>' +
                                    '• OFFBOARD is selected but no controller is running.<br>' +
                                    '• The flight stack is still starting. Wait until the widgets show data.<br>' +
                                    '• The Duckiedrone is not level, or it moved while arming.<br><br>' +
                                    'If arming still fails, see the Troubleshooting section of "Flying the Duckiedrone" in the manual.'
                                );
                            } else {
                                // Disarming failed
                                showDashboardPopup(
                                    '<strong>Disarming failed.</strong><br><br>' +
                                    'If you are flying in altitude mode you must first land by bringing the throttle down.<br><br>' +
                                    'If you want to force disarm use the Kill button. <strong>Warning</strong>: this will instantly turn off the motors and crash the drone!'
                                );
                            }
                            
                            // Auto-clear error message after 10 seconds
                            setTimeout(function() {
                                $('#<?php echo $id ?> #arming_status_message').text('');
                            }, 10000);
                            
                            // Revert UI on failure
                            set_arming_ui(isArmed);
                        }
                    });
                });

                // Helper: visually mark the selected mode button without firing its click handler.
                function highlight_mode_button(mode) {
                    let grp = $('#<?php echo $id ?> #drone_mode_selector');
                    grp.find('button').removeClass('is-active');
                    if (mode === null) return;
                    let btn = grp.find('button[data-mode="' + mode + '"]');
                    if (btn.length) {
                        btn.addClass('is-active');
                    }
                }

                $('#<?php echo $id ?> #drone_mode_selector button').off().click(function() {
                    if (_syncing) return;
                    let mode = $(this).data('mode');
                    if (!mode || mode === currentMode) return;
                    console.log("Mode button clicked. Requesting mode:", mode);
                    $('#<?php echo $id ?> #mode_status_message').text('');
                    set_mode(mode, function(response) {
                        if (response.mode_sent) {
                            // Do not update currentMode here — let the state topic confirm.
                            console.log("Mode change request accepted by MAVROS:", mode);
                        } else {
                            console.error("Failed to set mode to:", mode);
                            $('#<?php echo $id ?> #mode_status_message').text(
                                'Mode change rejected (MAVROS did not forward to PX4).');
                        }
                    });
                });

                $('#<?php echo $id ?> #drone_kill_switch_button').off().click(function() {
                    console.log("Kill switch button clicked");
                    emergency_kill();
                });

                // Subscribe to the State topic
                (new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args["state_topic"] ?>',
                    messageType: 'mavros_msgs/State',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['frequency'] ?>
                })).subscribe(function (message) {
                    // Sync toggles from PX4/MAVROS state — programmatic updates only,
                    // suppress the change/click handlers so they don't fire a redundant
                    // set_arming / set_mode call back to the drone (which used to push the
                    // drone into ALTCTL on page load).
                    _syncing = true;
                    try {
                        if (message.armed !== isArmed) {
                            isArmed = message.armed;
                            set_arming_ui(message.armed);
                        }
                        if (message.mode !== currentMode) {
                            currentMode = message.mode;
                            // Only highlight the button if the reported mode is one of the
                            // selectable modes (_SELECTABLE_MODES); otherwise clear selection
                            // (e.g. AUTO.TAKEOFF, AUTO.LAND, MANUAL, etc. are transient and not
                            // user-selectable here).
                            let shown = _SELECTABLE_MODES.indexOf(message.mode) >= 0 ? message.mode : null;
                            highlight_mode_button(shown);
                        }
                    } finally {
                        _syncing = false;
                    }
                });
            });
        </script>

        
        <?php
        ROS::connect($ros_hostname);
        ?>

        <?php if ($bg !== '') { ?>
        <style type="text/css">
            #<?php echo $id ?>{
                background-color: <?php echo htmlspecialchars($bg) ?>;
            }
        </style>
        <?php } ?>
        <?php
    }
}
?>
