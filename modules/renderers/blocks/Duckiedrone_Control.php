<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class Duckiedrone_Control extends BlockRenderer {
    
    static protected $ICON = [
        "class" => "fa",
        "name" => "gamepad"
    ];
    
    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "service_set_mode" => [
            "name" => "ROS Service (Set mode)",
            "type" => "text",
            "mandatory" => True
        ],
        "service_override_commands" => [
            "name" => "ROS Service (Set commands override)",
            "type" => "text",
            "mandatory" => True
        ],
        "param_override_prefix" => [
            "name" => "ROS Param Prefix (Command override)",
            "type" => "text",
            "mandatory" => True
        ],
        "topic_mode_current" => [
            "name" => "ROS Topic (Read mode)",
            "type" => "text",
            "mandatory" => True
        ],
        "topic_control" => [
            "name" => "ROS Topic (Joystick control)",
            "type" => "text",
            "mandatory" => True
        ],
        "topic_commands" => [
            "name" => "ROS Topic (Read commands)",
            "type" => "text",
            "mandatory" => True
        ],
        "frequency" => [
            "name" => "Frequency (Hz)",
            "type" => "number",
            "default" => 10,
            "mandatory" => True
        ],
        "max_roll_pitch" => [
            "name" => "Max Roll/Pitch (D-pad)",
            "type" => "numeric",
            "mandatory" => False,
            "default" => 300
        ],
        "min_value" => [
            "name" => "Minimum value",
            "type" => "numeric",
            "mandatory" => True,
            "default" => 1000
        ],
        "max_value" => [
            "name" => "Maximum value",
            "type" => "numeric",
            "mandatory" => True,
            "default" => 2000
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
            <tr style="height: 20px; font-weight: bold">
                <td class="col-md-1">
                    Channel
                </td>
                <td class="col-md-1 text-center">
                    Override
                </td>
                <td class="col-md-6 text-left">
                    Intensity
                </td>
                <td rowspan="5" class="col-md-2 text-center" style="padding: 0">
                    <canvas id="drone_control_commands_joy_keys" width="150px" height="150px"></canvas>
                </td>
                <td rowspan="5" class="col-md-2 text-center" style="padding: 0">
                    <div id="drone_control_commands_joy_stick" style="width:160px;height:160px;margin:0;"></div>
                    <div id="drone_control_commands_gamepad_status" style="font-size: 11px; margin-top: 6px; color: #8a8a8a;">
                        Controller: not detected
                    </div>
                </td>
            </tr>
            <?php
            $bars = [
                [
                    "id" => "roll",
                    "label" => "Roll"
                ],
                [
                    "id" => "pitch",
                    "label" => "Pitch"
                ],
                [
                    "id" => "yaw",
                    "label" => "Yaw"
                ],
                [
                    "id" => "throttle",
                    "label" => "Throttle"
                ],
            ];
            
            foreach ($bars as &$bar) {
                ?>
                <tr style="height: 20px">
                    <td class="col-md-1" style="text-align: right">
                        <p class=text-right" style="margin: 0"><?php echo $bar["label"] ?></p>
                    </td>
                    <td class="col-md-1">
                        <input type="checkbox"
                               data-toggle="toggle"
                               data-onstyle="primary"
                               data-offstyle="warning"
                               data-class="fast"
                               data-size="mini"
                               name="drone_control_commands_override_<?php echo $bar["id"] ?>"
                               id="drone_control_commands_override_<?php echo $bar["id"] ?>">
                    </td>
                    <td class="col-md-6 text-left">
                        <div class="progress" style="margin: 0; height: 16px">
                            <div class="progress-bar progress-bar-primary" role="progressbar"
                                 id="drone_control_commands_bar_<?php echo $bar["id"] ?>"
                                 aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                                 style="width: 0">
                                <span class="sr-only"></span>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>

        <!-- Include ROS Configuration Discovery -->
        <script src="<?php echo Core::getJSscriptURL('ros-config.js', 'duckietown_duckiedrone') ?>"></script>
        <!-- Include ROS -->
        <script src="<?php echo Core::getJSscriptURL('rosdb.js', 'ros') ?>"></script>
        <!-- Include Joy library -->
        <script src="<?php echo Core::getJSscriptURL('joy.js', 'duckietown_duckiedrone') ?>"></script>

        <script type="text/javascript">
            const CONST_YAW_DELTA = 90;
            const CONST_ROLL_DELTA = 90;
            const CONST_PITCH_DELTA = 90;
            const CONST_MID_VAL = 1500;
            
            const CONST_JOY_YAW_DEADBAND = 20;
            const CONST_GAMEPAD_AXIS_DEADBAND = 0.12;
            const CONST_GAMEPAD_TRIGGER_DEADBAND = 0.05;
            const CONST_MAX_ROLL_PITCH = <?php echo $args['max_roll_pitch'] ?? self::$ARGUMENTS['max_roll_pitch']['default'] ?>;
            
            function drawArrow(ctx, fromx, fromy, tox, toy, arrowWidth, color) {
                //variables to be used when creating the arrow
                var headlen = 10;
                var angle = Math.atan2(toy - fromy, tox - fromx);
                
                ctx.save();
                ctx.strokeStyle = color;
                
                //starting path of the arrow from the start square to the end square and drawing the stroke
                ctx.beginPath();
                ctx.moveTo(fromx, fromy);
                ctx.lineTo(tox, toy);
                ctx.lineWidth = arrowWidth;
                ctx.stroke();
                
                //starting a new path from the head of the arrow to one of the sides of the point
                ctx.beginPath();
                ctx.moveTo(tox, toy);
                ctx.lineTo(
                    tox - headlen * Math.cos(angle - Math.PI / 7),
                    toy - headlen * Math.sin(angle - Math.PI / 7)
                );
                
                //path from the side point of the arrow, to the other side point
                ctx.lineTo(
                    tox - headlen * Math.cos(angle + Math.PI / 7),
                    toy - headlen * Math.sin(angle + Math.PI / 7)
                );
                
                //path from the side point back to the tip of the arrow, and then again to the opposite side point
                ctx.lineTo(tox, toy);
                ctx.lineTo(
                    tox - headlen * Math.cos(angle - Math.PI / 7),
                    toy - headlen * Math.sin(angle - Math.PI / 7)
                );
                
                //draws the paths created above
                ctx.stroke();
                ctx.restore();
            }
      
            // data types
            class JoyAxes {
                constructor(left_right, front_back, cw_ccw, up_down) {
                    this.throttle = up_down;
                    this.roll = left_right;
                    this.pitch = front_back;
                    this.yaw = cw_ccw;
                }
            
                get manualControlMsg() {
                    return {
                        x: this.roll,      // int16 [-1000, 1000]
                        y: this.pitch,     // int16 [-1000, 1000]
                        z: this.throttle,  // int16 [-1000, 1000]
                        r: this.yaw,       // int16 [-1000, 1000]
                        buttons: 0         // uint16
                    };
                }
            }
            
            class JoyButtons {
                constructor(arm, disarm, takeoff, land) {
                    this.arm = arm;
                    this.disarm = disarm;
                    this.takeoff = takeoff;
                    this.land = land;
                }
            
                get btnArr() {
                    return [this.arm, this.disarm, this.takeoff, this.land]
                }
            }
            
            class JoyXY {
                constructor(x, y) {
                    this.x = x;
                    this.y = y;
                }
            }
      
            // Initialize ROS configuration discovery and set up widget
            (async function initializeWidget() {
                try {
                    // Discover ROS configuration at runtime (works with proxies!)
                    let config = await ROSConfig.init();
                    let ros_hostname = config.vehicle_name;
                    
                    console.log('[Duckiedrone_Control] Using discovered robot:', ros_hostname);
                    
                    // Ensure ROSbridge connection exists for this hostname
                    if (!window.ros || !window.ros[ros_hostname]) {
                        console.warn('[Duckiedrone_Control] Waiting for ROSbridge connection...');
                        // Wait a bit for rosdb.js to establish connection
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                    
                    // If still no connection, try to initialize one
                    if (!window.ros || !window.ros[ros_hostname]) {
                        console.log('[Duckiedrone_Control] Creating ROS connection to', config.rosbridge_url);
                        window.ros = window.ros || {};
                        window.ros[ros_hostname] = new ROSLIB.Ros({
                            url: config.rosbridge_url
                        });
                    }
                    
                    // TODO: this is the right way to do it
                    let set_override_srv = new ROSLIB.Service({
                        ros: window.ros[ros_hostname],
                        name : '<?php echo $args['service_override_commands'] ?>',
                        messageType : 'duckietown_msgs/SetDroneCommandsOverride'
                    });
                    
                    let roll_override = new ROSLIB.Param({
                        ros: window.ros[ros_hostname],
                        name: '<?php echo $args['param_override_prefix'] ?>roll_override',
                    });
                    roll_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_roll').bootstrapToggle(status);
                    });
                    
                    let pitch_override = new ROSLIB.Param({
                        ros: window.ros[ros_hostname],
                        name: '<?php echo $args['param_override_prefix'] ?>pitch_override',
                    });
                    pitch_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_pitch').bootstrapToggle(status);
                    });
                    
                    let yaw_override = new ROSLIB.Param({
                        ros: window.ros[ros_hostname],
                        name: '<?php echo $args['param_override_prefix'] ?>yaw_override',
                    });
                    yaw_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_yaw').bootstrapToggle(status);
                    });
                    
                    let throttle_override = new ROSLIB.Param({
                        ros: window.ros[ros_hostname],
                        name: '<?php echo $args['param_override_prefix'] ?>throttle_override',
                    });
                    throttle_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_throttle').bootstrapToggle(status);
                    });
                    
                    let set_mode_srv = new ROSLIB.Service({
                        ros: window.ros[ros_hostname],
                        name : '<?php echo $args['service_set_mode'] ?>',
                        messageType : 'duckietown_msgs/SetDroneMode'
                    });
            
                let ctx = document.getElementById("drone_control_commands_joy_keys").getContext('2d');
                
                let roll_bar = $('#<?php echo $id ?> #drone_control_commands_bar_roll');
                let pitch_bar = $('#<?php echo $id ?> #drone_control_commands_bar_pitch');
                let yaw_bar = $('#<?php echo $id ?> #drone_control_commands_bar_yaw');
                let throttle_bar = $('#<?php echo $id ?> #drone_control_commands_bar_throttle');
                let gamepad_status = $('#<?php echo $id ?> #drone_control_commands_gamepad_status');
                
                let range = (<?php echo $args['max_value'] ?> - <?php echo $args['min_value'] ?>).toFixed(1);
                
                let joy_stick_data = new JoyXY(0, 0);
                let joy_stick = new JoyStick('drone_control_commands_joy_stick', {}, function (data) {
                    joy_stick_data.x = data.x;
                    joy_stick_data.y = data.y;
                });
                let joy_keys = new Set([]);
                let active_gamepad_id = null;
                
                let armed = false;

                function clamp(value, min, max) {
                    return Math.min(Math.max(value, min), max);
                }

                function apply_deadband(value, deadband) {
                    return Math.abs(value) < deadband ? 0 : value;
                }

                function set_controller_status(text, color) {
                    gamepad_status.text(text);
                    gamepad_status.css('color', color);
                }

                function get_gamepads() {
                    if (!navigator.getGamepads) {
                        return [];
                    }
                    return navigator.getGamepads();
                }

                function get_gamepad_by_id(target_id) {
                    if (target_id === null) {
                        return null;
                    }
                    let gamepads = get_gamepads();
                    for (let gamepad of gamepads) {
                        if (gamepad && gamepad.id === target_id && gamepad.connected) {
                            return gamepad;
                        }
                    }
                    return null;
                }

                function get_active_gamepad() {
                    let current = get_gamepad_by_id(active_gamepad_id);
                    if (current) {
                        return current;
                    }
                    let gamepads = get_gamepads();
                    for (let gamepad of gamepads) {
                        if (gamepad && gamepad.connected) {
                            active_gamepad_id = gamepad.id;
                            return gamepad;
                        }
                    }
                    active_gamepad_id = null;
                    return null;
                }

                function read_gamepad_axes() {
                    let gamepad = get_active_gamepad();
                    if (!gamepad) {
                        set_controller_status('Controller: not detected', '#8a8a8a');
                        return null;
                    }

                    // Standard Gamepad mapping: left stick controls roll/pitch, right stick X controls yaw,
                    // and triggers are combined for throttle.
                    let left_x = apply_deadband(gamepad.axes[0] || 0, CONST_GAMEPAD_AXIS_DEADBAND);
                    let left_y = apply_deadband(gamepad.axes[1] || 0, CONST_GAMEPAD_AXIS_DEADBAND);
                    let right_x = apply_deadband(gamepad.axes[2] || 0, CONST_GAMEPAD_AXIS_DEADBAND);

                    let left_trigger = 0;
                    let right_trigger = 0;
                    if (gamepad.buttons[6]) {
                        left_trigger = apply_deadband(gamepad.buttons[6].value || 0, CONST_GAMEPAD_TRIGGER_DEADBAND);
                    }
                    if (gamepad.buttons[7]) {
                        right_trigger = apply_deadband(gamepad.buttons[7].value || 0, CONST_GAMEPAD_TRIGGER_DEADBAND);
                    }

                    let roll = Math.round(clamp(left_x, -1, 1) * 1000);
                    let pitch = Math.round(clamp(-left_y, -1, 1) * 1000);
                    let yaw = Math.round(clamp(right_x, -1, 1) * 1000);

                    // Trigger differential centers throttle around hover-ish midpoint (500),
                    // then saturates to [0, 1000].
                    let throttle_delta = clamp(right_trigger - left_trigger, -1, 1);
                    let throttle = clamp(Math.round(500 + throttle_delta * 500), 0, 1000);

                    set_controller_status('Controller: connected', '#3c763d');
                    return new JoyAxes(roll, pitch, yaw, throttle);
                }

                window.addEventListener('gamepadconnected', (_) => {
                    set_controller_status('Controller: connected', '#3c763d');
                });

                window.addEventListener('gamepaddisconnected', (_) => {
                    active_gamepad_id = null;
                    set_controller_status('Controller: not detected', '#8a8a8a');
                });

                if (!navigator.getGamepads) {
                    set_controller_status('Controller: unsupported by browser', '#8a8a8a');
                }
            
                $('#<?php echo $id ?> #drone_control_commands_override_roll').change(function() {
                    let checked = $(this).prop('checked');
                    roll_override.set(checked);
                });
            
                $('#<?php echo $id ?> #drone_control_commands_override_pitch').change(function() {
                    let checked = $(this).prop('checked');
                    pitch_override.set(checked);
                });
            
                $('#<?php echo $id ?> #drone_control_commands_override_yaw').change(function() {
                    let checked = $(this).prop('checked');
                    yaw_override.set(checked);
                });
            
                $('#<?php echo $id ?> #drone_control_commands_override_throttle').change(function() {
                    let checked = $(this).prop('checked');
                    throttle_override.set(checked);
                });
                
                // subscribe to control signals
                (new ROSLIB.Topic({
                    ros: window.ros[ros_hostname],
                    name: '<?php echo $args["topic_commands"] ?>',
                    messageType: 'mavros_msgs/ManualControl',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['frequency'] ?>
                })).subscribe(function (message) {
                    // convert normalized values [-1000, 1000] to percentages [0, 100]
                    let r = Math.floor(((message.x + 1000) / 2000) * 100);
                    roll_bar.width("{0}%".format(r));
                    let p = Math.floor(((message.y + 1000) / 2000) * 100);
                    pitch_bar.width("{0}%".format(p));
                    let y = Math.floor(((message.r + 1000) / 2000) * 100);
                    yaw_bar.width("{0}%".format(y));
                    let t = Math.floor(((message.z + 1000) / 2000) * 100);
                    throttle_bar.width("{0}%".format(t));
                });
                
                //subscribe to mode
                (new ROSLIB.Topic({
                    ros: window.ros[ros_hostname],
                    name: '<?php echo $args["topic_mode_current"] ?>',
                    messageType: 'mavros_msgs/State',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['frequency'] ?>
                })).subscribe(function (message) {
                    armed = message.armed;
                });
                
                // joystick commands publisher
                const joystick_topic = new ROSLIB.Topic({
                    ros: window.ros[ros_hostname],
                    name: '<?php echo $args["topic_control"] ?>',
                    messageType: 'mavros_msgs/ManualControl',
                    queue_size: 1
                });
                
                function publish_joy_cmd(joy_axes, joy_buttons) {
                    let control_msg = joy_axes.manualControlMsg;
                    // add header with timestamp
                    control_msg.header = {
                        stamp: {
                            secs: Math.floor(Date.now() / 1000),
                            nsecs: (Date.now() % 1000) * 1000000
                        },
                        frame_id: ''
                    };
                    let msg = new ROSLIB.Message(control_msg);
                    joystick_topic.publish(msg);
                }
                
                function disarm_drone() {
                    // disarm drone
                    let request = new ROSLIB.ServiceRequest({mode: {mode: 0}});
                    // send request
                    set_mode_srv.callService(request, (_) => {});
                }
                
                function map_to_real(k_front, k_back, k_left, k_right) {
                    let x = parseInt(joy_stick_data.x);
                    let y = parseInt(joy_stick_data.y);
                    // throttle: map joystick Y (-100 to 100) to [0, 1000]
                    // y=-100 -> 0, y=0 -> 500, y=100 -> 1000
                    let throttle = Math.round((y + 100) * 5);
                    
                    // deadzone for yaw
                    if (Math.abs(x) < CONST_JOY_YAW_DEADBAND) x = 0;
                    // yaw: map joystick X (-100 to 100) to [-1000, 1000]
                    let yaw = Math.round(x * 10);
                
                    let k_pitch = 0;
                    if (k_front) {
                        k_pitch = 1;
                    } else if (k_back) {
                        k_pitch = -1;
                    }
                    // pitch: discrete values -CONST_MAX_ROLL_PITCH, 0, or CONST_MAX_ROLL_PITCH
                    let pitch = k_pitch * CONST_MAX_ROLL_PITCH;
                    
                    let k_roll = 0;
                    if (k_left) {
                        k_roll = -1;
                    } else if (k_right) {
                        k_roll = 1;
                    }
                    // roll: discrete values -CONST_MAX_ROLL_PITCH, 0, or CONST_MAX_ROLL_PITCH
                    let roll = k_roll * CONST_MAX_ROLL_PITCH;
                    
                    return new JoyAxes(roll, pitch, yaw, throttle);
                }
                
                $(document).on("keyup", (e) => {
                    let key = e.key.toLowerCase();
                    if (['w', 'a', 's', 'd', ' '].indexOf(key) >= 0 && armed) {
                        e.preventDefault();
                    }
                    joy_keys.delete(key);
                });
                
                $(document).on("keydown", (e) => {
                    let key = e.key.toLowerCase();
                    if (['w', 'a', 's', 'd', ' '].indexOf(key) >= 0 && armed) {
                        e.preventDefault();
                    }
                    if (key === " ") {
                        // space -> disarms
                        console.log("Disarming drone...");
                        disarm_drone();
                    } else {
                        joy_keys.add(key);
                    }
                });
                
                function main_loop() {
                    let front = joy_keys.has("w");
                    let back = joy_keys.has("s");
                    let left = joy_keys.has("a");
                    let right = joy_keys.has("d");
                    
                    let line_width = 20;
                    let pos = {
                        up: [50, 45, 50, 10],
                        down: [50, 55, 50, 90],
                        left: [45, 50, 10, 50],
                        right: [55, 50, 90, 50],
                    };
                    let scale = 1.2;
                    let offsetX = 20;
                    let offsetY = 20;
                    
                    for (let k in pos) {
                        pos[k] = pos[k].map(x => x * scale);
                        pos[k][0] += offsetX;
                        pos[k][2] += offsetX;
                        pos[k][1] += offsetY;
                        pos[k][3] += offsetY;
                    }
                    
                    drawArrow(ctx, ...pos.up, line_width, front ? 'green' : 'gray');
                    drawArrow(ctx, ...pos.down, line_width, back ? 'green' : 'gray');
                    drawArrow(ctx, ...pos.left, line_width, left ? 'green' : 'gray');
                    drawArrow(ctx, ...pos.right, line_width, right ? 'green' : 'gray');
                    
                    let joy_axes = read_gamepad_axes();
                    if (joy_axes === null) {
                        joy_axes = map_to_real(front, back, left, right);
                    }
                    publish_joy_cmd(joy_axes, {});
                }
                
                setInterval(main_loop, 50);
                    
                } catch (err) {
                    console.error('[Duckiedrone_Control] Failed to initialize widget:', err);
                }
            })();
        </script>
        
        <?php
        // Note: ROS::connect() is no longer needed here since widgets use runtime discovery
        // via ROSConfig.init(). The old event-based pattern is replaced with async/await.
        ?>

        <style type="text/css">
            #<?php echo $id ?>{
                background-color: <?php echo $args['background_color'] ?>;
            }
        </style>
        <?php
    }//render
    
}//Duckiedrone_Control
?>
