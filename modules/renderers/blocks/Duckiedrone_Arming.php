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
            "default" => "~/mavros/cmd/arming"
        ],
        "kill_switch" => [
            "name" => "Kill Switch Service",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/mavros/cmd/command"
        ],
        "set_mode_service" => [
            "name" => "Set Mode Service",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/mavros/set_mode"
        ],
        "state_topic" => [
            "name" => "State Topic",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/mavros/state"
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
        <table class="resizable" style="height: 100%">
            <tr style="font-size: 18pt;">
                <td class="col-md-3 text-right" style="padding-right: 10px">
                    <input type="checkbox"
                           data-toggle="toggle"
                           data-on="ARM"
                           data-onstyle="primary"
                           data-off="DISARM"
                           data-offstyle="warning"
                           data-class="fast"
                           data-size="small"
                           name="drone_arming_toggle"
                           id="drone_arming_toggle">
                </td>
                <td class="col-md-3 text-left" style="padding-left: 10px; padding-right: 10px">
                    <button type="button" 
                            class="btn btn-success btn-sm" 
                            id="drone_takeoff_button"
                            style="font-size: 12pt; padding: 6px 12px;">
                        <i class="fa fa-plane" style="margin-right: 5px;"></i>
                        TAKEOFF
                    </button>
                </td>
                <td class="col-md-2 text-left" style="padding-left: 10px">
                    <button type="button" 
                            class="btn btn-danger btn-sm" 
                            id="drone_kill_switch_button"
                            title="Emergency Kill Switch - Force disarm immediately"
                            style="font-size: 12pt; padding: 6px 12px;">
                        <i class="fa fa-bolt" style="margin-right: 5px;"></i>
                        KILL
                    </button>
                </td>
            </tr>
        </table>
        
        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        ?>

        <!-- Include ROS -->
        <script src="<?php echo Core::getJSscriptURL('rosdb.js', 'ros') ?>"></script>

        <script type="text/javascript">
            let _MODE_STABILIZE = 'STABILIZE';
            let _MODE_OFFBOARD = 'OFFBOARD';
            let _MODE_AUTO_TAKEOFF = 'AUTO.TAKEOFF';
            let _MODE_AUTO_LAND = 'AUTO.LAND';
            
            // Track the success of the arming service call
            let armingSuccess = false;
            // Track if drone is in flight
            let isFlying = false;

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

                function set_arming(arm) {
                    console.log("ros service hostname:", arming_srv.ros.hostname);
                    console.log("Calling arming service: ", arming_srv.name);
                    console.log("Setting arming to: ", arm);
                                        
                    if (arm) {
                        // Call the arming service
                        let request = new ROSLIB.ServiceRequest({value: arm}); // `arm` is the desired boolean value
                        arming_srv.callService(request, function(response) {
                            console.log("Arming result: ", response.success);
                            armingSuccess = response.success;
                        });
                    } else {
                        // Call the kill switch service TODO: fix this so that it ALWAYS works
                        let request = new ROSLIB.ServiceRequest({
                            broadcast: false,
                            command: 400, // MAV_CMD_COMPONENT_ARM_DISARM
                            confirmation: 0,
                            param1: 0.0, // Disarm
                            param2: 21196.0,
                            param3: 0.0,
                            param4: 0.0,
                            param5: 0.0,
                            param6: 0.0,
                            param7: 0.0
                        });
                        kill_switch_srv.callService(request, function(response) {
                            console.log("Kill switch result: ", response.success);
                            armingSuccess = response.success;
                        });
                    }

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
                                        armingSuccess = true;
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
                            armingSuccess = false;
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

                $('#<?php echo $id ?> #drone_arming_toggle').off().change(function() {
                    let checked = $(this).prop('checked');
                    console.log("Arming toggle changed. Checked: ", checked);
                    set_arming(checked);  // Trigger the arming service call
                });

                $('#<?php echo $id ?> #drone_takeoff_button').off().click(function() {
                    if (isFlying) {
                        console.log("Land button clicked");
                        initiate_landing();
                    } else {
                        console.log("Takeoff button clicked");
                        initiate_takeoff();
                    }
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
                    console.log("State topic message received. Armed:", message.armed);
                    
                    // Only update the toggle if both the service call was successful and the armed state is true
                    if (armingSuccess && message.armed) {
                        $('#<?php echo $id ?> #drone_arming_toggle').bootstrapToggle('on');
                    } else if (!armingSuccess || !message.armed) {
                        $('#<?php echo $id ?> #drone_arming_toggle').bootstrapToggle('off');
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
