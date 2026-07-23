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
        <table class="resizable" style="height: 100%">
            <tr style="height: 20px; font-weight: bold">
                <td class="col-md-1">
                    Channel
                </td>
                <td class="col-md-1 text-center">
                    Override
                </td>
                <td class="col-md-4 text-left">
                    Intensity
                </td>
                <td rowspan="5" class="col-md-1 text-center" style="padding: 0; vertical-align: middle">
                    <canvas id="drone_control_commands_throttle_gauge" width="56px" height="110px"></canvas>
                    <div style="margin-top: 3px"
                         data-toggle="tooltip" data-placement="top"
                         title="The throttle where the drone lifts off. Below it throttle rises fast, above it rises slowly for fine control. Blue line on the gauge.">
                        <label for="drone_control_commands_hover_threshold" style="display: block; font-size: 12px; margin: 0 0 1px; font-weight: normal">Hover %</label>
                        <input type="number" id="drone_control_commands_hover_threshold"
                               min="0" max="100" step="1"
                               style="width: 56px; font-size: 13px; padding: 1px 3px">
                    </div>
                    <div style="margin-top: 5px"
                         data-toggle="tooltip" data-placement="top"
                         title="The most throttle allowed. The drone never goes above this, whatever the keyboard or joystick asks for. Red line on the gauge.">
                        <label for="drone_control_commands_max_throttle" style="display: block; font-size: 12px; margin: 0 0 1px; font-weight: normal">Thrust Cap %</label>
                        <input type="number" id="drone_control_commands_max_throttle"
                               min="0" max="100" step="1"
                               style="width: 56px; font-size: 13px; padding: 1px 3px">
                    </div>
                </td>
                <td rowspan="5" class="col-md-2 text-center" style="padding: 0; vertical-align: middle">
                    <canvas id="drone_control_commands_joy_keys" width="150px" height="134px"></canvas>
                    <div style="font-size: 13px; color: #555">Roll / Pitch</div>
                </td>
                <td rowspan="5" class="col-md-2 text-center" style="padding: 0; vertical-align: middle">
                    <div id="drone_control_commands_joy_stick" style="width:120px;height:120px;margin:0 auto;"></div>
                    <div style="font-size: 13px; color: #555">Yaw / Throttle</div>
                </td>
            </tr>
            <?php
            $bars = [
                [
                    "id" => "roll",
                    "label" => "Roll",
                    "tooltip" => "Tilt left or right. Keys A and D."
                ],
                [
                    "id" => "pitch",
                    "label" => "Pitch",
                    "tooltip" => "Tilt forward or back. Keys W and S."
                ],
                [
                    "id" => "yaw",
                    "label" => "Yaw",
                    "tooltip" => "Turn left or right. Left and right arrow keys."
                ],
                [
                    "id" => "throttle",
                    "label" => "Throttle",
                    "tooltip" => "More or less thrust. Up and down arrow keys. Stays where it is left."
                ],
            ];
            
            foreach ($bars as &$bar) {
                ?>
                <tr style="height: 20px">
                    <td class="col-md-1" style="text-align: right"
                        data-toggle="tooltip" data-placement="top"
                        title="<?php echo $bar["tooltip"] ?>">
                        <p class="text-right" style="margin: 0"><?php echo $bar["label"] ?></p>
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
            <tr>
                <td colspan="6" style="padding: 2px 4px 0; border-top: 1px solid #eee">
                    <div style="font-size: 14px; color: #666; text-align: center; line-height: 1.5">
                        <b>A</b> / <b>D</b> = Roll (Roll Left / Right)
                        &nbsp;&middot;&nbsp; <b>W</b> / <b>S</b> = Pitch (Pitch Forward / Back)
                        &nbsp;&middot;&nbsp; <b>&larr;</b> / <b>&rarr;</b> = Yaw (Yaw Left / Right)
                        &nbsp;&middot;&nbsp; <b>&uarr;</b> / <b>&darr;</b> = Throttle (More / Less)
                        &nbsp;&middot;&nbsp; <b>Space</b> = Disarm
                    </div>
                </td>
            </tr>
        </table>
        
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
            const CONST_MAX_ROLL_PITCH = <?php echo $args['max_roll_pitch'] ?? self::$ARGUMENTS['max_roll_pitch']['default'] ?>;

            // Keyboard yaw/throttle (arrow keys). Tune per airframe.
            // joystick-X magnitude while a yaw key is held. Chosen so the resulting yaw
            // command (map_to_real: yaw = x * 10) equals the roll/pitch command magnitude
            // (CONST_MAX_ROLL_PITCH), keeping the yaw rate matched to the roll/pitch rate.
            const CONST_YAW_KEY_VALUE = CONST_MAX_ROLL_PITCH / 10;
            const CONST_THROTTLE_STEP_COARSE = 25;       // throttle units (z, [0,1000]) added per tick below the hover threshold
            const CONST_THROTTLE_STEP_FINE = 10;         // throttle units per tick at/above the hover threshold (fine hover trim)
            const CONST_DEFAULT_HOVER_THRESHOLD = 100;   // conservatively low until the user calibrates their own value below
            const CONST_DEFAULT_MAX_THROTTLE = 400;      // hard ceiling on thrust (z, [0,1000]); shown to the user as 40%
            
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

            // Vertical throttle gauge for the `z` command [0,1000]: fill height tracks
            // current throttle, a blue line marks the user-calibrated hover_threshold, a red
            // line marks the max_throttle ceiling, and the fill turns green once z crosses
            // the hover threshold.
            function drawThrottleGauge(gctx, z, hover_threshold, max_throttle) {
                let W = gctx.canvas.width, H = gctx.canvas.height;
                let pad = 4, barW = 26, labelH = 16;
                let x0 = (W - barW) / 2, y0 = pad, barH = H - pad * 2 - labelH;
                gctx.clearRect(0, 0, W, H);

                let frac = Math.max(0, Math.min(1, z / 1000));
                let aboveThresh = z >= hover_threshold;

                // filled portion: grey while grounded, green once airborne
                let fillH = barH * frac;
                gctx.fillStyle = aboveThresh ? '#28a745' : '#888';
                gctx.fillRect(x0, y0 + barH - fillH, barW, fillH);

                // frame
                gctx.strokeStyle = '#333';
                gctx.lineWidth = 1;
                gctx.strokeRect(x0, y0, barW, barH);

                // tick marks every 25%
                gctx.strokeStyle = '#bbb';
                for (let f = 0.25; f < 1; f += 0.25) {
                    let ty = y0 + barH - barH * f;
                    gctx.beginPath();
                    gctx.moveTo(x0, ty);
                    gctx.lineTo(x0 + 4, ty);
                    gctx.stroke();
                }

                // hover_threshold marker line (blue)
                let thY = y0 + barH - barH * (hover_threshold / 1000);
                gctx.strokeStyle = '#337ab7';
                gctx.lineWidth = 2;
                gctx.beginPath();
                gctx.moveTo(x0 - 3, thY);
                gctx.lineTo(x0 + barW + 3, thY);
                gctx.stroke();

                // max_throttle ceiling line (red)
                let ceilY = y0 + barH - barH * (max_throttle / 1000);
                gctx.strokeStyle = '#d9534f';
                gctx.lineWidth = 2;
                gctx.beginPath();
                gctx.moveTo(x0 - 3, ceilY);
                gctx.lineTo(x0 + barW + 3, ceilY);
                gctx.stroke();

                // z as a percentage of full throttle
                gctx.fillStyle = '#000';
                gctx.font = '13px monospace';
                gctx.textAlign = 'center';
                gctx.fillText(Math.round(frac * 100) + '%', W / 2, H - 2);
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
                    // PX4/MAVLink MANUAL_CONTROL: x-axis = pitch, y-axis = roll.
                    return {
                        x: this.pitch,     // int16 [-1000, 1000], forward +
                        y: this.roll,      // int16 [-1000, 1000], right +
                        z: this.throttle,  // int16 [0, 1000]
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
            
                let ctx = document.getElementById("drone_control_commands_joy_keys").getContext('2d');
                let gauge_ctx = document.getElementById("drone_control_commands_throttle_gauge").getContext('2d');

                let roll_bar = $('#<?php echo $id ?> #drone_control_commands_bar_roll');
                let pitch_bar = $('#<?php echo $id ?> #drone_control_commands_bar_pitch');
                let yaw_bar = $('#<?php echo $id ?> #drone_control_commands_bar_yaw');
                let throttle_bar = $('#<?php echo $id ?> #drone_control_commands_bar_throttle');
                
                let joy_stick_data = new JoyXY(0, -100);
                let joy_stick = new JoyStick('drone_control_commands_joy_stick', {
                    autoReturnToCenterX: true,
                    autoReturnToCenterY: false,
                    initialY: -100
                }, function (data) {
                    joy_stick_data.x = data.x;
                    joy_stick_data.y = data.y;
                });
                let joy_keys = new Set([]);
                let kb_yaw_prev = false; // true if a yaw key was held last tick (to recenter on release)

                let armed = false;

                // Settings persist per widget instance across page reloads. localStorage can
                // throw (private browsing, sandboxed iframe, storage disabled), so both calls
                // are wrapped: a storage failure must never stop the setup below, or the
                // manual_control publish loop would die and the drone could not arm.
                function load_setting(key, fallback) {
                    try {
                        return Number(localStorage.getItem(key)) || fallback;
                    } catch (e) {
                        return fallback;
                    }
                }
                function save_setting(key, value) {
                    try {
                        localStorage.setItem(key, value);
                    } catch (e) {}
                }

                // Hover threshold: the z value where this specific drone leaves the ground.
                // Users find it by ramping throttle up and reading the gauge/bar, then typing
                // it in so the coarse->fine ramp switchover and the gauge line match their
                // airframe.
                const hover_threshold_storage_key = 'drone_control_hover_threshold_<?php echo $id ?>';
                // Stored/used internally as a z value in [0,1000]; shown and entered as a
                // percentage of full thrust (0-100%). % <-> z: z = pct * 10.
                let hover_threshold = load_setting(hover_threshold_storage_key, CONST_DEFAULT_HOVER_THRESHOLD);
                let hover_threshold_input = $('#<?php echo $id ?> #drone_control_commands_hover_threshold');
                hover_threshold_input.val(Math.round(hover_threshold / 10));
                hover_threshold_input.on('change', function () {
                    let raw = Number($(this).val());
                    let pct = isNaN(raw) ? CONST_DEFAULT_HOVER_THRESHOLD / 10 : Math.max(0, Math.min(100, raw));
                    pct = Math.round(pct);
                    hover_threshold = pct * 10;
                    $(this).val(pct);
                    save_setting(hover_threshold_storage_key, hover_threshold);
                });

                // Max throttle: hard ceiling on the z command. Thrust can never be commanded
                // above this, whatever the keyboard ramp or joystick asks for.
                const max_throttle_storage_key = 'drone_control_max_throttle_<?php echo $id ?>';
                // Same convention as the hover threshold: internal z in [0,1000], shown and
                // entered as a percentage of full thrust (0-100%).
                let max_throttle = load_setting(max_throttle_storage_key, CONST_DEFAULT_MAX_THROTTLE);
                let max_throttle_input = $('#<?php echo $id ?> #drone_control_commands_max_throttle');
                max_throttle_input.val(Math.round(max_throttle / 10));
                max_throttle_input.on('change', function () {
                    let raw = Number($(this).val());
                    let pct = isNaN(raw) ? CONST_DEFAULT_MAX_THROTTLE / 10 : Math.max(0, Math.min(100, raw));
                    pct = Math.round(pct);
                    max_throttle = pct * 10;
                    $(this).val(pct);
                    save_setting(max_throttle_storage_key, max_throttle);
                });

                // Native title tooltips always work; upgrade to Bootstrap tooltips when the
                // plugin is present. Guarded so a missing plugin cannot break setup.
                try {
                    $('#<?php echo $id ?> [data-toggle="tooltip"]').tooltip();
                } catch (e) {}
            
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
                    // MANUAL_CONTROL: x = pitch, y = roll.
                    let p = Math.floor(((message.x + 1000) / 2000) * 100);
                    pitch_bar.width("{0}%".format(p));
                    let r = Math.floor(((message.y + 1000) / 2000) * 100);
                    roll_bar.width("{0}%".format(r));
                    let y = Math.floor(((message.r + 1000) / 2000) * 100);
                    yaw_bar.width("{0}%".format(y));
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
                    joy_stick.SetPosition(0, -100);
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
                
                function map_to_real(k_front, k_back, k_left, k_right) {
                    let x = Number(joy_stick_data.x);
                    let y = Number(joy_stick_data.y);
                    // throttle: map joystick Y (-100 to 100) to [0, 1000]
                    // y=-100 -> 0, y=0 -> 500, y=100 -> 1000
                    // then apply the hard ceiling so nothing published can exceed it
                    let throttle = Math.min(max_throttle, Math.round((y + 100) * 5));
                    
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
                
                // Keys this widget owns for flight control. Always prevented from doing
                // their default browser thing (space/arrows scroll the page) regardless
                // of arm state, so the page never scrolls out from under the pilot.
                const CONTROL_KEYS = ['w', 'a', 's', 'd', ' ',
                    'arrowup', 'arrowdown', 'arrowleft', 'arrowright'];

                $(document).on("keyup", (e) => {
                    let key = e.key.toLowerCase();
                    if (CONTROL_KEYS.indexOf(key) >= 0) {
                        e.preventDefault();
                    }
                    joy_keys.delete(key);
                });

                $(document).on("keydown", (e) => {
                    let key = e.key.toLowerCase();
                    if (CONTROL_KEYS.indexOf(key) >= 0) {
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

                    // keyboard yaw (arrows L/R) and throttle (arrows U/D) drive the joystick,
                    // keeping joy_stick_data the single source of truth shared with the mouse.
                    let yaw_left = joy_keys.has("arrowleft");
                    let yaw_right = joy_keys.has("arrowright");
                    let thr_up = joy_keys.has("arrowup");
                    let thr_down = joy_keys.has("arrowdown");

                    let new_x = Number(joy_stick_data.x);
                    let new_y = Number(joy_stick_data.y);
                    let update_stick = false;

                    // throttle: ramp and hold (coarse below the hover threshold, fine above)
                    if (thr_up || thr_down) {
                        let z = (new_y + 100) * 5; // joystick-Y -> throttle [0,1000]
                        let step = (z < hover_threshold)
                            ? CONST_THROTTLE_STEP_COARSE : CONST_THROTTLE_STEP_FINE;
                        z += thr_up ? step : -step;
                        z = Math.max(0, Math.min(max_throttle, z));
                        new_y = z / 5 - 100;
                        update_stick = true;
                    }

                    // yaw: momentary, recenters on release (like roll/pitch)
                    let kb_yaw = yaw_left || yaw_right;
                    if (kb_yaw) {
                        new_x = yaw_left ? -CONST_YAW_KEY_VALUE : CONST_YAW_KEY_VALUE;
                        update_stick = true;
                    } else if (kb_yaw_prev) {
                        new_x = 0;
                        update_stick = true;
                    }
                    kb_yaw_prev = kb_yaw;

                    if (update_stick) {
                        joy_stick.SetPosition(new_x, new_y);
                    }

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
                    
                    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                    drawArrow(ctx, ...pos.up, line_width, front ? 'green' : 'gray');
                    drawArrow(ctx, ...pos.down, line_width, back ? 'green' : 'gray');
                    drawArrow(ctx, ...pos.left, line_width, left ? 'green' : 'gray');
                    drawArrow(ctx, ...pos.right, line_width, right ? 'green' : 'gray');
                    
                    let joy_axes = map_to_real(front, back, left, right);
                    publish_joy_cmd(joy_axes, {});

                    drawThrottleGauge(gauge_ctx, joy_axes.throttle, hover_threshold, max_throttle);
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
