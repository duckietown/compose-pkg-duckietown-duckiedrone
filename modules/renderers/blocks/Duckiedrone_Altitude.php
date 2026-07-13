<?php

use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class Duckiedrone_Altitude extends BlockRenderer {

    static protected $ICON = [
        "class" => "fa",
        "name" => "crosshairs"
    ];

    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "topic" => [
            "name" => "ROS Topic (Altitude)",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/altitude_node/altitude"
        ],
        "reference" => [
            "name" => "ROS Topic (Reference)",
            "type" => "text",
            "mandatory" => False,
            "default" => "~/pid_controller_node/desired/height"
        ],
        "label" => [
            "name" => "Label",
            "type" => "text",
            "mandatory" => True,
            "default" => "Altitude"
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
        <canvas class="resizable" style="width:100%; height:95%; padding:6px 16px"></canvas>
        <?php
        $ros_hostname = $args['ros_hostname'] ?? null;
        $ros_hostname = ROS::sanitize_hostname($ros_hostname);
        $connected_evt = ROS::get_event(ROS::$ROSBRIDGE_CONNECTED, $ros_hostname);
        $reference_topic = trim($args['reference'] ?? '');
        $has_reference_topic = strlen($reference_topic) > 0;
        ?>

        <script type="text/javascript">
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                let altitude_topic = new ROSLIB.Topic({
                    ros: window.ros['<?php echo $ros_hostname ?>'],
                    name: '<?php echo $args['topic'] ?>',
                    messageType: 'sensor_msgs/Range',
                    queue_size: 1,
                    throttle_rate: <?php echo 1000 / $args['fps'] ?>
                });

                let has_reference_topic = <?php echo $has_reference_topic ? 'true' : 'false' ?>;
                let reference_topic_name = <?php echo json_encode($reference_topic) ?>;
                let reference_value = null;
                let time_horizon_secs = 20;
                let color = Chart.helpers.color;
                let chart_config = {
                    type: 'line',
                    data: {
                        labels: range(time_horizon_secs - 1, 0, 1),
                        datasets: [{
                            label: '<?php echo $args['label'] ?>',
                            backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.red,
                            fill: true,
                            pointRadius: 0,
                            data: new Array(time_horizon_secs).fill(null)
                        }, {
                            label: 'Reference',
                            backgroundColor: color(window.chartColors.blue).alpha(0.5).rgbString(),
                            borderColor: window.chartColors.blue,
                            fill: false,
                            hidden: !has_reference_topic,
                            pointRadius: 0,
                            data: new Array(time_horizon_secs).fill(null)
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
                                    labelString: 'meters'
                                },
                                ticks: {
                                    suggestedMin: 0,
                                    suggestedMax: 1,
                                    maxTicksLimit: 6
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

                function push_value(dataset, value) {
                    dataset.data.shift();
                    dataset.data.push(value);
                }

                function numeric_values(values) {
                    let out = [];
                    let index = 0;

                    for (index = 0; index < values.length; index += 1) {
                        let value = values[index];
                        if (typeof value !== 'number') {
                            continue;
                        }
                        if (!isFinite(value)) {
                            continue;
                        }
                        out.push(value);
                    }

                    return out;
                }

                function update_y_axis(config) {
                    let ticks = config.options.scales.yAxes[0].ticks;
                    let altitude_values = config.data.datasets[0].data.slice();
                    let reference_values = config.data.datasets[1].data.slice();
                    let values = altitude_values.concat(reference_values);
                    let plotted_values = numeric_values(values);
                    let observed_min = 0.0;
                    let observed_max = 1.0;
                    let span = 0.0;
                    let padding = 0.0;
                    let suggested_min = 0.0;
                    let suggested_max = 1.0;

                    if (plotted_values.length > 0) {
                        // Always include ground level so zero altitude stays visible on the chart.
                        plotted_values.push(0.0);
                        observed_min = Math.min.apply(null, plotted_values);
                        observed_max = Math.max.apply(null, plotted_values);
                    }

                    span = observed_max - observed_min;
                    if (span < 0.5) {
                        span = 0.5;
                    }

                    padding = span * 0.1;
                    suggested_min = observed_min - padding;
                    suggested_max = observed_max + padding;

                    if (observed_min >= 0.0) {
                        suggested_min = Math.max(0.0, suggested_min);
                    }
                    if (suggested_max < 1.0) {
                        suggested_max = 1.0;
                    }

                    ticks.suggestedMin = Number(suggested_min.toFixed(2));
                    ticks.suggestedMax = Number(suggested_max.toFixed(2));
                }

                altitude_topic.subscribe(function (message) {
                    let chart_desc = window.mission_control_page_blocks_data['<?php echo $id ?>'];
                    let chart = chart_desc.chart;
                    let config = chart_desc.config;
                    let reference_sample = null;

                    if (has_reference_topic) {
                        reference_sample = reference_value;
                    }

                    push_value(config.data.datasets[0], message.range);
                    push_value(config.data.datasets[1], reference_sample);
                    update_y_axis(config);
                    chart.update();
                });

                if (has_reference_topic && reference_topic_name.length > 0) {
                    let reference_topic = new ROSLIB.Topic({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: reference_topic_name,
                        messageType: 'std_msgs/Float32',
                        queue_size: 1,
                        throttle_rate: <?php echo 1000 / $args['fps'] ?>
                    });

                    reference_topic.subscribe(function (message) {
                        reference_value = message.data;
                    });
                }
            });
        </script>
        <?php
    }

}
?>