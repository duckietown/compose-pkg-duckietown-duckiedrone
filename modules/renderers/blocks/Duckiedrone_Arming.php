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
            "mandatory" => True,
            "default" => "#fff"
        ]
    ];

    protected static function render($id, &$args) {
        ?>
        <div style="display: flex; height: 100%; width: 100%; align-items: center; justify-content: space-around; padding: 5px; box-sizing: border-box;">
            <!-- ARM/DISARM Toggle (1/3) -->
            <div style="flex: 1; text-align: center;">
                <div style="margin-bottom: 3px; font-size: 9pt; font-weight: bold;">ARM / DISARM</div>
                <input type="checkbox"
                       data-toggle="toggle"
                       data-on="ARMED"
                       data-onstyle="success"
                       data-off="DISARMED"
                       data-offstyle="warning"
                       data-class="fast"
                       data-size="small"
                       name="drone_arming_toggle"
                       id="drone_arming_toggle">
                <div id="arming_status_message" style="margin-top: 3px; font-size: 8pt; color: #d9534f; min-height: 12px;"></div>
            </div>
            
            <!-- FLIGHT MODE 3-way selector (1/3) -->
            <div style="flex: 1; text-align: center;">
                <div style="margin-bottom: 3px; font-size: 9pt; font-weight: bold;">FLIGHT MODE</div>
                <div class="btn-group btn-group-xs" role="group" id="drone_mode_selector"
                     style="display: inline-flex;">
                    <button type="button" class="btn btn-default" data-mode="AUTO.LOITER"
                            title="PX4 AUTO.LOITER — position/altitude hold, safe armable default">LOITER</button>
                    <button type="button" class="btn btn-default" data-mode="ALTCTL"
                            title="PX4 ALTCTL — manual stick with altitude hold">ALTITUDE</button>
                    <button type="button" class="btn btn-default" data-mode="OFFBOARD"
                            title="PX4 OFFBOARD — external setpoints">OFFBOARD</button>
                </div>
                <div id="mode_status_message" style="margin-top: 3px; font-size: 8pt; color: #d9534f; min-height: 12px;"></div>
            </div>
            
            <!-- Stacked Buttons (1/3) -->
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px;">
                <button type="button" 
                        class="btn btn-success btn-xs" 
                        id="drone_takeoff_button"
                        disabled
                        title="Takeoff disabled - use manual altitude control"
                        style="font-size: 9pt; padding: 3px 6px; width: 90px; opacity: 0.5;">
                    <i class="fa fa-plane" style="margin-right: 3px;"></i>
                    TAKEOFF
                </button>
                <button type="button" 
                        class="btn btn-danger btn-xs" 
                        id="drone_kill_switch_button"
                        title="Emergency Kill Switch - Force disarm immediately"
                        style="font-size: 9pt; padding: 3px 6px; width: 90px;">
                    <i class="fa fa-bolt" style="margin-right: 3px;"></i>
                    KILL
                </button>
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
            let _MODE_LOITER = 'AUTO.LOITER';
            let _MODE_ALTITUDE = 'ALTCTL';
            let _MODE_OFFBOARD = 'OFFBOARD';
            let _MODE_AUTO_TAKEOFF = 'AUTO.TAKEOFF';
            let _MODE_AUTO_LAND = 'AUTO.LAND';
            let _SELECTABLE_MODES = [_MODE_LOITER, _MODE_ALTITUDE, _MODE_OFFBOARD];

            // Track states
            let isArmed = false;
            let isFlying = false;
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

                function initiate_takeoff() {
                    console.log("Initiating takeoff sequence...");
                    
                    // Disable the takeoff button during the process
                    let takeoff_btn = $('#<?php echo $id ?> #drone_takeoff_button');
                    takeoff_btn.prop('disabled', true);
                    takeoff_btn.html('<i class="fa fa-spinner fa-spin" style="margin-right: 5px;"></i>TAKING OFF...');
                    
                    // Step 1: Set mode to AUTO.TAKEOFF
                    set_mode(_MODE_AUTO_TAKEOFF, function(mode_response) {
                        console.log("Set mode response:", mode_response);
                        if (mode_response.mode_sent) {
                            console.log("Mode set to AUTO.TAKEOFF successfully");
                            
                            // Step 2: Arm the drone to initiate takeoff
                            // Increased delay to allow flight controller to process mode change
                            setTimeout(function() {
                                console.log("Calling arming service...");
                                let request = new ROSLIB.ServiceRequest({value: true});
                                arming_srv.callService(request, function(arm_response) {
                                    console.log("Arming for takeoff response:", arm_response);
                                    console.log("Arming success:", arm_response.success);
                                    console.log("Arming result code:", arm_response.result);
                                    
                                    if (arm_response.success) {
                                        console.log("Takeoff initiated successfully!");
                                        isArmed = true;
                                        isFlying = true;
                                        
                                        // Change button to LAND mode after 3 seconds
                                        setTimeout(function() {
                                            takeoff_btn.prop('disabled', false);
                                            takeoff_btn.removeClass('btn-success').addClass('btn-warning');
                                            takeoff_btn.html('<i class="fa fa-chevron-down" style="margin-right: 5px;"></i>LAND');
                                        }, 3000);
                                    } else {
                                        console.error("Failed to arm for takeoff. Result code:", arm_response.result);
                                        takeoff_btn.prop('disabled', false);
                                        takeoff_btn.html('<i class="fa fa-exclamation-triangle" style="margin-right: 5px;"></i>FAILED');
                                        
                                        // Reset button text after 2 seconds
                                        setTimeout(function() {
                                            takeoff_btn.html('<i class="fa fa-plane" style="margin-right: 5px;"></i>TAKEOFF');
                                        }, 2000);
                                    }
                                }, function(error) {
                                    console.error("Arming service call error:", error);
                                    takeoff_btn.prop('disabled', false);
                                    takeoff_btn.html('<i class="fa fa-exclamation-triangle" style="margin-right: 5px;"></i>ERROR');
                                    setTimeout(function() {
                                        takeoff_btn.html('<i class="fa fa-plane" style="margin-right: 5px;"></i>TAKEOFF');
                                    }, 2000);
                                });
                            }, 1500); // Increased delay from 500ms to 1500ms
                        } else {
                            console.error("Failed to set mode to AUTO.TAKEOFF");
                            takeoff_btn.prop('disabled', false);
                            takeoff_btn.html('<i class="fa fa-exclamation-triangle" style="margin-right: 5px;"></i>FAILED');
                            
                            // Reset button text after 2 seconds
                            setTimeout(function() {
                                takeoff_btn.html('<i class="fa fa-plane" style="margin-right: 5px;"></i>TAKEOFF');
                            }, 2000);
                        }
                    });
                }

                function initiate_landing() {
                    console.log("Initiating landing sequence...");
                    
                    // Disable the land button during the process
                    let land_btn = $('#<?php echo $id ?> #drone_takeoff_button');
                    land_btn.prop('disabled', true);
                    land_btn.html('<i class="fa fa-spinner fa-spin" style="margin-right: 5px;"></i>LANDING...');
                    
                    // Set mode to AUTO.LAND
                    set_mode(_MODE_AUTO_LAND, function(mode_response) {
                        if (mode_response.mode_sent) {
                            console.log("Mode set to AUTO.LAND successfully");
                            isFlying = false;
                            
                            // Change button back to TAKEOFF mode after 2 seconds
                            setTimeout(function() {
                                land_btn.prop('disabled', false);
                                land_btn.removeClass('btn-warning').addClass('btn-success');
                                land_btn.html('<i class="fa fa-plane" style="margin-right: 5px;"></i>TAKEOFF');
                            }, 2000);
                        } else {
                            console.error("Failed to set mode to AUTO.LAND");
                            land_btn.prop('disabled', false);
                            land_btn.html('<i class="fa fa-exclamation-triangle" style="margin-right: 5px;"></i>FAILED');
                            
                            // Reset button text after 2 seconds
                            setTimeout(function() {
                                land_btn.removeClass('btn-success').addClass('btn-warning');
                                land_btn.html('<i class="fa fa-chevron-down" style="margin-right: 5px;"></i>LAND');
                            }, 2000);
                        }
                    });
                }

                function emergency_kill() {
                    console.log("EMERGENCY KILL SWITCH ACTIVATED!");
                    
                    let kill_btn = $('#<?php echo $id ?> #drone_kill_switch_button');
                    kill_btn.prop('disabled', true);
                    kill_btn.html('<i class="fa fa-spinner fa-spin" style="margin-right: 5px;"></i>KILLING...');
                    
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
                            isFlying = false;
                            
                            // Reset states
                            $('#<?php echo $id ?> #drone_arming_toggle').bootstrapToggle('off');
                            let takeoff_btn = $('#<?php echo $id ?> #drone_takeoff_button');
                            takeoff_btn.removeClass('btn-warning').addClass('btn-success');
                            takeoff_btn.html('<i class="fa fa-plane" style="margin-right: 5px;"></i>TAKEOFF');
                            
                            // Re-enable kill button
                            setTimeout(function() {
                                kill_btn.prop('disabled', false);
                                kill_btn.html('<i class="fa fa-bolt" style="margin-right: 5px;"></i>KILL');
                            }, 1000);
                        } else {
                            console.error("Kill switch failed! Result:", response.result);
                            kill_btn.html('<i class="fa fa-exclamation-triangle" style="margin-right: 5px;"></i>FAILED');
                            
                            setTimeout(function() {
                                kill_btn.prop('disabled', false);
                                kill_btn.html('<i class="fa fa-bolt" style="margin-right: 5px;"></i>KILL');
                            }, 2000);
                        }
                    }, function(error) {
                        console.error("Kill switch service call error:", error);
                        kill_btn.html('<i class="fa fa-exclamation-triangle" style="margin-right: 5px;"></i>ERROR');
                        
                        setTimeout(function() {
                            kill_btn.prop('disabled', false);
                            kill_btn.html('<i class="fa fa-bolt" style="margin-right: 5px;"></i>KILL');
                        }, 2000);
                    });
                }

                function showDashboardPopup(message) {
                    // Create popup element
                    let popup = $('<div>')
                        .css({
                            'position': 'fixed',
                            'top': '20px',
                            'left': '50%',
                            'transform': 'translateX(-50%)',
                            'background-color': '#f8d7da',
                            'color': '#721c24',
                            'border': '1px solid #f5c6cb',
                            'border-radius': '4px',
                            'padding': '15px 20px',
                            'box-shadow': '0 4px 6px rgba(0,0,0,0.1)',
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

                $('#<?php echo $id ?> #drone_arming_toggle').off().change(function() {
                    if (_syncing) return;
                    let checked = $(this).prop('checked');
                    console.log("Arming toggle changed. Setting armed to:", checked);
                    
                    // Clear previous status message
                    $('#<?php echo $id ?> #arming_status_message').text('');
                    
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
                                    '• Pre-flight checks not passed (GPS, sensors, etc.)<br>' +
                                    '• Vehicle not in a valid mode for arming<br>' +
                                    '• Safety checks preventing arming<br><br>' +
                                    'Check the logs of the dt-px4 and mavros containers for more details.'
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
                            
                            // Revert toggle on failure
                            $('#<?php echo $id ?> #drone_arming_toggle').bootstrapToggle(checked ? 'off' : 'on');
                        }
                    });
                });

                // Helper: visually mark the selected mode button without firing its click handler.
                function highlight_mode_button(mode) {
                    let grp = $('#<?php echo $id ?> #drone_mode_selector');
                    grp.find('button').removeClass('btn-primary btn-info active')
                                      .addClass('btn-default');
                    if (mode === null) return;
                    let btn = grp.find('button[data-mode="' + mode + '"]');
                    if (btn.length) {
                        btn.removeClass('btn-default')
                           .addClass(mode === _MODE_OFFBOARD ? 'btn-primary' : 'btn-info')
                           .addClass('active');
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

                // Takeoff button disabled - commented out
                // $('#<?php echo $id ?> #drone_takeoff_button').off().click(function() {
                //     if (isFlying) {
                //         console.log("Land button clicked");
                //         initiate_landing();
                //     } else {
                //         console.log("Takeoff button clicked");
                //         initiate_takeoff();
                //     }
                // });

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
                            $('#<?php echo $id ?> #drone_arming_toggle')
                                .bootstrapToggle(message.armed ? 'on' : 'off');
                            isArmed = message.armed;
                        }
                        if (message.mode !== currentMode) {
                            currentMode = message.mode;
                            // Only highlight the button if the reported mode is one of the
                            // three selectable modes; otherwise clear selection (e.g. AUTO.TAKEOFF,
                            // AUTO.LAND, MANUAL, etc. are transient and not user-selectable here).
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

        <style type="text/css">
            #<?php echo $id ?>{
                background-color: <?php echo $args['background_color'] ?>;
            }
        </style>
        <?php
    }
}
?>
