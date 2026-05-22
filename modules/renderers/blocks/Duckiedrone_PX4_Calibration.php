<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;

class Duckiedrone_PX4_Calibration extends BlockRenderer {

    static protected $ICON = [
        "class" => "fa",
        "name" => "compass"
    ];

    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "gyro_service" => [
            "name" => "Gyro calibration service",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/px4_calibration/calibrate_gyro"
        ],
        "accel_service" => [
            "name" => "Accel calibration service",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/px4_calibration/calibrate_accel"
        ],
        "status_topic" => [
            "name" => "Calibration status topic",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/px4_calibration/status"
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
        <div class="px4-calibration-block">
            <div class="px4-calibration-actions">
                <button type="button" class="btn btn-primary btn-xs" id="px4_calibrate_gyro">
                    <i class="fa fa-crosshairs" style="margin-right: 4px;"></i>GYRO
                </button>
                <button type="button" class="btn btn-warning btn-xs" id="px4_calibrate_accel">
                    <i class="fa fa-cube" style="margin-right: 4px;"></i>ACCEL
                </button>
            </div>
            <div id="px4_calibration_status" class="px4-calibration-status">
                Idle
            </div>
        </div>

        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        ?>

        <script src="<?php echo Core::getJSscriptURL('rosdb.js', 'ros') ?>"></script>

        <script type="text/javascript">
            $(document).on("<?php echo $connected_evt ?>", function () {
                let status_box = $('#<?php echo $id ?> #px4_calibration_status');
                let gyro_btn = $('#<?php echo $id ?> #px4_calibrate_gyro');
                let accel_btn = $('#<?php echo $id ?> #px4_calibrate_accel');

                let gyro_srv = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['gyro_service'] ?>',
                    serviceType: 'std_srvs/srv/Trigger'
                });
                let accel_srv = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['accel_service'] ?>',
                    serviceType: 'std_srvs/srv/Trigger'
                });
                let status_topic = new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['status_topic'] ?>',
                    messageType: 'std_msgs/msg/String',
                    queue_size: 10
                });

                function set_busy(busy) {
                    gyro_btn.prop('disabled', busy);
                    accel_btn.prop('disabled', busy);
                }

                function call_calibration(service, label) {
                    set_busy(true);
                    status_box.removeClass('px4-calibration-error px4-calibration-success');
                    status_box.text('Starting ' + label + ' calibration...');
                    service.callService(new ROSLIB.ServiceRequest({}), function (response) {
                        set_busy(false);
                        status_box
                            .toggleClass('px4-calibration-success', response.success)
                            .toggleClass('px4-calibration-error', !response.success)
                            .text(response.message || (response.success ? label + ' calibration complete' : label + ' calibration failed'));
                    }, function (error) {
                        set_busy(false);
                        status_box
                            .addClass('px4-calibration-error')
                            .text('Service error: ' + error);
                    });
                }

                gyro_btn.off().click(function () {
                    call_calibration(gyro_srv, 'gyro');
                });
                accel_btn.off().click(function () {
                    call_calibration(accel_srv, 'accel');
                });
                status_topic.subscribe(function (message) {
                    status_box.text(message.data);
                });
            });
        </script>

        <?php
        ROS::connect($ros_hostname);
        ?>

        <style type="text/css">
            #<?php echo $id ?> {
                background-color: <?php echo $args['background_color'] ?>;
            }
            #<?php echo $id ?> .px4-calibration-block {
                display: flex;
                flex-direction: column;
                justify-content: center;
                height: 100%;
                padding: 6px;
                box-sizing: border-box;
                gap: 6px;
            }
            #<?php echo $id ?> .px4-calibration-actions {
                display: flex;
                justify-content: center;
                gap: 6px;
            }
            #<?php echo $id ?> .px4-calibration-actions button {
                min-width: 82px;
                font-size: 9pt;
            }
            #<?php echo $id ?> .px4-calibration-status {
                min-height: 42px;
                overflow: auto;
                font-size: 8pt;
                line-height: 1.25;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 4px;
                color: #333;
                background: #fafafa;
            }
            #<?php echo $id ?> .px4-calibration-error {
                color: #a94442;
                border-color: #ebccd1;
                background: #f2dede;
            }
            #<?php echo $id ?> .px4-calibration-success {
                color: #3c763d;
                border-color: #d6e9c6;
                background: #dff0d8;
            }
        </style>
        <?php
    }
}
?>
