<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class Duckiedrone_Battery extends BlockRenderer {

    static protected $ICON = [
        "class" => "fa",
        "name" => "battery-three-quarters"
    ];

    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "topic" => [
            "name" => "ROS Topic",
            "type" => "text",
            "mandatory" => True,
            "default" => "/mavros/battery"
        ],
        "timeout" => [
            "name" => "Seconds without data before the reading is cleared",
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
        <style type="text/css">
            #<?php echo $id ?> {
                background-color: <?php echo $args['background_color'] ?>;
            }
            #<?php echo $id ?> .battery-widget {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                width: 100%;
                box-sizing: border-box;
            }
            #<?php echo $id ?> .battery-icon {
                position: relative;
                width: 180px;
                height: 92px;
                margin-right: 12px;
                border: 4px solid #333;
                border-radius: 12px;
                box-sizing: border-box;
            }
            #<?php echo $id ?> .battery-icon::after {
                content: "";
                position: absolute;
                right: -14px;
                top: 50%;
                transform: translateY(-50%);
                width: 10px;
                height: 30px;
                background-color: #333;
                border-radius: 0 5px 5px 0;
            }
            #<?php echo $id ?> .battery-level {
                position: absolute;
                top: 4px;
                right: 4px;
                bottom: 4px;
                left: 4px;
            }
            #<?php echo $id ?> .battery-level-fill {
                height: 100%;
                width: 0;
                background-color: #9ecae1;
                border-radius: 5px;
                transition: width 0.5s;
            }
            /* the level is also told by the fill width, the number and the LOW label, not by colour alone */
            #<?php echo $id ?> .battery-icon.level-high .battery-level-fill {
                background-color: #5cc99b;
            }
            #<?php echo $id ?> .battery-icon.level-mid .battery-level-fill {
                background-color: #f0e442;
            }
            #<?php echo $id ?> .battery-icon.level-low .battery-level-fill {
                background-color: #e8704a;
            }
            #<?php echo $id ?> .battery-low-label {
                display: none;
                font-size: 9pt;
                font-weight: bold;
                letter-spacing: 1px;
            }
            #<?php echo $id ?> .battery-icon.level-low .battery-low-label {
                display: inline;
            }
            #<?php echo $id ?> .battery-text {
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                color: #222;
                line-height: 1.15;
            }
            #<?php echo $id ?> .battery-percentage {
                font-size: 24pt;
                font-weight: bold;
            }
            #<?php echo $id ?> .battery-voltage {
                font-size: 12pt;
            }
        </style>

        <div class="battery-widget">
            <div class="battery-icon">
                <div class="battery-level"><div class="battery-level-fill"></div></div>
                <div class="battery-text">
                    <span class="battery-percentage">--</span>
                    <span class="battery-voltage">--</span>
                    <span class="battery-low-label">LOW</span>
                </div>
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
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                let percentage_txt = $('#<?php echo $id ?> .battery-percentage');
                let voltage_txt = $('#<?php echo $id ?> .battery-voltage');
                let bar = $('#<?php echo $id ?> .battery-level-fill');
                let icon = $('#<?php echo $id ?> .battery-icon');
                let stale_timer = null;

                function set_unknown() {
                    percentage_txt.text('--');
                    voltage_txt.text('--');
                    bar.css('width', '0');
                    icon.removeClass('level-high level-mid level-low');
                }

                (new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['topic'] ?>',
                    messageType: 'sensor_msgs/BatteryState',
                    queue_size: 1,
                    throttle_rate: 1000
                })).subscribe(function (message) {
                    clearTimeout(stale_timer);
                    stale_timer = setTimeout(set_unknown, <?php echo $args['timeout'] * 1000 ?>);
                    if (!isFinite(message.voltage) || message.voltage <= 0) {
                        set_unknown();
                        return;
                    }
                    voltage_txt.text(message.voltage.toFixed(2) + ' V');
                    // sensor_msgs/BatteryState carries the charge as a [0, 1] fraction
                    if (isFinite(message.percentage) && message.percentage >= 0) {
                        let percentage = Math.min(message.percentage * 100, 100);
                        percentage_txt.text(percentage.toFixed(0) + '%');
                        bar.css('width', percentage + '%');
                        icon.removeClass('level-high level-mid level-low').addClass(
                            percentage > 50 ? 'level-high' : (percentage > 20 ? 'level-mid' : 'level-low'));
                    } else {
                        percentage_txt.text('--');
                        bar.css('width', '0');
                        icon.removeClass('level-high level-mid level-low');
                    }
                });
            });
        </script>

        <?php
        ROS::connect($ros_hostname);
    }//render

}//Duckiedrone_Battery
?>
