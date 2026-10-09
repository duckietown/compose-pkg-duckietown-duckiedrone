<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class Duckiedrone_Heartbeat extends BlockRenderer {
    
    static protected $ICON = [
        "class" => "glyphicon",
        "name" => "heart"
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
            "mandatory" => True
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
        <div class="drone-hb-pulse resizable">
            <div class="drone-hb-pulse-card">
                <img src="<?php echo Core::getImageURL('heartbeat.gif', 'duckietown_duckiedrone') ?>"
                     alt="Joystick heartbeat">
                <span class="drone-hb-pulse-label">Publishing</span>
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
                // Publish joystick heartbeat so onboard watchers stay alive.
                let topic = new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['topic'] ?>',
                    messageType: 'std_msgs/Empty',
                    queue_size: 1
                });

                setInterval(function () {
                    let msg = new ROSLIB.Message({});
                    topic.publish(msg);
                }, 1000 * (1.0 / <?php echo $args['frequency'] ?>))
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
    
}//Duckiedrone_Heartbeat
?>
