<?php

use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;


class Duckiedrone_TimeOfFlight extends BlockRenderer {

    static protected $ICON = [
        "class" => "fa",
        "name" => "arrows-v"
    ];

    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name" => "ROSbridge hostname",
            "type" => "text",
            "mandatory" => False,
            "default" => ""
        ],
        "topic_bottom" => [
            "name" => "ROS Topic (Bottom)",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/bottom_tof_driver_node/range"
        ],
        "topic_front" => [
            "name" => "ROS Topic (Front)",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/front_tof_driver_node/range"
        ],
        "topic_left" => [
            "name" => "ROS Topic (Left)",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/left_tof_driver_node/range"
        ],
        "topic_right" => [
            "name" => "ROS Topic (Right)",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/right_tof_driver_node/range"
        ],
        "topic_top" => [
            "name" => "ROS Topic (Top)",
            "type" => "text",
            "mandatory" => True,
            "default" => "~/top_tof_driver_node/range"
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
        ?>

        <script type="text/javascript">
            $(document).on("<?php echo $connected_evt ?>", function (evt) {
                let sensor_specs = [{
                    key: 'bottom',
                    label: 'Bottom',
                    topic: '<?php echo $args['topic_bottom'] ?>',
                    color: window.chartColors.red
                }, {
                    key: 'front',
                    label: 'Front',
                    topic: '<?php echo $args['topic_front'] ?>',
                    color: window.chartColors.orange
                }, {
                    key: 'left',
                    label: 'Left',
                    topic: '<?php echo $args['topic_left'] ?>',
                    color: window.chartColors.green
                }, {
                    key: 'right',
                    label: 'Right',
                    topic: '<?php echo $args['topic_right'] ?>',
                    color: window.chartColors.blue
                }, {
                    key: 'top',
                    label: 'Top',
                    topic: '<?php echo $args['topic_top'] ?>',
                    color: window.chartColors.purple
                }];
                let time_horizon_secs = 20;
                let color = Chart.helpers.color;
                let chart_config = {
                    type: 'line',
                    data: {
                        labels: range(time_horizon_secs - 1, 0, 1),
                        datasets: sensor_specs.map(function (sensor) {
                            return {
                                label: sensor.label,
                                backgroundColor: color(sensor.color).alpha(0.5).rgbString(),
                                borderColor: sensor.color,
                                fill: false,
                                pointRadius: 0,
                                data: new Array(time_horizon_secs).fill(null)
                            };
                        })
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
                                    suggestedMax: 2,
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
                    let values = [];
                    let dataset_index = 0;
                    let plotted_values = [];
                    let observed_max = 2.0;
                    let padding = 0.0;
                    let suggested_max = 2.0;

                    for (dataset_index = 0; dataset_index < config.data.datasets.length; dataset_index += 1) {
                        values = values.concat(config.data.datasets[dataset_index].data);
                    }

                    plotted_values = numeric_values(values);
                    if (plotted_values.length > 0) {
                        plotted_values.push(0.0);
                        observed_max = Math.max.apply(null, plotted_values);
                    }

                    padding = Math.max(observed_max * 0.1, 0.1);
                    suggested_max = observed_max + padding;
                    if (suggested_max < 0.5) {
                        suggested_max = 0.5;
                    }
                    if (suggested_max < 2.0) {
                        suggested_max = 2.0;
                    }

                    ticks.suggestedMin = 0.0;
                    ticks.suggestedMax = Number(suggested_max.toFixed(2));
                }

                function sanitize_range(message) {
                    let value = message.range;

                    if (typeof value !== 'number') {
                        return null;
                    }
                    if (!isFinite(value)) {
                        return null;
                    }
                    if (typeof message.min_range === 'number' && value < message.min_range) {
                        return null;
                    }
                    if (typeof message.max_range === 'number' && value > message.max_range) {
                        return null;
                    }

                    return value;
                }

                sensor_specs.forEach(function (sensor, sensor_index) {
                    let topic = new ROSLIB.Topic({
                        ros: window.ros['<?php echo $ros_hostname ?>'],
                        name: sensor.topic,
                        messageType: 'sensor_msgs/Range',
                        queue_size: 1,
                        throttle_rate: <?php echo 1000 / $args['fps'] ?>
                    });

                    topic.subscribe(function (message) {
                        let chart_desc = window.mission_control_page_blocks_data['<?php echo $id ?>'];
                        let chart = chart_desc.chart;
                        let config = chart_desc.config;

                        push_value(config.data.datasets[sensor_index], sanitize_range(message));
                        update_y_axis(config);
                        chart.update();
                    });
                });
            });
        </script>
        <?php
        ROS::connect($ros_hostname);
    }

}
?>