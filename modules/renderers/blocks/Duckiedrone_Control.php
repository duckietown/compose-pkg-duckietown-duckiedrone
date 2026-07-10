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
            "mandatory" => False,
            "default" => ""
        ],
        "arming_service" => [
            "name" => "ROS Service (ARM/DISARM)",
            "type" => "text",
            "mandatory" => False,
            "default" => "/mavros/cmd/arming"
        ],
        "service_override_commands" => [
            "name" => "ROS Service (Set commands override)",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "param_override_prefix" => [
            "name" => "ROS Param Prefix (Command override)",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "topic_mode_current" => [
            "name" => "ROS Topic (Read mode)",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/state"
        ],
        "topic_control" => [
            "name" => "ROS Topic (Joystick control)",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/manual_control/send"
        ],
        "topic_commands" => [
            "name" => "ROS Topic (Read commands)",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/manual_control/send"
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
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        $override_param_prefix = trim($args['param_override_prefix'] ?? '');
        $has_override_params = strlen($override_param_prefix) > 0;
        $override_disabled_attr = $has_override_params ? '' : 'disabled';
        $mode_topic = $args['topic_mode_current'] ?? self::$ARGUMENTS['topic_mode_current']['default'];
        $control_topic = $args['topic_control'] ?? self::$ARGUMENTS['topic_control']['default'];
        $commands_topic = $args['topic_commands'] ?? $control_topic;
        if (strlen($commands_topic) <= 0) {
            $commands_topic = $control_topic;
        }
        $arming_service = $args['arming_service'] ?? self::$ARGUMENTS['arming_service']['default'];
        $legacy_set_mode_service = trim($args['service_set_mode'] ?? '');
        ?>
        <style type="text/css">
            #<?php echo $id ?> .rc-gimbal {
                position: relative;
                width: 130px; height: 130px;
                margin: 2px auto;
                border: 2px solid #999;
                border-radius: 10px;
                background: #f2f2f2;
                touch-action: none;
                box-sizing: border-box;
            }
            #<?php echo $id ?> .rc-gimbal .rc-crosshair {
                position: absolute; left: 50%; top: 50%;
                width: 8px; height: 8px; margin-left: -4px; margin-top: -4px;
                border-radius: 50%; background: #ccc;
            }
            #<?php echo $id ?> .rc-gimbal .rc-knob {
                position: absolute;
                width: 36px; height: 36px;
                margin-left: -18px; margin-top: -18px;
                border-radius: 50%;
                background: #00AA00;
                border: 2px solid #003300;
                pointer-events: none;
            }
        </style>
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
                <!-- RC Mode 2 layout: left stick = throttle (up/down) + yaw (left/right),
                     right stick = pitch (up/down) + roll (left/right). -->
                <td rowspan="5" class="text-center" style="padding: 0 4px; vertical-align: top">
                    <div style="font-size:8pt; font-weight:bold;">&minus; yaw +</div>
                    <div style="display:flex; justify-content:center; align-items:stretch;">
                        <div style="display:flex; flex-direction:column; justify-content:space-between; align-items:center; font-size:8pt; font-weight:bold; padding:2px;">
                            <span>+</span>
                            <span style="writing-mode:vertical-rl; text-orientation:mixed;">throttle</span>
                            <span>0</span>
                        </div>
                        <div id="<?php echo $id ?>_rc_left" class="rc-gimbal"><div class="rc-crosshair"></div></div>
                    </div>
                    <div style="font-size:8pt;">THR: <span id="<?php echo $id ?>_rc_thr_val">0</span></div>
                </td>
                <td rowspan="5" class="text-center" style="padding: 0 4px; vertical-align: top">
                    <div style="font-size:8pt; font-weight:bold;">&minus; roll +</div>
                    <div style="display:flex; justify-content:center; align-items:stretch;">
                        <div style="display:flex; flex-direction:column; justify-content:space-between; align-items:center; font-size:8pt; font-weight:bold; padding:2px;">
                            <span>+</span>
                            <span style="writing-mode:vertical-rl; text-orientation:mixed;">pitch</span>
                            <span>&minus;</span>
                        </div>
                        <div id="<?php echo $id ?>_rc_right" class="rc-gimbal"><div class="rc-crosshair"></div></div>
                    </div>
                    <div style="font-size:8pt;">&nbsp;</div>
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
                               <?php echo $override_disabled_attr ?>
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
        
        <!-- Include ROS -->
        <script src="<?php echo Core::getJSscriptURL('rosdb.js', 'ros') ?>"></script>

        <script type="text/javascript">
            const CONST_YAW_DELTA = 90;
            const CONST_ROLL_DELTA = 90;
            const CONST_PITCH_DELTA = 90;
            const CONST_MID_VAL = 1500;
            
            const CONST_JOY_YAW_DEADBAND = 20;
            const CONST_MAX_ROLL_PITCH = <?php echo $args['max_roll_pitch'] ?? self::$ARGUMENTS['max_roll_pitch']['default'] ?>;
            
            // A minimal RC-style gimbal: a draggable knob in a square pad, with
            // independent auto-centering per axis. Reports x and y in [-100, 100]
            // (y is positive-up). Uses Pointer Events, so a single mouse or several
            // touch points (one thumb per gimbal) work the same way.
            class VirtualStick {
                constructor(containerId, opts) {
                    opts = opts || {};
                    this.autoCenterX = opts.autoCenterX !== false;   // default: springs to center
                    this.autoCenterY = opts.autoCenterY !== false;
                    this.initialX = (typeof opts.initialX === 'number') ? opts.initialX : 0;
                    this.initialY = (typeof opts.initialY === 'number') ? opts.initialY : 0;
                    this.onChange = opts.onChange || function () {};
                    this.x = this.initialX;
                    this.y = this.initialY;

                    this.container = document.getElementById(containerId);
                    this.knob = document.createElement('div');
                    this.knob.className = 'rc-knob';
                    this.container.appendChild(this.knob);

                    this.dragging = false;
                    let self = this;
                    this.container.addEventListener('pointerdown', function (e) {
                        self.dragging = true;
                        try { self.container.setPointerCapture(e.pointerId); } catch (_) {}
                        self.knob.style.transition = 'none';
                        self._update(e);
                    });
                    this.container.addEventListener('pointermove', function (e) {
                        if (self.dragging) self._update(e);
                    });
                    let release = function (e) {
                        if (!self.dragging) return;
                        self.dragging = false;
                        try { self.container.releasePointerCapture(e.pointerId); } catch (_) {}
                        self.knob.style.transition = 'left 0.08s ease-out, top 0.08s ease-out';
                        if (self.autoCenterX) self.x = 0;   // e.g. yaw / roll / pitch spring back
                        if (self.autoCenterY) self.y = 0;   // throttle does NOT (holds where left)
                        self._render();
                        self.onChange(self.x, self.y);
                    };
                    this.container.addEventListener('pointerup', release);
                    this.container.addEventListener('pointercancel', release);

                    if (typeof ResizeObserver !== 'undefined') {
                        new ResizeObserver(function () { self._render(); }).observe(this.container);
                    } else {
                        window.addEventListener('resize', function () { self._render(); });
                    }

                    this._render();
                }

                _metrics() {
                    let r = this.container.getBoundingClientRect();
                    let size = Math.min(r.width, r.height);
                    return { size: size, maxR: size / 2 - 4,
                             cx: r.left + r.width / 2, cy: r.top + r.height / 2 };
                }

                _update(e) {
                    let m = this._metrics();
                    if (m.maxR <= 0) return;
                    let dx = Math.max(-m.maxR, Math.min(m.maxR, e.clientX - m.cx));
                    let dy = Math.max(-m.maxR, Math.min(m.maxR, e.clientY - m.cy));
                    this.x = Math.round(dx / m.maxR * 100);
                    this.y = Math.round(-dy / m.maxR * 100);   // screen-down is negative
                    this._render();
                    this.onChange(this.x, this.y);
                }

                _render() {
                    let m = this._metrics();
                    if (m.maxR <= 0) return;
                    let half = m.size / 2;
                    this.knob.style.left = (half + (this.x / 100) * m.maxR) + 'px';
                    this.knob.style.top = (half - (this.y / 100) * m.maxR) + 'px';
                }

                setY(v) { this.y = v; this._render(); }
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
                    // PX4/MAVLink MANUAL_CONTROL: x-axis = pitch, y-axis = roll
                    // (PX4 maps pitch = x/1000, roll = y/1000). Do NOT swap these.
                    return {
                        x: this.pitch,     // int16 [-1000, 1000], forward +
                        y: this.roll,      // int16 [-1000, 1000], right +
                        z: this.throttle,  // int16 [0, 1000]
                        r: this.yaw,       // int16 [-1000, 1000], clockwise +
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
      
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                const hasOverrideParams = <?php echo $has_override_params ? 'true' : 'false' ?>;
                let roll_override = null;
                let pitch_override = null;
                let yaw_override = null;
                let throttle_override = null;

                if (hasOverrideParams) {
                    roll_override = new ROSLIB.Param({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: '<?php echo $override_param_prefix ?>roll_override',
                    });
                    roll_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_roll').bootstrapToggle(status);
                    });

                    pitch_override = new ROSLIB.Param({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: '<?php echo $override_param_prefix ?>pitch_override',
                    });
                    pitch_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_pitch').bootstrapToggle(status);
                    });

                    yaw_override = new ROSLIB.Param({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: '<?php echo $override_param_prefix ?>yaw_override',
                    });
                    yaw_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_yaw').bootstrapToggle(status);
                    });

                    throttle_override = new ROSLIB.Param({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: '<?php echo $override_param_prefix ?>throttle_override',
                    });
                    throttle_override.get((v) => {
                        let status = (v)? 'on' : 'off';
                        $('#<?php echo $id ?> #drone_control_commands_override_throttle').bootstrapToggle(status);
                    });
                } else {
                    let override_inputs = $('#<?php echo $id ?> input[id^="drone_control_commands_override_"]');
                    override_inputs.bootstrapToggle('off');
                    override_inputs.bootstrapToggle('disable');
                }

                let arming_srv = null;
                if ('<?php echo $arming_service ?>'.length > 0) {
                    arming_srv = new ROSLIB.Service({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name : '<?php echo $arming_service ?>',
                        serviceType : 'mavros_msgs/CommandBool'
                    });
                }

                let legacy_set_mode_srv = null;
                if ('<?php echo $legacy_set_mode_service ?>'.length > 0) {
                    legacy_set_mode_srv = new ROSLIB.Service({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name : '<?php echo $legacy_set_mode_service ?>',
                        serviceType : 'duckietown_msgs/SetDroneMode'
                    });
                }
            
                let roll_bar = $('#<?php echo $id ?> #drone_control_commands_bar_roll');
                let pitch_bar = $('#<?php echo $id ?> #drone_control_commands_bar_pitch');
                let yaw_bar = $('#<?php echo $id ?> #drone_control_commands_bar_yaw');
                let throttle_bar = $('#<?php echo $id ?> #drone_control_commands_bar_throttle');
                let throttle_value_label = $('#<?php echo $id ?>_rc_thr_val');

                // Two RC Mode-2 gimbals.
                //   LEFT  — X = yaw (springs to center), Y = throttle (holds; rests at bottom = 0)
                //   RIGHT — X = roll, Y = pitch (both spring to center)
                let left_stick = new VirtualStick('<?php echo $id ?>_rc_left', {
                    autoCenterX: true,
                    autoCenterY: false,
                    initialY: -100,   // bottom of travel -> throttle 0, so the FC can arm
                    onChange: function (x, y) {
                        throttle_value_label.text(Math.round((y + 100) * 5));
                    }
                });
                let right_stick = new VirtualStick('<?php echo $id ?>_rc_right', {
                    autoCenterX: true,
                    autoCenterY: true
                });

                let armed = false;
            
                if (hasOverrideParams) {
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
                }
                
                // subscribe to control signals
                (new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $commands_topic ?>',
                    messageType: 'mavros_msgs/ManualControl',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['frequency'] ?>
                })).subscribe(function (message) {
                    // convert normalized values [-1000, 1000] to percentages [0, 100]
                    // MANUAL_CONTROL: x = pitch, y = roll (see manualControlMsg).
                    let p = Math.floor(((message.x + 1000) / 2000) * 100);
                    pitch_bar.width("{0}%".format(p));
                    let r = Math.floor(((message.y + 1000) / 2000) * 100);
                    roll_bar.width("{0}%".format(r));
                    let y = Math.floor(((message.r + 1000) / 2000) * 100);
                    yaw_bar.width("{0}%".format(y));
                    // throttle z is 0..1000 (not centered like roll/pitch/yaw), so map straight to 0..100%
                    let t = Math.max(0, Math.min(100, Math.floor((message.z / 1000) * 100)));
                    throttle_bar.width("{0}%".format(t));
                });
                
                //subscribe to mode
                (new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $mode_topic ?>',
                    messageType: 'mavros_msgs/State',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['frequency'] ?>
                })).subscribe(function (message) {
                    armed = message.armed;
                });
                
                // joystick commands publisher
                const joystick_topic = new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $control_topic ?>',
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
                    // Safety: drop throttle back to 0 on disarm, so the next arm starts from
                    // zero throttle (the FC rejects arming at non-zero throttle).
                    left_stick.setY(-100);
                    throttle_value_label.text('0');
                    if (arming_srv !== null) {
                        let request = new ROSLIB.ServiceRequest({value: false});
                        arming_srv.callService(request, (_) => {});
                        return;
                    }
                    if (legacy_set_mode_srv !== null) {
                        let request = new ROSLIB.ServiceRequest({mode: {mode: 0}});
                        legacy_set_mode_srv.callService(request, (_) => {});
                        return;
                    }
                    console.warn('No disarm service configured for Duckiedrone_Control.');
                }
                
                function compute_axes() {
                    // LEFT stick: X = yaw (with a small deadband), Y = throttle (0..1000, held).
                    let yaw_raw = left_stick.x;
                    if (Math.abs(yaw_raw) < CONST_JOY_YAW_DEADBAND) yaw_raw = 0;
                    let yaw = Math.round(yaw_raw * 10);                    // -1000..1000
                    let throttle = Math.round((left_stick.y + 100) * 5);   // 0..1000 (bottom..top)
                    throttle = Math.max(0, Math.min(1000, throttle));

                    // RIGHT stick: X = roll, Y = pitch, scaled to the configured max authority.
                    let roll = Math.round((right_stick.x / 100) * CONST_MAX_ROLL_PITCH);
                    let pitch = Math.round((right_stick.y / 100) * CONST_MAX_ROLL_PITCH);

                    return new JoyAxes(roll, pitch, yaw, throttle);
                }

                // Spacebar remains an emergency disarm.
                $(document).on("keydown", (e) => {
                    if (e.key === " " && armed) {
                        e.preventDefault();
                        console.log("Disarming drone...");
                        disarm_drone();
                    }
                });

                function main_loop() {
                    publish_joy_cmd(compute_axes(), {});
                }

                setInterval(main_loop, 50);
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
    }//render
    
}//Duckiedrone_Control
?>
