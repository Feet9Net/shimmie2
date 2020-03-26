<?php declare(strict_types=1);

class ET extends Extension
{
    /** @var ETTheme */
    protected $theme;

    public function onPageRequest(PageRequestEvent $event)
    {
        global $user;
        if ($event->page_matches("system_info")) {
            if ($user->can(Permissions::VIEW_SYSINTO)) {
                $this->theme->display_info_page($this->to_yaml($this->get_info()));
            }
        }
    }

    public function onPageSubNavBuilding(PageSubNavBuildingEvent $event)
    {
        global $user;
        if ($event->parent==="system") {
            if ($user->can(Permissions::VIEW_SYSINTO)) {
                $event->add_nav_link("system_info", new Link('system_info'), "System Info", null, 10);
            }
        }
    }

    public function onUserBlockBuilding(UserBlockBuildingEvent $event)
    {
        global $user;
        if ($user->can(Permissions::VIEW_SYSINTO)) {
            $event->add_link("System Info", make_link("system_info"), 99);
        }
    }

    public function onCommand(CommandEvent $event)
    {
        if ($event->cmd == "help") {
            print "\tget-info\n";
            print "\t\tList a bunch of info\n\n";
        }
        if ($event->cmd == "info") {
            print($this->to_yaml($this->get_info()));
        }
    }

    /**
     * Collect the information and return it in a keyed array.
     */
    private function get_info(): array
    {
        global $config, $database;

        $core_exts = ExtensionInfo::get_core_extensions();
        $extra_exts = [];
        foreach (ExtensionInfo::get_all() as $info) {
            if ($info->is_enabled() && !in_array($info->key, $core_exts)) {
                $extra_exts[] = $info->key;
            }
        }

        $info = [
            "about" => [
                'title' => $config->get_string(SetupConfig::TITLE),
                'theme' => $config->get_string(SetupConfig::THEME),
                'url'   => "http://" . $_SERVER["HTTP_HOST"] . get_base_href(),
            ],
            "versions" => [
                'shimmie' => VERSION,
                'schema'  => $config->get_int("db_version"),
                'php'     => phpversion(),
                'db'      => $database->get_driver_name() . " " . $database->get_version(),
                'os'      => php_uname(),
                'server'  => isset($_SERVER["SERVER_SOFTWARE"]) ? $_SERVER["SERVER_SOFTWARE"] : 'unknown',
            ],
            "extensions" => [
                "core" => $core_exts,
                "extra" => $extra_exts,
                "handled_extensions" => DataHandlerExtension::get_all_supported_exts(),
            ],
            "stats" => [
                'images'   => (int)$database->get_one("SELECT COUNT(*) FROM images"),
                'comments' => (int)$database->get_one("SELECT COUNT(*) FROM comments"),
                'users'    => (int)$database->get_one("SELECT COUNT(*) FROM users"),
            ],
            "media" => [
                "memory_limit" => to_shorthand_int($config->get_int(MediaConfig::MEM_LIMIT)),
                "disk_use" => to_shorthand_int((int)disk_total_space("./") - (int)disk_free_space("./")),
                "disk_total" => to_shorthand_int((int)disk_total_space("./")),
            ],
            "thumbnails" => [
                "engine" => $config->get_string(ImageConfig::THUMB_ENGINE),
                "quality" => $config->get_int(ImageConfig::THUMB_QUALITY),
                "width" => $config->get_int(ImageConfig::THUMB_WIDTH),
                "height" => $config->get_int(ImageConfig::THUMB_HEIGHT),
                "scaling" => $config->get_int(ImageConfig::THUMB_SCALING),
                "type" => $config->get_string(ImageConfig::THUMB_TYPE),
            ],
        ];

        return $info;
    }

    private function to_yaml($info)
    {
        $data = "";
        foreach ($info as $title => $section) {
            $data .= "$title:\n";
            foreach ($section as $k => $v) {
                $data .= "  $k: " . json_encode($v, JSON_UNESCAPED_SLASHES) . "\n";
            }
            $data .= "\n";
        }
        return $data;
    }
}
