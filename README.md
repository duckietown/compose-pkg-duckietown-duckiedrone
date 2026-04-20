# compose-pkg-duckietown-duckiedrone

`\compose\` package that provides the Duckiedrone-specific dashboard widgets (block renderers) and the default mission layout shown on the `/dashboard/robot/mission_control` page of the Duckiedrone device dashboard ([`robot/dt-device-dashboard`](../../robot/dt-device-dashboard)).

This package is consumed by the dashboard via [`dependencies-compose.txt`](../../robot/dt-device-dashboard/dependencies-compose.txt):

```
duckietown_duckiedrone==v2.1.0
```

The dashboard runs inside [`\compose\`](https://github.com/afdaniele/compose), a PHP package system. This repo is one of those packages.

---

## Package layout

```
compose-pkg-duckietown-duckiedrone/
├── VERSION                       # Semantic version — bumped on every release
├── metadata.json                 # Package name, description, compose compatibility
├── configuration/
│   └── schema.json               # Configurable-from-UI settings schema
├── modules/
│   └── renderers/
│       └── blocks/               # Widget definitions (PHP BlockRenderer classes)
│           ├── Duckiedrone_Arming.php         # class Mavros_Arming
│           ├── Duckiedrone_Control.php
│           ├── Duckiedrone_Heartbeat.php
│           ├── Duckiedrone_Heartbeats_Monitor.php
│           └── DuckietownMsgs_DroneMotorCommand.php
├── data/
│   └── private/
│       └── default_missions/
│           └── duckietown_duckiedrone_missions/
│               ├── default.json   # Mission layout shown by default
│               └── ...
├── css/, js/, images/            # Static assets served by the dashboard
├── scripts/                      # Optional helper scripts
├── post_install                  # Runs once on package install
├── post_update                   # Runs on install AND on package upgrade
├── bump-version.sh               # Helper to bump VERSION + tag
└── CHANGELOG.md                  # Keep in sync with VERSION on every release
```

### `post_install` / `post_update`

Both hooks run with `$COMPOSE_USERDATA_DIR` pointing at the dashboard's user-data directory (`/var/www/html/public_html/system/user-data` inside the container). `post_update` copies the mission files in `data/private/default_missions/` into `${USERDATA_DIR}/databases/data/`, which is the path the dashboard reads mission layouts from. `post_install` just delegates to `post_update`.

If you add a new default mission file, drop it in `data/private/default_missions/duckietown_duckiedrone_missions/` and it will be installed on the next build of the image (or on the next mount-and-reload loop, see **Development workflow** below).

---

## Widgets (block renderers)

Each PHP file under [modules/renderers/blocks/](modules/renderers/blocks/) defines one widget. A widget is a PHP class extending `\system\classes\BlockRenderer` with:

- `$ICON` — Font Awesome icon shown in the widget picker
- `$ARGUMENTS` — schema of user-configurable widget options (rendered into an HTML form when the user adds the widget to a mission)
- `render($id, &$args)` — the HTML + JS that gets emitted on the mission page

The `$id` is unique per widget instance; use it to namespace DOM ids and JS variables when a mission has more than one copy of the same widget.

### Current widgets

| File | Class | Purpose |
|---|---|---|
| `Duckiedrone_Arming.php` | `Mavros_Arming` | ARM/DISARM toggle + flight-mode toggle (OFFBOARD/ALTITUDE) + kill switch + takeoff button. Talks to mavros services. |
| `Duckiedrone_Control.php` | `Duckiedrone_Control` | Virtual joystick publishing to `~/mavros/manual_control/send`. |
| `Duckiedrone_Heartbeat.php` | `Duckiedrone_Heartbeat` | Single-topic heartbeat indicator. |
| `Duckiedrone_Heartbeats_Monitor.php` | `Duckiedrone_Heartbeats_Monitor` | Multi-topic heartbeat grid for joystick / altitude / state_estimator / pid. |
| `DuckietownMsgs_DroneMotorCommand.php` | `DuckietownMsgs_DroneMotorCommand` | Bar chart of the four motor PWM values. |

### Anatomy of a widget (writing a new one)

Minimal pattern:

```php
<?php

use \system\classes\Core;
use \system\classes\BlockRenderer;
use \system\packages\ros\ROS;

class My_Widget extends BlockRenderer {

    static protected $ICON = [
        "class" => "fa",
        "name"  => "rocket"
    ];

    static protected $ARGUMENTS = [
        "ros_hostname" => [
            "name"      => "ROSbridge hostname",
            "type"      => "text",
            "mandatory" => False,
            "default"   => ""          // empty -> resolved to current robot
        ],
        "topic" => [
            "name"      => "Topic",
            "type"      => "text",
            "mandatory" => True,
            "default"   => "~/my_topic"
        ],
        "frequency" => [
            "name"      => "Frequency (Hz)",
            "type"      => "number",
            "mandatory" => True,
            "default"   => 10
        ]
    ];

