<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class Duckiedrone_Heartbeats_Monitor extends BlockRenderer {
    
    static protected $ICON = [
        "class" => "glyphicon",
        "name" => "tasks"
    ];
    
    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "topic1" => [
            "name" => "ROS Topic (1)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "label1" => [
            "name" => "Heartbeat label (1)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "override1" => [
            "name" => "Override ROS Param (1)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "topic2" => [
            "name" => "ROS Topic (2)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "label2" => [
            "name" => "Heartbeat label (2)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "override2" => [
            "name" => "Override ROS Param (2)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "topic3" => [
            "name" => "ROS Topic (3)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "label3" => [
            "name" => "Heartbeat label (3)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "override3" => [
            "name" => "Override ROS Param (3)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "topic4" => [
            "name" => "ROS Topic (4)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "label4" => [
            "name" => "Heartbeat label (4)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "override4" => [
            "name" => "Override ROS Param (4)",
            "type" => "text",
            "mandatory" => False,
            "default" => null
        ],
        "threshold" => [
            "name" => "Threshold in seconds before the heartbeat disappears",
            "type" => "number",
            "mandatory" => True,
            "default" => 2
        ],
        "frequency" => [
            "name" => "Frequency (Hz)",
            "type" => "number",
            "default" => 5,
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
        <div class="drone-hb resizable">
            <?php
            for ($i = 1; $i <= 4; $i++) {
                if (is_null($args["topic{$i}"]) || strlen($args["topic{$i}"]) <= 0)
                    continue;
                ?>
                <div class="drone-hb-item heartbeats-monitor-heart<?php echo $i ?>" id="heartbeats-monitor-heart<?php echo $i ?>-wrap">
                    <span id="heartbeats-monitor-heart<?php echo $i ?>" class="glyphicon glyphicon-heart" aria-hidden="true"></span>
                    <span class="drone-hb-label"><?php echo htmlspecialchars($args["label{$i}"]) ?></span>
                </div>
            <?php
            }
            ?>
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
                let _heartbeats = {};
                let _heartbeats_override = {};
                
                <?php
                for ($i = 1; $i <= 4; $i++) {
                    if (is_null($args["topic{$i}"]) || strlen($args["topic{$i}"]) <= 0)
                        continue;
                    $override_name = trim($args["override{$i}"] ?? '');
                    ?>
                    _heartbeats['<?php echo "heart{$i}" ?>'] = 0.0;
                    (new ROSLIB.Topic({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: '<?php echo $args["topic{$i}"] ?>',
                        messageType: 'std_msgs/Empty',
                        queue_size: 1,
                        throttle_rate: <?php echo 1000 / $args['frequency'] ?>
                    })).subscribe(function (message) {
                        _heartbeats['<?php echo "heart{$i}" ?>'] = seconds_since_epoch();
                        _heartbeat_set_state("<?php echo "heart{$i}" ?>", "ok");
                    });
                    <?php if (strlen($override_name) > 0) { ?>
                    let <?php echo "heart{$i}" ?> = new ROSLIB.Param({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: '<?php echo $override_name ?>',
                    });
                    <?php echo "heart{$i}" ?>.get((v) => {
                        _heartbeats_override['<?php echo "heart{$i}" ?>'] = v;
                    });

                    $("#<?php echo $id ?> #heartbeats-monitor-<?php echo "heart{$i}" ?>").on("click", () => {
                        _heartbeats_override['<?php echo "heart{$i}" ?>'] = !(_heartbeats_override['<?php echo "heart{$i}" ?>'] ?? true);
                        <?php echo "heart{$i}" ?>.set(_heartbeats_override['<?php echo "heart{$i}" ?>']);
                    });
                    <?php } ?>
                    <?php
                }
                ?>
                
                function _heartbeat_set_state(label, state) {
                    let $item = $("#<?php echo $id ?> .heartbeats-monitor-" + label);
                    $item.removeClass("is-ok is-stale is-warn is-bad");
                    if (state === "ok") $item.addClass("is-ok");
                    else if (state === "warn") $item.addClass("is-warn");
                    else if (state === "bad") $item.addClass("is-bad");
                    else $item.addClass("is-stale");
                }
                
                function _update_heartbeats_monitor(){
                    for (let heart in _heartbeats) {
                        let t = _heartbeats[heart];
                        if (seconds_since_epoch() - t > <?php echo $args["threshold"] ?>) {
                            let state = "stale";
                            if (heart in _heartbeats_override) {
                                state = (_heartbeats_override[heart]) ? "bad" : "warn";
                            }
                            _heartbeat_set_state(heart, state);
                        }
                    }
                }

                setInterval(_update_heartbeats_monitor, 1000 * (1.0 / <?php echo $args['frequency'] ?>))
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
    }//render
    
}//Duckiedrone_Heartbeats_Monitor
?>
