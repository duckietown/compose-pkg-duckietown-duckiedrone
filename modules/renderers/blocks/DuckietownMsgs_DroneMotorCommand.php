<?php

use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class DuckietownMsgs_DroneMotorCommand extends BlockRenderer {
    
    static protected $ICON = [
        "class" => "fa",
        "name" => "exchange"
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
        "message_type" => [
            "name" => "ROS Message Type",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "fallback_topic" => [
            "name" => "Fallback ROS Topic",
            "type" => "text",
            "mandatory" => False,
            "default" => "/mavros/rc/out"
        ],
        "fps" => [
            "name" => "Update frequency (Hz)",
            "type" => "numeric",
            "mandatory" => True,
            "default" => 5
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
        ]
    ];
    
    protected static function render($id, &$args) {
        $topic_name = trim($args['topic'] ?? '');
        $message_type = trim($args['message_type'] ?? '');
        $fallback_topic = trim($args['fallback_topic'] ?? '/mavros/rc/out');
        ?>
        <canvas class="resizable" style="width:100%; height:95%; padding:6px 16px"></canvas>
        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        ?>

        <script type="text/javascript">
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                const legacyMessageType = 'duckietown_msgs/DroneMotorCommand';
                const mavrosMessageType = 'mavros_msgs/RCOut';
                const topicName = <?php echo json_encode($topic_name) ?>;
                const explicitMessageType = <?php echo json_encode($message_type) ?>;
                const fallbackTopicName = <?php echo json_encode($fallback_topic) ?>;
                const resolvedMessageType = explicitMessageType.length > 0
                    ? explicitMessageType
                    : (topicName === '/mavros/rc/out' ? mavrosMessageType : legacyMessageType);

                let time_horizon_secs = 20;
                let color = Chart.helpers.color;
                let chart_config = {
                    type: 'line',
                    data: {
                        labels: range(time_horizon_secs - 1, 0, 1),
                        datasets: [{
                            label: 'Motor 1',
                            backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.red,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(<?php echo $args["min_value"] ?>)
                        }, {
                            label: 'Motor 2',
                            backgroundColor: color(window.chartColors.blue).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.blue,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(<?php echo $args["min_value"] ?>)
                        }, {
                            label: 'Motor 3',
                            backgroundColor: color(window.chartColors.green).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.green,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(<?php echo $args["min_value"] ?>)
                        }, {
                            label: 'Motor 4',
                            backgroundColor: color(window.chartColors.purple).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.purple,
                            fill: true,
                            data: new Array(time_horizon_secs).fill(<?php echo $args["min_value"] ?>)
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
                                    labelString: 'PWM'
                                },
                                ticks: {
                                    suggestedMin: <?php echo $args['min_value'] ?>,
                                    suggestedMax: <?php echo $args['max_value'] ?>
                                }
                            }]
                        },
                        tooltips: {
                            enabled: false
                        },
                        maintainAspectRatio: false
                    }
                };
                // create chart obj
                let ctx = $("#<?php echo $id ?> .block_renderer_container canvas")[0].getContext('2d');
                let chart = new Chart(ctx, chart_config);
                window.mission_control_page_blocks_data['<?php echo $id ?>'] = {
                    chart: chart,
                    config: chart_config,
                    activeSource: null
                };

                function updateChart(values, sourceId) {
                    // get chart
                    let chart_desc = window.mission_control_page_blocks_data['<?php echo $id ?>'];
                    if (chart_desc.activeSource !== null && chart_desc.activeSource !== sourceId) {
                        return;
                    }
                    chart_desc.activeSource = sourceId;
                    let chart = chart_desc.chart;
                    let config = chart_desc.config;
                    let yAxisTicks = config.options.scales.yAxes[0].ticks;
                    let observedMin = Math.min(values[0], values[1], values[2], values[3]);
                    let observedMax = Math.max(values[0], values[1], values[2], values[3]);
                    if (observedMin < yAxisTicks.suggestedMin) {
                        yAxisTicks.suggestedMin = observedMin;
                    }
                    if (observedMax > yAxisTicks.suggestedMax) {
                        yAxisTicks.suggestedMax = observedMax;
                    }
                    // cut the time horizon to `time_horizon_secs` points
                    config.data.datasets[0].data.shift();
                    config.data.datasets[1].data.shift();
                    config.data.datasets[2].data.shift();
                    config.data.datasets[3].data.shift();
                    // add new Y
                    config.data.datasets[0].data.push(values[0]);
                    config.data.datasets[1].data.push(values[1]);
                    config.data.datasets[2].data.push(values[2]);
                    config.data.datasets[3].data.push(values[3]);
                    // refresh chart
                    chart.update();
                }

                function subscribeToTopic(topic, messageType, sourceId) {
                    if (topic.length <= 0 || messageType.length <= 0) {
                        return;
                    }

                    let subscriber = new ROSLIB.Topic({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: topic,
                        messageType: messageType,
                        queue_size: 1,
                        throttle_rate: <?php echo 1000 / $args['fps'] ?>
                    });

                    subscriber.subscribe(function (message) {
                        if (messageType === mavrosMessageType) {
                            if (!message.channels || message.channels.length < 4) {
                                return;
                            }
                            updateChart([
                                message.channels[0],
                                message.channels[1],
                                message.channels[2],
                                message.channels[3]
                            ], sourceId);
                            return;
                        }

                        updateChart([
                            message.m1,
                            message.m2,
                            message.m3,
                            message.m4
                        ], sourceId);
                    });
                }

                subscribeToTopic(topicName, resolvedMessageType, 'primary');

                if (
                    resolvedMessageType === legacyMessageType &&
                    fallbackTopicName.length > 0 &&
                    fallbackTopicName !== topicName
                ) {
                    subscribeToTopic(fallbackTopicName, mavrosMessageType, 'fallback');
                }
            });
        </script>
        <?php
    }//render
    
}//DuckietownMsgs_DroneMotorCommand
?>
