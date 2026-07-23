<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class Duckiedrone_IMU_Orientation extends BlockRenderer {

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
        "topic" => [
            "name" => "ROS Topic",
            "type" => "text",
            "mandatory" => True
        ],
        "gyro_service" => [
            "name" => "PX4 gyro calibration service",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/px4_calibration/calibrate_gyro"
        ],
        "level_service" => [
            "name" => "PX4 level-horizon calibration service",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/px4_calibration/calibrate_level"
        ],
        "status_topic" => [
            "name" => "PX4 calibration status topic",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/px4_calibration/status"
        ],
        "fps" => [
            "name" => "Update frequency (Hz)",
            "type" => "numeric",
            "mandatory" => True,
            "default" => 5
        ]
    ];

    protected static function render($id, &$args) {
        ?>
        <div class="duckiedrone-imu-toolbar">
            <button type="button" class="btn btn-primary btn-sm" id="px4_calibrate_gyro">
                <i class="fa fa-crosshairs" aria-hidden="true"></i>
                GYRO
            </button>
            <button type="button" class="btn btn-info btn-sm" id="px4_calibrate_level">
                <i class="fa fa-balance-scale" aria-hidden="true"></i>
                LEVEL
            </button>
        </div>
        <div id="px4_calibration_status" class="duckiedrone-imu-status">PX4 calibration idle</div>
        <canvas class="resizable duckiedrone-imu-chart"></canvas>
        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        ?>

        <script src="<?php echo Core::getJSscriptURL('rosdb.js', 'ros') ?>"></script>

        <script type="text/javascript">
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                let status_box = $("#<?php echo $id ?> #px4_calibration_status");
                let gyro_btn = $("#<?php echo $id ?> #px4_calibrate_gyro");
                let level_btn = $("#<?php echo $id ?> #px4_calibrate_level");

                function normalize_ros_name(name) {
                    return name.replace(/^~\/+/, '/').replace(/^\/+/, '/');
                }

                let subscriber = new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: normalize_ros_name('<?php echo $args['topic'] ?>'),
                    messageType: 'sensor_msgs/Imu',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['fps'] ?>
                });

                let gyro_srv = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: normalize_ros_name('<?php echo $args['gyro_service'] ?>'),
                    serviceType: 'std_srvs/srv/Trigger'
                });
                let level_srv = new ROSLIB.Service({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: normalize_ros_name('<?php echo $args['level_service'] ?>'),
                    serviceType: 'std_srvs/srv/Trigger'
                });
                let status_topic = new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: normalize_ros_name('<?php echo $args['status_topic'] ?>'),
                    messageType: 'std_msgs/msg/String',
                    queue_size: 10
                });

                function set_busy(busy) {
                    gyro_btn.prop('disabled', busy);
                    level_btn.prop('disabled', busy);
                }

                function set_status(text, class_name) {
                    status_box
                        .removeClass('duckiedrone-imu-status-error duckiedrone-imu-status-success')
                        .addClass(class_name || '')
                        .text(text);
                }

                function call_calibration(service, label) {
                    set_busy(true);
                    set_status('Starting PX4 ' + label + ' calibration...');
                    service.callService(new ROSLIB.ServiceRequest({}), function (response) {
                        set_busy(false);
                        set_status(
                            response.message || (response.success ? 'PX4 ' + label + ' calibration complete' : 'PX4 ' + label + ' calibration failed'),
                            response.success ? 'duckiedrone-imu-status-success' : 'duckiedrone-imu-status-error'
                        );
                    }, function (error) {
                        set_busy(false);
                        set_status('Service error: ' + error, 'duckiedrone-imu-status-error');
                    });
                }

                gyro_btn.off().click(function () {
                    call_calibration(gyro_srv, 'gyro');
                });
                level_btn.off().click(function () {
                    call_calibration(level_srv, 'level');
                });
                status_topic.subscribe(function (message) {
                    set_status(message.data);
                });

                let time_horizon_secs = 20;
                let color = Chart.helpers.color;
                let chart_config = {
                    type: 'line',
                    data: {
                        labels: range(time_horizon_secs - 1, 0, 1),
                        datasets: [{
                            label: 'Roll',
                            backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.red,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        }, {
                            label: 'Pitch',
                            backgroundColor: color(window.chartColors.green).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.green,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        }, {
                            label: 'Yaw',
                            backgroundColor: color(window.chartColors.blue).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.blue,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(0)
                        }]
                    },
                    options: {
                        scales: {
                            xAxes: [{
                                scaleLabel: {
                                    display: false
                                }
                            }],
                            yAxes: [{
                                scaleLabel: {
                                    display: true,
                                    labelString: 'deg'
                                },
                                ticks: {
                                    suggestedMin: -180,
                                    suggestedMax: 180,
                                    stepSize: 45
                                }
                            }]
                        },
                        tooltips: {
                            enabled: false
                        },
                        maintainAspectRatio: false
                    }
                };
                let ctx = $("#<?php echo $id ?> .block_renderer_container canvas")[0].getContext('2d');
                let chart = new Chart(ctx, chart_config);
                window.mission_control_page_blocks_data['<?php echo $id ?>'] = {
                    chart: chart,
                    config: chart_config
                };

                subscriber.subscribe(function (message) {
                    let chart_desc = window.mission_control_page_blocks_data['<?php echo $id ?>'];
                    let chart = chart_desc.chart;
                    let config = chart_desc.config;
                    config.data.datasets[0].data.shift();
                    config.data.datasets[1].data.shift();
                    config.data.datasets[2].data.shift();
                    let q = [
                        message.orientation.w,
                        message.orientation.x,
                        message.orientation.y,
                        message.orientation.z,
                    ];
                    let rpy = eulerFromQuaternion(q, "XYZ");
                    config.data.datasets[0].data.push(rpy[0] * (180/Math.PI));
                    config.data.datasets[1].data.push(rpy[1] * (180/Math.PI));
                    config.data.datasets[2].data.push(rpy[2] * (180/Math.PI));
                    chart.update();
                });
            });

            function eulerFromQuaternion( quaternion, order ) {
                const w = quaternion[0], x = quaternion[1], y = quaternion[2], z = quaternion[3];
                const x2 = x + x, y2 = y + y, z2 = z + z;
                const xx = x * x2, xy = x * y2, xz = x * z2;
                const yy = y * y2, yz = y * z2, zz = z * z2;
                const wx = w * x2, wy = w * y2, wz = w * z2;
                const matrix = [
                    1 - ( yy + zz ), xy + wz, xz - wy, 0,
                    xy - wz, 1 - ( xx + zz ), yz + wx, 0,
                    xz + wy, yz - wx, 1 - ( xx + yy ), 0,
                    0, 0, 0, 1
                ];
                function clamp( value, min, max ) {
                    return Math.max( min, Math.min( max, value ) );
                }
                const m11 = matrix[ 0 ], m12 = matrix[ 4 ], m13 = matrix[ 8 ];
                const m21 = matrix[ 1 ], m22 = matrix[ 5 ], m23 = matrix[ 9 ];
                const m31 = matrix[ 2 ], m32 = matrix[ 6 ], m33 = matrix[ 10 ];
                var euler = [ 0, 0, 0 ];
                switch ( order ) {
                    case "XYZ":
                        euler[1] = Math.asin( clamp( m13, - 1, 1 ) );
                        if ( Math.abs( m13 ) < 0.9999999 ) {
                            euler[0] = Math.atan2( - m23, m33 );
                            euler[2] = Math.atan2( - m12, m11 );
                        } else {
                            euler[0] = Math.atan2( m32, m22 );
                            euler[2] = 0;
                        }
                        break;
                }
                return euler;
            }
        </script>

        <?php
        ROS::connect($ros_hostname);
        ?>

        <style type="text/css">
            #<?php echo $id ?> .duckiedrone-imu-toolbar {
                position: absolute;
                right: 8px;
                top: 45px;
                z-index: 2;
                display: flex;
                gap: 6px;
            }
            #<?php echo $id ?> .duckiedrone-imu-toolbar .btn {
                min-width: 72px;
                font-size: 9pt;
            }
            #<?php echo $id ?> .duckiedrone-imu-status {
                position: absolute;
                left: 16px;
                right: 176px;
                top: 48px;
                min-height: 31px;
                max-height: 58px;
                overflow: auto;
                white-space: normal;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 4px 8px;
                background: #fafafa;
                color: #333;
                font-size: 8pt;
                line-height: 1.2;
                z-index: 2;
            }
            #<?php echo $id ?> .duckiedrone-imu-status-error {
                color: #a94442;
                border-color: #ebccd1;
                background: #f2dede;
            }
            #<?php echo $id ?> .duckiedrone-imu-status-success {
                color: #3c763d;
                border-color: #d6e9c6;
                background: #dff0d8;
            }
            #<?php echo $id ?> .duckiedrone-imu-chart {
                width: 100%;
                height: 95%;
                min-height: 150px;
                padding: 44px 16px 6px 16px;
            }
        </style>
        <?php
    }//render
}
?>