    protected static function render($id, &$args) {
        $host = ROS::sanitize_hostname($args["ros_hostname"]);
        ?>
        <div id="my_widget_<?php echo $id; ?>"></div>
        <script type="text/javascript">
            (function() {
                ROS.connect('<?php echo $host; ?>', function(ros) {
                    const topic = new ROSLIB.Topic({
                        ros: ros,
                        name: '<?php echo $args["topic"]; ?>',
                        messageType: 'std_msgs/String'
                    });
                    topic.subscribe(function(msg) {
                        document.getElementById('my_widget_<?php echo $id; ?>').innerText = msg.data;
                    });
                });
            })();
        </script>
        <?php
    }
}
?>
```

Key conventions:

- Always namespace DOM ids with `<?php echo $id; ?>` so multiple instances don't collide.
- Use `ROS::sanitize_hostname($args["ros_hostname"])` to get the rosbridge host. An empty `ros_hostname` means "current robot" — the dashboard resolves it server-side.
- Use `ROS::connect(host, callback)` in JS to share the singleton rosbridge connection; do not instantiate `ROSLIB.Ros` directly.
- Gate frequent work by checking `anybody_listening()` on the ROS side, not on the widget side — widgets are the consumer, not the publisher.

### Adding a widget to the default mission

Edit `data/private/default_missions/duckietown_duckiedrone_missions/default.json` and add a block entry:

```json
{
  "shape": { "rows": 1, "cols": 3 },
  "renderer": "My_Widget",
  "title": "My widget",
  "subtitle": "what it does",
  "args": {
    "ros_hostname": "",
    "topic": "/my_topic",
    "frequency": 10
  }
}
```

The `renderer` field must match the PHP class name (not the filename).

> **Known issue — `~/` path resolution.** The default mission currently ships with `~/mavros/...` paths for topics and services. On a virtual drone, rosbridge resolves `~` to `/<robot>/rosbridge_websocket`, not to `/`, so these services don't exist at that path and the corresponding widgets fail silently. Prefer absolute paths (`/mavros/cmd/arming`, `/mavros/state`, etc.) in new mission entries until the upstream `ROS::sanitize_hostname` behavior is fixed. See `docs/dashboard-test-report/README.md`.

---

## Development workflow

The dashboard image bakes this package in at build time via `dependencies-compose.txt`. For fast iteration you mount the local checkout into a running dashboard sandbox container, so PHP changes show up on page reload without a rebuild.

### 1. Build the dashboard image (once)

From [`robot/dt-device-dashboard`](../../robot/dt-device-dashboard):

```bash
dts devel build
```

This pulls `duckietown_duckiedrone==v2.1.0` (or whichever version is pinned). You need the package installed in the image at least once so that the autoload paths exist.

### 2. Run the sandbox with this package mounted

```bash
cd robot/dt-device-dashboard/sandbox
make run EXTRA_ARGS='-v "/workspaces/dt-env-developer/compose/compose-pkg-duckietown-duckiedrone:/user-data/packages/duckietown_duckiedrone:rw"'
```

The dashboard is then reachable at `http://localhost:8888`. PHP file changes are picked up on the next page load. Changes to `post_update` / default missions require a container restart because they only run on package install/update — or you can `docker exec` into the container and run `/user-data/packages/duckietown_duckiedrone/post_update` manually.

### 3. Or run against a real/virtual robot

Build and run on the robot (physical or virtual):

```bash
dts devel build -f -H ROBOT_NAME
dts devel run -H ROBOT_NAME
```

For a virtual drone, see the dashboard README's **Testing with a virtual Duckiedrone** section.

### 4. Bump version + changelog

```bash
./bump-version.sh        # bumps VERSION, creates git tag
```

Update `CHANGELOG.md` with a new entry, then bump the pin in [`robot/dt-device-dashboard/dependencies-compose.txt`](../../robot/dt-device-dashboard/dependencies-compose.txt) to the new tag. The dashboard image needs to be rebuilt and redeployed for the new version to ship to robots.

### 5. Deploy

1. Tag and push this package: the `ente` branch tag (e.g. `v2.1.1`) is what `dependencies-compose.txt` references.
2. In `dt-device-dashboard`, bump `duckietown_duckiedrone==v2.1.1` in `dependencies-compose.txt`, commit, push.
3. Rebuild and publish the dashboard image; it is then picked up the next time `dts duckiebot update ROBOT_NAME` runs on a robot.

---

## Conventions

- Widget class name = PHP filename stem, but *may* differ when we're renaming (e.g. `Duckiedrone_Arming.php` defines `class Mavros_Arming`). The `renderer` key in mission JSON uses the class name.
- Use `snake_case` for `$ARGUMENTS` keys; they map 1:1 to `args` in the mission JSON.
- Color/style args should pass through `data-*` attributes rather than inline styles when possible — avoids PHP string escaping bugs.
- Prefer absolute ROS paths (`/mavros/...`) in default missions; see the note above.
