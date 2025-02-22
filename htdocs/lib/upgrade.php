<?php
/**
 *
 * @package    mahara
 * @subpackage core
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

defined('INTERNAL') || die();

/**
 * Class to use for installation exceptions
 */
class InstallationException extends SystemException {}

require_once('ddl.php');

/**
 * This function checks core and plugins for which need to be upgraded/installed
 *
 * Note: This function is sometimes executed during upgrades from
 * ancient databases.  Avoid rash assumptions about what's installed
 * or these upgrades may fail.
 *
 * @param string $name The name of the plugin to check. If no name is specified,
 *                     all plugins are checked.
 * @return mixed If a name is specified, an object will be returned with upgrade data
 *                     about the requested component (which can be "core", "local", or a plugin).
 *                     If the component desn't need to be updated, an empty array will be returned.
 *               If no name is specified, an array of such objects will be returned.
 *                     It will also include an array key "settings", which will be an array
 *                     that may contain metadata about the upgrade/install process.
 */
function check_upgrades($name=null) {

    $pluginstocheck = plugin_types();

    $toupgrade = array();
    $settings = array();
    $toupgradecount = 0;
    $newinstallcount = 0;
    $installing = false;
    $newinstalls = array();
    // This will be set in version.php below.
    $config = new stdClass();
    require('version.php');
    // check core first...
    if (empty($name) || $name == 'core') {
        try {
            $coreversion = get_config('version');
        }
        catch (Exception $e) {
            $coreversion = 0;
        }
        $corerelease = get_config('release');
        $core = new stdClass();
        $core->to = $config->version;
        $core->torelease = $config->release;
        $core->toseries = $config->series;
        $toupgrade['core'] = $core;
        if (empty($coreversion)) {
            if (is_mysql()) { // Show a more informative error message if using mysql with skip-innodb
                // In MySQL 5.6.x, we run the command 'SHOW ENGINES' to check if InnoDB is enabled or not
                global $db;
                $result = $db->Execute("SHOW ENGINES");
                $hasinnodb = false;
                while (!$result->EOF) {
                    if ($result->fields['Engine'] == 'InnoDB' && ($result->fields['Support'] == 'YES' || $result->fields['Support'] == 'DEFAULT')) {
                        $hasinnodb = true;
                        break;
                    }
                    $result->MoveNext();
                }

                if (!$hasinnodb) {
                    throw new ConfigSanityException("Mahara requires InnoDB tables.  Please ensure InnoDB tables are enabled in your MySQL server.");
                }
            }
            $core->install = true;
            $installing = true;
        }
        else if ($config->version > $coreversion) {
            if (isset($config->minupgradefrom) && isset($config->minupgraderelease)
                && $coreversion < $config->minupgradefrom) {
                throw new ConfigSanityException("Must upgrade to $config->minupgradefrom "
                                          . "($config->minupgraderelease) first "
                                          . " (you have $coreversion ($corerelease)");
            }
            $toupgradecount ++;
            $core->upgrade = true;
            $core->from = $coreversion;
            $core->fromrelease = $corerelease;
        }
        else if ($config->version < $coreversion) {
            // Core can't be upgraded. Remove it from the list!
            unset($toupgrade['core']);
            if (get_config('productionmode')) {
                throw new ConfigSanityException("Database version of Mahara $corerelease ($coreversion) is newer "
                                            . "than files version $config->release ($config->version). "
                                            . "Please make sure you have the correct Mahara files in place.");
            }
            else {
                if (!defined('SITEOUTOFSYNC')) {
                    define('SITEOUTOFSYNC', 'core');
                }
            }
        }
        else {
            // Core doesn't need to be upgraded. Remove it from the list!
            unset($toupgrade['core']);
        }
    }

    // If we were just checking if the core needed to be upgraded, we can stop here
    if ($name == 'core') {
        return $toupgrade['core'];
    }

    if (!$installing && (empty($name) || $name == 'local')) {
        $localversion = get_config('localversion');
        $localrelease = get_config('localrelease');
        if (is_null($localversion)) {
            $localversion = 0;
            $localrelease = 0;
        }

        $config = new stdClass();
        require(get_config('docroot') . 'local/version.php');

        if ($config->version > $localversion) {
            $toupgradecount ++;
            $toupgrade['local'] = (object) array(
                'upgrade'     => true,
                'from'        => $localversion,
                'fromrelease' => $localrelease,
                'to'          => $config->version,
                'torelease'   => $config->release,
            );
        }

        if ($name == 'local') {
            return $toupgrade['local'];
        }
    }

    $plugins = array();
    if (!empty($name)) {
        try {
            $bits = explode('.', $name);
            $pt = $bits[0];
            $pn = $bits[1];
            $pp = null;
            if ($pt == 'blocktype' && strpos($pn, '/') !== false) {
                $bits = explode('/', $pn);
                $pp = get_config('docroot') . 'artefact/' . $bits[0]  . '/blocktype/' . $bits[1];
            }
            validate_plugin($pt, $pn, $pp);
            $plugins[] = explode('.', $name);
        }
        catch (InstallationException $_e) {
            log_warn(get_string('pluginnotinstallable', 'mahara', $pt, $pn) . $_e->GetMessage());
        }
    }
    else {
        foreach ($pluginstocheck as $plugin) {
            $dirhandle = opendir(get_config('docroot') . $plugin);
            while (false !== ($dir = readdir($dirhandle))) {
                if (strpos($dir, '.') === 0 or 'CVS' == $dir) {
                    continue;
                }
                $plugin_dir = get_config('docroot') . $plugin . '/' . $dir;
                if (!is_dir($plugin_dir)) {
                    continue;
                }
                try {
                    validate_plugin($plugin, $dir);
                    $plugins[] = array($plugin, $dir);
                }
                catch (InstallationException $_e) {
                    log_warn(get_string('pluginnotinstallable', 'mahara', $plugin, $dir) . $_e->GetMessage(), true , false);
                }

                if ($plugin == 'artefact') { // go check it for blocks as well
                    $btlocation = $plugin_dir . '/blocktype';
                    if (!is_dir($btlocation)) {
                        continue;
                    }
                    $btdirhandle = opendir($btlocation);
                    while (false !== ($btdir = readdir($btdirhandle))) {
                        if (strpos($btdir, '.') === 0 or 'CVS' == $btdir) {
                            continue;
                        }
                        if (!is_dir(get_config('docroot') . $plugin . '/' . $dir . '/blocktype/' . $btdir)) {
                            continue;
                        }
                        $plugins[] = array('blocktype', $dir . '/' . $btdir);
                    }
                }
            }
        }
    }
    $outofsyncplugins = array();
    foreach ($plugins as $plugin) {
        $plugintype = $plugin[0];
        $pluginname = $plugin[1];
        $pluginpath = "$plugin[0]/$plugin[1]";
        $pluginkey  = "$plugin[0].$plugin[1]";

        if ($plugintype == 'blocktype' && strpos($pluginname, '/') !== false) {
            // sigh.. we're a bit special...
            $bits = explode('/', $pluginname);
            $pluginpath = 'artefact/' . $bits[0] . '/blocktype/' . $bits[1];
        }

        // Don't try to get the plugin info if we are installing - it will
        // definitely fail
        $pluginversion = 0;
        $pluginrelease = 0;
        if (!$installing && table_exists(new XMLDBTable($plugintype . '_installed'))) {
            if ($plugintype == 'blocktype' && strpos($pluginname, '/')) {
                $bits = explode('/', $pluginname);
                $installed = get_record('blocktype_installed', 'name', $bits[1], 'artefactplugin', $bits[0]);
            }
            else {
                $installed = get_record($plugintype . '_installed', 'name', $pluginname);
            }
            if ($installed) {
                $pluginversion = $installed->version;
                $pluginrelease =  $installed->release;
            }
        }

        $config = new stdClass();
        require(get_config('docroot') . $pluginpath . '/version.php');

        $classname = generate_class_name($plugintype, $pluginname);
        safe_require($plugintype, $pluginname);
        // Check if there is a displayname
        $plugindisplayname = $classname::get_plugin_display_name();

        if (empty($pluginversion)) {
            $newinstall = false;
            if (empty($installing) && $pluginkey != $name) {
                $newinstall = true;
            }
            $plugininfo = new stdClass();
            $plugininfo->install = true;
            $plugininfo->to = $config->version;
            $plugininfo->torelease = $config->release;
            if (property_exists($config, 'requires_config')) {
                $plugininfo->requires_config = $config->requires_config;
            }
            if (property_exists($config, 'requires_parent')) {
                $plugininfo->requires_parent = $config->requires_parent;
            }
            $plugininfo->displayname = $plugindisplayname;

            try {
                $classname::sanity_check();
            }
            catch (InstallationException $exc) {
                $plugininfo->to = get_string('notinstalled', 'admin');
                $plugininfo->torelease = get_string('notinstalled', 'admin');
                $plugininfo->errormsg = $exc->getMessage();
            }

            if ($newinstall) {
                $plugininfo->from = get_string('notinstalled', 'admin');
                $plugininfo->fromrelease = get_string('notinstalled', 'admin');
                $plugininfo->newinstall = true;
                $newinstallcount ++;
                $newinstalls[$pluginkey] = $plugininfo;
            }
            else {
                $toupgrade[$pluginkey] = $plugininfo;
            }
        }
        else if ($config->version > $pluginversion) {
            if (isset($config->minupgradefrom) && isset($config->minupgraderelease)
                && $pluginversion < $config->minupgradefrom) {
                throw new ConfigSanityException("Must upgrade to $config->minupgradefrom "
                                          . " ($config->minupgraderelease) first "
                                          . " (you have $pluginversion ($pluginrelease))");
            }
            $toupgradecount++;
            $plugininfo = new stdClass();
            $plugininfo->upgrade = true;
            $plugininfo->from = $pluginversion;
            $plugininfo->fromrelease = $pluginrelease;
            $plugininfo->to = $config->version;
            $plugininfo->torelease = $config->release;
            if (property_exists($config, 'requires_config')) {
                $plugininfo->requires_config = $config->requires_config;
            }
            if (property_exists($config, 'requires_parent')) {
                $plugininfo->requires_parent = $config->requires_parent;
            }
            $plugininfo->displayname = $plugindisplayname;

            try {
                $classname::sanity_check();
            }
            catch (InstallationException $exc) {
                $plugininfo->to = $config->version;
                $plugininfo->torelease = $pluginrelease;
                $plugininfo->errormsg = $exc->getMessage();
                $toupgrade[$pluginkey] = $plugininfo;

                continue;
            }

            $toupgrade[$pluginkey] = $plugininfo;
        }
        else if ($config->version < $pluginversion) {
            $plugindisplayname = !empty($plugindisplayname) ? $plugindisplayname : (!empty($config->name) ? $config->name : $pluginpath);
            if (get_config('productionmode')) {
                throw new ConfigSanityException("Database version of Mahara plugin " . $plugindisplayname . " "
                                            . $pluginrelease . " (" . $pluginversion . ") is newer "
                                            . "than files version " . $config->release . " (" . $config->version . "). "
                                            . "Please make sure you have the correct Mahara plugin files in place.");
            }
            else {
                $outofsyncplugins[] = $plugindisplayname;
            }
        }
    }
    if (!empty($outofsyncplugins)) {
        if (!defined('SITEOUTOFSYNC')) {
            define('SITEOUTOFSYNC', implode(', ', $outofsyncplugins));
        }
    }
    // if we've just asked for one, don't return an array...
    if (!empty($name)) {
        if (count($toupgrade) == 1) {
            $upgrade = new stdClass();
            $upgrade->name = $name;
            foreach ((array)$toupgrade[$name] as $key => $value) {
                $upgrade->{$key} = $value;
            }
            return $upgrade;
        }
        else {
            return array();
        }
    }

    // If we get here, it's because we have an array of objects to return
    uksort($toupgrade, 'sort_upgrades');
    $settings['newinstallcount'] = $newinstallcount;
    $settings['newinstalls'] = $newinstalls;
    $settings['toupgradecount'] = $toupgradecount;
    $toupgrade['settings'] = $settings;
    return $toupgrade;
}

/**
 * Upgrades the core system to given upgrade version.
 *
 * @param object $upgrade   The version to upgrade to
 * @return bool             Whether the upgrade succeeded or not
 * @throws SQLException     If the upgrade failed due to a database error
 */
function upgrade_core($upgrade) {
    global $db;

    $location = get_config('libroot') . 'db/';

    db_begin();

    if (!empty($upgrade->install)) {
        install_from_xmldb_file($location . 'install.xml');
    }
    else {
        require_once($location . 'upgrade.php');
        xmldb_core_upgrade($upgrade->from);
    }

    set_config('version', $upgrade->to);
    set_config('release', $upgrade->torelease);
    set_config('series', $upgrade->toseries);
    bump_cache_version();
    cron_check_for_updates();

    if (!empty($upgrade->install)) {
        core_postinst();
    }

    db_commit();
    return true;
}

/**
 * Upgrades local customisations.
 *
 * @param object $upgrade   The version to upgrade to
 * @return bool             Whether the upgrade succeeded or not
 * @throws SQLException     If the upgrade failed due to a database error
 */
function upgrade_local($upgrade) {
    db_begin();

    require_once(get_config('docroot') . 'local/upgrade.php');
    xmldb_local_upgrade($upgrade->from);

    set_config('localversion', $upgrade->to);
    set_config('localrelease', $upgrade->torelease);
    bump_cache_version();

    db_commit();
    return true;
}

/**
 * Upgrades the plugin to a new version
 *
 * Note: This function is sometimes executed during upgrades from
 * ancient databases.  Avoid rash assumptions about what's installed
 * or these upgrades may fail.
 *
 * @param object $upgrade   Information about the plugin to upgrade
 * @return bool             Whether the upgrade succeeded or not
 * @throws SQLException     If the upgrade failed due to a database error
 */
function upgrade_plugin($upgrade) {
    global $db;

    $plugintype = '';
    $pluginname = '';

    list($plugintype, $pluginname) = explode('.', $upgrade->name);

    if ($plugintype == 'blocktype' && strpos($pluginname, '/') !== false) {
        list($artefactplugin, $blocktypename) = explode('/', $pluginname);
        $location = get_config('docroot') . 'artefact/' . $artefactplugin . '/blocktype/' . $blocktypename . '/db/';
        $function = 'xmldb_' . $plugintype . '_' . $blocktypename . '_upgrade';
    }
    else {
        $location = get_config('docroot') . $plugintype . '/' . $pluginname . '/db/';
        $function = 'xmldb_' . $plugintype . '_' . $pluginname . '_upgrade';
    }

    db_begin();

    if (!empty($upgrade->install)) {
        if (is_readable($location . 'install.xml')) {
            install_from_xmldb_file($location . 'install.xml');
        }
    }
    else {
        if (is_readable($location .  'upgrade.php')) {
            require_once($location . 'upgrade.php');
            if (!$function($upgrade->from)) {
                throw new InstallationException("Failed to run " . $function . " (check logs for errors)");
            }
        }
    }

    $installed = new stdClass();
    $installed->name = $pluginname;
    $installed->version = $upgrade->to;
    $installed->release = $upgrade->torelease;
    if ($plugintype == 'blocktype') {
        if (!empty($blocktypename)) {
            $installed->name = $blocktypename;
        }
        if (!empty($artefactplugin)) { // blocks come from artefactplugins.
            $installed->artefactplugin = $artefactplugin;
        }
    }
    if (property_exists($upgrade, 'requires_config')) {
        $installed->requires_config = $upgrade->requires_config;
    }
    if (property_exists($upgrade, 'requires_parent')) {
        $installed->requires_parent = $upgrade->requires_parent;
    }
    $installtable = $plugintype . '_installed';

    if (!empty($upgrade->install)) {
        insert_record($installtable,$installed);
    }
    else {
        update_record($installtable, $installed, 'name');
    }
    bump_cache_version();

    // postinst stuff...
    safe_require($plugintype, $pluginname);
    $pcname = generate_class_name($plugintype, $installed->name);

    if ($crons = $pcname::get_cron()) {
        foreach ($crons as $cron) {
            $cron = (object)$cron;
            if (empty($cron->callfunction)) {
                throw new InstallationException("cron for $pcname didn't supply function name");
            }
            if (!is_callable(array($pcname, $cron->callfunction))) {
                throw new InstallationException("cron $cron->callfunction for $pcname supplied but wasn't callable");
            }
            $new = false;
            $table = $plugintype . '_cron';
            if (!empty($upgrade->install)) {
                $new = true;
            }
            else if (!record_exists($table, 'plugin', $pluginname, 'callfunction', $cron->callfunction)) {
                $new = true;
            }
            $cron->plugin = $pluginname;
            if (!empty($new)) {
                insert_record($table, $cron);
            }
            else {
                update_record($table, $cron, array('plugin', 'callfunction'));
            }
        }
    }

    if ($events = $pcname::get_event_subscriptions()) {
        foreach ($events as $event) {
            $event = (object)$event;

            if (!record_exists('event_type', 'name', $event->event)) {
                throw new InstallationException("event $event->event for $pcname doesn't exist!");
            }
            if (empty($event->callfunction)) {
                throw new InstallationException("event $event->event for $pcname didn't supply function name");
            }
            if (!is_callable(array($pcname, $event->callfunction))) {
                throw new InstallationException("event $event->event with function $event->callfunction for $pcname supplied but wasn't callable");
            }
            $exists = false;
            $table = $plugintype . '_event_subscription';
            $block = blocktype_namespaced_to_single($pluginname);
            if (empty($upgrade->install)) {
                $exists = get_record($table, 'plugin' , $block, 'event', $event->event);
            }
            $event->plugin = $block;
            if (empty($exists)) {
                insert_record($table, $event);
            }
            else {
                update_record($table, $event, array('id' => $exists->id));
            }
        }
    }

    if ($activities = $pcname::get_activity_types()) {
        foreach ($activities as $activity) {
            $classname = 'ActivityType' . ucfirst($plugintype) . ucfirst($pluginname) . ucfirst($activity->name);
            if (!class_exists($classname)) {
                throw new InstallationException(get_string('classmissing', 'error',  $classname, $pluginname, $plugintype));
            }
            // Add activity_type if it doesn't exist
            if (!get_record('activity_type', 'name', $activity->name, 'plugintype', $plugintype, 'pluginname', $pluginname)) {
                $activity->plugintype = $plugintype;
                $activity->pluginname = $pluginname;
                $activity->defaultmethod = get_config('defaultnotificationmethod') ? get_config('defaultnotificationmethod') : $activity->defaultmethod;
                $where = (object) array(
                    'name'       => $activity->name,
                    'plugintype' => $plugintype,
                    'pluginname' => $pluginname,
                );
                ensure_record_exists('activity_type', $where, $activity);
            }
        }
    }

    // install artefact types
    if ($plugintype == 'artefact') {
        if (!is_callable(array($pcname, 'get_artefact_types'))) {
            throw new InstallationException("Artefact plugin $pcname must implement get_artefact_types and doesn't");
        }
        $types = $pcname::get_artefact_types();
        $ph = array();
        if (is_array($types)) {
            // Check for missing plugins - don't try to remove their data.
            // Bugs 505732 and 1287344.
            $used_types = get_records_sql_assoc("SELECT t.name, count(a.id) ct, t.plugin FROM {artefact_installed_type} t
                    LEFT JOIN {artefact} a ON t.name = a.artefacttype
                    GROUP BY t.name
                    HAVING count(a.id) > 0 AND plugin = '$pluginname'");
            if ($used_types === FALSE) {
                $used_types = array();
            }

            foreach ($types as $type) {
                $ph[] = '?';
                if (!record_exists('artefact_installed_type', 'plugin', $pluginname, 'name', $type)) {
                    $t = new stdClass();
                    $t->name = $type;
                    $t->plugin = $pluginname;
                    insert_record('artefact_installed_type',$t);
                }
                if (isset($used_types[$type])) {
                    unset($used_types[$type]);
                }
            }

            foreach ($used_types as $type) {
                $ph[] = '?';
            }

            $used_types = array_keys($used_types);

            $select = '(plugin = ? AND name NOT IN (' . implode(',', $ph) . '))';
            delete_records_select('artefact_installed_type', $select,
                    array_merge(array($pluginname),$types,$used_types));
            if (!empty($used_types)) {
                log_warn('Plugin for artefact type(s) "' . implode('", "', $used_types) . '" has gone away', true, false);
            }
        }
    }

    // install blocktype categories.
    if ($plugintype == 'blocktype' && get_config('installed')) {
        install_blocktype_categories_for_plugin($pluginname);
        install_blocktype_viewtypes_for_plugin($pluginname);
    }

    $prevversion = (empty($upgrade->install)) ? $upgrade->from : 0;
    $pcname::postinst($prevversion);

    db_commit();
    return true;
}

function core_postinst() {
    // Attempt to create session directories
    $sessionpath = get_config('sessionpath');
    $status = Session::create_directory_levels($sessionpath);

    $now = db_format_timestamp(time());
    // Set default search plugin
    set_config('searchplugin', 'internal');

    set_config('lang', get_accept_lang());
    set_config('installation_key', get_random_key());
    set_config('installation_time', $now);
    set_config('stats_installation_time', $now);
    set_config('passwordpolicy', '8_ulns');

    // Pre-define SMTP settings
    set_config('smtphosts', '');
    set_config('smtpport', '');
    set_config('smtpuser', '');
    set_config('smtppass', '');
    set_config('smtpsecure', '');

    // XMLDB adds a table's keys immediately after creating the table.  Some
    // foreign keys therefore cannot be created during the XMLDB installation,
    // because they refer to tables created later in the installation.  These
    // missing keys can be created now that all the core tables exist.
    $table = new XMLDBTable('usr');
    $key = new XMLDBKey('profileiconfk');
    $key->setAttributes(XMLDB_KEY_FOREIGN, array('profileicon'), 'artefact', array('id'));
    add_key($table, $key);

    $table = new XMLDBTable('institution');
    $key = new XMLDBKey('logofk');
    $key->setAttributes(XMLDB_KEY_FOREIGN, array('logo'), 'artefact', array('id'));
    add_key($table, $key);

    // PostgreSQL supports indexes over functions of columns, MySQL does not.
    // We make use if this if we can
    if (is_postgres()) {
        // Improve the username index
        execute_sql('DROP INDEX {usr_use_uix}');
        execute_sql('CREATE UNIQUE INDEX {usr_use_uix} ON {usr}(LOWER(username))');

        // Add user search indexes
        // Postgres only.  We could create non-lowercased indexes in MySQL, but
        // they would not be useful, and would require a change to varchar columns.
        execute_sql('CREATE INDEX {usr_fir_ix} ON {usr}(LOWER(firstname))');
        execute_sql('CREATE INDEX {usr_las_ix} ON {usr}(LOWER(lastname))');
        execute_sql('CREATE INDEX {usr_pre_ix} ON {usr}(LOWER(preferredname))');
        execute_sql('CREATE INDEX {usr_ema_ix} ON {usr}(LOWER(email))');
        execute_sql('CREATE INDEX {usr_stu_ix} ON {usr}(LOWER(studentid))');

        // Only one profile view per user
        execute_sql("CREATE UNIQUE INDEX {view_own_type_uix} ON {view}(owner) WHERE type = 'profile'");
    }

    // Some more advanced constraints. XMLDB can't handle this in its xml file format
    execute_sql('ALTER TABLE {artefact} ADD CHECK (
        (owner IS NOT NULL AND "group" IS NULL     AND institution IS NULL) OR
        (owner IS NULL     AND "group" IS NOT NULL AND institution IS NULL) OR
        (owner IS NULL     AND "group" IS NULL     AND institution IS NOT NULL)
    )');
    execute_sql('ALTER TABLE {view} ADD CHECK (
        (owner IS NOT NULL AND "group" IS NULL     AND institution IS NULL) OR
        (owner IS NULL     AND "group" IS NOT NULL AND institution IS NULL) OR
        (owner IS NULL     AND "group" IS NULL     AND institution IS NOT NULL)
    )');
    execute_sql('ALTER TABLE {artefact} ADD CHECK (
        (author IS NOT NULL AND authorname IS NULL    ) OR
        (author IS NULL     AND authorname IS NOT NULL)
    )');
    execute_sql('ALTER TABLE {view_access} ADD CHECK (
        (accesstype IS NOT NULL AND "group" IS NULL     AND usr IS NULL     AND token IS NULL   AND institution IS NULL) OR
        (accesstype IS NULL     AND "group" IS NOT NULL AND usr IS NULL     AND token IS NULL AND institution IS NULL) OR
        (accesstype IS NULL     AND "group" IS NULL     AND usr IS NOT NULL AND token IS NULL AND institution IS NULL) OR
        (accesstype IS NULL     AND "group" IS NULL     AND usr IS NULL     AND token IS NOT NULL AND institution IS NULL) OR
        (accesstype IS NULL     AND "group" IS NULL     AND usr IS NULL     AND token IS NULL AND institution IS NOT NULL)
    )');
    execute_sql('ALTER TABLE {collection} ADD CHECK (
        (owner IS NOT NULL AND "group" IS NULL     AND institution IS NULL) OR
        (owner IS NULL     AND "group" IS NOT NULL AND institution IS NULL) OR
        (owner IS NULL     AND "group" IS NULL     AND institution IS NOT NULL)
    )');

    set_antispam_defaults();
    reload_html_filters();

    // Default set of sites from which iframe content can be embedded
    // See also the postinst() function in plugins for other valid iframes
    // by searching for 'iframe_source_icon'
    $iframesources = array(
        'www.youtube.com/embed/'                   => 'YouTube',
        'player.vimeo.com/video/'                  => 'Vimeo',
        'www.slideshare.net/slideshow/embed_code/' => 'SlideShare',
        'www.glogster.com/glog/'                   => 'Glogster',
        'www.glogster.com/glog.php'                => 'Glogster',
        'edu.glogster.com/glog/'                   => 'Glogster',
        'edu.glogster.com/glog.php'                => 'Glogster',
        'wikieducator.org/index.php'               => 'WikiEducator',
        'voki.com/php/'                            => 'Voki',
    );
    $iframedomains = array(
        'YouTube'      => 'www.youtube.com',
        'Vimeo'        => 'vimeo.com',
        'SlideShare'   => 'www.slideshare.net',
        'Glogster'     => 'www.glogster.com',
        'WikiEducator' => 'wikieducator.org',
        'Voki'         => 'voki.com',
    );
    update_safe_iframes($iframesources, $iframedomains);

    require_once(get_config('docroot') . 'lib/file.php');
    update_magicdb_path();

    return $status;
}

function core_install_lastcoredata_defaults() {
    global $USER;
    db_begin();
    $institution = new stdClass();
    $institution->name = 'mahara';
    $institution->displayname = 'No Institution';
    $institution->authplugin  = 'internal';
    $institution->theme  = 'default';
    $institution->priority = 0;
    insert_record('institution', $institution);

    $auth_instance = new stdClass();
    $auth_instance->instancename  = 'Internal';
    $auth_instance->priority='1';
    $auth_instance->institution   = 'mahara';
    $auth_instance->authname      = 'internal';
    $auth_instance->active        = 1;
    $auth_instance->id = insert_record('auth_instance', $auth_instance, 'id', true);

    // Insert the root user
    $userid = 0;
    $user = new stdClass();
    $user->id = $userid;
    $user->username = 'root';
    $user->password = '*';
    $user->salt = '*';
    $user->firstname = 'System';
    $user->lastname = 'User';
    $user->email = 'root@example.org';
    $user->quota = get_config_plugin('artefact', 'file', 'defaultquota');
    $user->authinstance = $auth_instance->id;
    $user->admin = 1;

    if (is_mysql()) { // gratuitous mysql workaround
        $newid = insert_record('usr', $user, 'id', true);
        set_field('usr', 'id', 0, 'id', $newid);
        execute_sql('ALTER TABLE {usr} AUTO_INCREMENT=1');
    }
    else {
        insert_record('usr', $user);
    }

    $pages = site_content_pages();
    $now = db_format_timestamp(time());
    foreach ($pages as $name) {
        $page = new stdClass();
        $page->ctime = $now;
        $page->institution = 'mahara';
        $page->content = get_string($name . 'defaultcontent', 'install', get_string('staticpageconfigdefaults', 'install',  get_config('wwwroot') . 'admin/site/pages.php'));
        $page->name = $name;
        $page->mtime = $now;
        $page->mauthor = $userid;
        insert_record('site_content', $page);
    }

    $versionedpages = site_content_version_pages();
    foreach ($versionedpages as $name) {
        $page = new stdClass();
        $page->ctime = $now;
        $page->institution = 'mahara';
        $page->content = get_string($name . 'defaultcontent', 'install', get_string('versionedpageconfigdefault', 'install', get_config('wwwroot') . 'admin/site/privacy.php?fs=' . $name));
        $page->type = $name;
        $page->author = $userid;
        $page->version = '1.0';
        insert_record('site_content_version', $page);
    }

    require_once('group.php');
    install_system_profile_view();
    install_system_dashboard_view();
    install_system_grouphomepage_view();
    require_once('view.php');
    install_system_portfolio_view();
    install_system_progress_view();

    require_once('license.php');
    install_licenses_default();

    require_once('skin.php');
    install_skins_default();

    install_auth_default();

    // Remove admin privs from root user as it doesn't need it now
    $user->admin = 0;
    update_record('usr', $user, array('id' => 0));

    // Insert the admin user
    $user = new stdClass();
    $user->username = 'admin';
    $user->salt = auth_get_random_salt();
    $user->password = crypt('mahara', '$2a$' . get_config('bcrypt_cost') . '$' . substr(md5(get_config('passwordsaltmain') . $user->salt), 0, 22));
    $user->password = substr($user->password, 0, 7) . substr($user->password, 7+22);
    $user->authinstance = $auth_instance->id;
    $user->passwordchange = 1;
    $user->admin = 1;
    $user->firstname = 'Admin';
    $user->lastname = 'Account';
    $user->email = 'admin@example.org';
    $user->quota = get_config_plugin('artefact', 'file', 'defaultquota');
    $user->ctime = db_format_timestamp(time());
    $user->id = insert_record('usr', $user, 'id', true);
    set_profile_field($user->id, 'email', $user->email);
    set_profile_field($user->id, 'firstname', $user->firstname);
    set_profile_field($user->id, 'lastname', $user->lastname);
    // Accept the user privacy agreement on install
    $sitecontentid = get_field('site_content_version', 'id', 'type', 'privacy', 'institution', 'mahara');
    save_user_reply_to_agreement($user->id, $sitecontentid, 1);
    // Accept the user T&C on install
    $sitecontentid = get_field('site_content_version', 'id', 'type', 'termsandconditions', 'institution', 'mahara');
    save_user_reply_to_agreement($user->id, $sitecontentid, 1);
    handle_event('createuser', $user, array('password'));
    activity_add_admin_defaults(array($user->id));
    db_commit();

    // if we're installing, set up the block categories here and then poll the plugins.
    // if we're upgrading this happens somewhere else.  This is because of dependency issues around
    // the order of installation stuff.
    install_blocktype_extras();

    // Setting user access roles for content block access
    $table = new XMLDBTable('usr_access_roles');

    $roles = array('peer' => 0, 'manager' => 1, 'peermanager' => 1, 'verifier' => 1);
    foreach ($roles as $role => $state) {
        $obj = new stdClass();
        $obj->role              = $role;
        $obj->see_block_content = $state;
        insert_record('usr_access_roles', $obj);
    }
}

function core_install_firstcoredata_defaults() {
    // Install the default institution
    db_begin();

    set_config('session_timeout', 86400);
    set_config('sitename', 'Mahara');
    set_config('defaultregistrationexpirylifetime', 1209600);
    set_config('defaultaccountinactivewarn', 604800);
    set_config('creategroups', 'all');
    set_config('createpublicgroups', 'all');
    set_config('allowpublicviews', 1);
    set_config('allowpublicprofiles', 1);
    set_config('allowanonymouspages', 0);
    set_config('generatesitemap', 1);
    set_config('showselfsearchsideblock', 0);
    set_config('showtagssideblock', 1);
    set_config('tagssideblockmaxtags', 20);
    set_config('usersallowedmultipleinstitutions', 1);
    set_config('userscanchooseviewthemes', 0);
    set_config('anonymouscomments', 1);
    set_config('homepageinfo', 1);
    set_config('showonlineuserssideblock', 1);
    set_config('footerlinks', serialize(array('legal', 'about', 'contactus', 'manualhelp')));
    set_config('nousernames', 0);
    set_config('showstudentid', 0);
    set_config('onlineuserssideblockmaxusers', 10);
    set_config('loggedinprofileviewaccess', 1);
    set_config('dropdownmenu', 0);
    // Set this to a random starting number to make minor version slightly harder to detect
    set_config('cacheversion', rand(1000, 9999));
    set_config('allowcommentsbydefault', 1);

    // install the applications
    $app = new stdClass();
    $app->name = 'mahara';
    $app->displayname = 'Mahara';
    $app->xmlrpcserverurl = '/api/xmlrpc/server.php';
    $app->ssolandurl = '/auth/xmlrpc/land.php';
    insert_record('application', $app);

    $app->name = 'moodle';
    $app->displayname = 'Moodle';
    $app->xmlrpcserverurl = '/mnet/xmlrpc/server.php';
    $app->ssolandurl = '/auth/mnet/land.php';
    insert_record('application', $app);

    // insert the event types
    $eventtypes = array(
        'createuser',
        'updateuser',
        'suspenduser',
        'unsuspenduser',
        'deleteuser',
        'undeleteuser',
        'expireuser',
        'unexpireuser',
        'deactivateuser',
        'activateuser',
        'userjoinsgroup',
        'userleavesgroup',
        'userchangegrouprole',
        'saveartefact',
        'deleteartefact',
        'deleteartefacts',
        'saveview',
        'deleteview',
        'blockinstancecommit',
        'deleteblockinstance',
        'addfriend',
        'removefriend',
        'addfriendrequest',
        'removefriendrequest',
        'creategroup',
        'loginas',
        'clearcaches',
        'createview',
        'createcollection',
        'updatecollection',
        'deletecollection',
        'addsubmission',
        'releasesubmission',
        'updateviewaccess',
        'removeviewaccess',
        'sharedcommenttogroup',
        'verifiedprogress',
    );

    foreach ($eventtypes as $et) {
        $e = new stdClass();
        $e->name = $et;
        insert_record('event_type', $e);
    }

    // install the core event subscriptions
    $subs = array(
        array(
            'event'        => 'createuser',
            'callfunction' => 'activity_set_defaults',
        ),
        array(
            'event'        => 'createuser',
            'callfunction' => 'add_user_to_autoadd_groups',
        ),
        array(
            'event'         => 'blockinstancecommit',
            'callfunction'  => 'watchlist_record_changes',
        ),
        array(
            'event'         => 'deleteblockinstance',
            'callfunction'  => 'watchlist_block_deleted',
        ),
        array(
            'event'         => 'saveartefact',
            'callfunction'  => 'watchlist_record_changes',
        ),
        array(
            'event'         => 'saveview',
            'callfunction'  => 'watchlist_record_changes',
        ),
    );

    foreach ($subs as $sub) {
        insert_record('event_subscription', (object)$sub);
    }

    // Install the activity types. Name, admin, delay, allownonemethod, defaultmethod.
    $activitytypes = array(
        array('maharamessage',      0, 0, 0, 'email'),
        array('usermessage',        0, 0, 0, 'email'),
        array('watchlist',          0, 1, 1, 'email'),
        array('viewaccess',         0, 1, 1, 'email'),
        array('contactus',          1, 1, 1, 'email'),
        array('objectionable',      1, 1, 1, 'email'),
        array('virusrepeat',        1, 1, 1, 'email'),
        array('virusrelease',       1, 1, 1, 'email'),
        array('institutionmessage', 0, 0, 1, 'email'),
        array('groupmessage',       0, 1, 1, 'email'),
        array('viewaccessrevoke',   0, 0, 0, 'email')
    );

    foreach ($activitytypes as $at) {
        $a = new stdClass();
        $a->name = $at[0];
        $a->admin = $at[1];
        $a->delay = $at[2];
        $a->allownonemethod = $at[3];
        $a->defaultmethod = $at[4];
        insert_record('activity_type', $a);
    }

    // install the cronjobs...
    $cronjobs = array(
        'auth_clean_partial_registrations'          => array('5', '0', '*', '*', '*'),
        'auth_clean_expired_password_requests'      => array('5', '0', '*', '*', '*'),
        'auth_handle_account_expiries'              => array('5', '10', '*', '*', '*'),
        'auth_handle_institution_expiries'          => array('5', '9', '*', '*', '*'),
        'activity_process_queue'                    => array('*/5', '*', '*', '*', '*'),
        'auth_remove_old_session_files'             => array('30', '20', '*', '*', '*'),
        'recalculate_quota'                         => array('15', '2', '*', '*', '*'),
        'import_process_queue'                      => array('*/5', '*', '*', '*', '*'),
        'export_process_queue'                      => array('*/6', '*', '*', '*', '*'),
        'submissions_delete_removed_archive'        => array('15', '1', '1', '*', '*'),
        'cron_send_registration_data'               => array(rand(0, 59), rand(0, 23), '*', '*', rand(0, 6)),
        'export_cleanup_old_exports'                => array('0', '3,15', '*', '*', '*'),
        'import_cleanup_old_imports'                => array('0', '4,16', '*', '*', '*'),
        'cron_site_data_weekly'                     => array('55', '23', '*', '*', '6'),
        'cron_site_data_daily'                      => array('51', '23', '*', '*', '*'),
        'cron_check_for_updates'                    => array(rand(0, 59), rand(0, 23), '*', '*', '*'),
        'cron_clean_internal_activity_notifications'=> array(45, 22, '*', '*', '*'),
        'cron_sitemap_daily'                        => array(0, 1, '*', '*', '*'),
        'file_cleanup_old_cached_files'             => array(0, 1, '*', '*', '*'),
        'file_cleanup_old_temp_files'               => array(0, 2, '*/2', '*', '*'),
        'user_login_tries_to_zero'                  => array('2-59/5', '*', '*', '*', '*'),
        'cron_institution_registration_data'        => array(rand(0, 59), rand(0, 23), '*', '*', rand(0, 6)),
        'cron_institution_data_weekly'              => array('0', '0', '*', '*', '1'),
        'cron_institution_data_daily'               => array('51', '23', '*', '*', '*'),
        'check_imap_for_bounces'                    => array('*', '*', '*', '*', '*'),
        'cron_event_log_expire'                     => array('7', '23', '*', '*', '*'),
        'watchlist_process_notifications'           => array('*', '*', '*', '*', '*'),
        'cron_email_reset_rebounce'                 => array(rand(0, 59), rand(0, 23), '*', '*', '*'),
        'auth_clean_expired_migrations'             => array('0', '2,14', '*', '*', '*'),
        'portfolio_auto_copy'                       => array('*', '*', '*', '*', '*'),
        'unlock_collections_by_rollover'            => array('0', '3', '*', '*', '*'),
    );
    foreach ($cronjobs as $callfunction => $times) {
        $cron = new stdClass();
        $cron->callfunction = $callfunction;
        $cron->minute       = $times[0];
        $cron->hour         = $times[1];
        $cron->day          = $times[2];
        $cron->month        = $times[3];
        $cron->dayofweek    = $times[4];
        insert_record('cron', $cron);
    }

    $viewtypes = array('dashboard', 'portfolio', 'profile', 'grouphomepage', 'progress');
    foreach ($viewtypes as $vt) {
        insert_record('view_type', (object)array(
            'type' => $vt,
        ));
    }
    db_commit();
}


/**
 * xmldb will pass us the xml file and we can perform any substitution as necessary
 */
function local_xmldb_contents_sub(&$contents) {
    // the main install.xml file needs to sub in plugintype tables.
    $searchstring = '<!-- PLUGIN_TYPE_SUBSTITUTION -->';
    if (strstr($contents, $searchstring) === 0) {
        return;
    }
    // ok, we're in the main file and we need to install all the plugin tables
    // get the basic skeleton structure
    $plugintables = file_get_contents(get_config('docroot') . 'lib/db/plugintables.xml');
    $tosub = '';
    foreach (plugin_types() as $plugin) {
        // any that want their own stuff can put it in docroot/plugintype/lib/db/plugintables.xml
        //- like auth is a bit special
        $specialcase = get_config('docroot') . $plugin . '/plugintables.xml';
        if (is_readable($specialcase)) {
            $tosub .= file_get_contents($specialcase) . "\n";
        }
        else {
            $replaced = '';
            // look for tables to put at the start...
            $pretables = get_config('docroot') . $plugin . '/beforetables.xml';
            if (is_readable($pretables)) {
                $replaced = file_get_contents($pretables) . "\n";
            }

            // perform any additional once off substitutions
            require_once(get_config('docroot') . $plugin . '/lib.php');
            $classname = generate_class_name($plugin);
            if (method_exists($classname, 'extra_xmldb_substitution')) {
                $replaced  .= $classname::extra_xmldb_substitution($plugintables);
            }
            else {
                $replaced .= $plugintables;
            }
            $tosub .= str_replace('__PLUGINTYPE__', $plugin, $replaced) . "\n";
            // look for any tables to put at the end..
            $extratables = get_config('docroot') . $plugin . '/extratables.xml';
            if (is_readable($extratables)) {
                $tosub .= file_get_contents($extratables) . "\n";
            }
        }
    }
    $contents = str_replace($searchstring, $tosub, $contents);
}


/**
 * validates a plugin for installation
 * @throws InstallationException
*/
function validate_plugin($plugintype, $pluginname, $pluginpath='') {

    if (empty($pluginpath)) {
        $pluginpath = get_config('docroot') . $plugintype . '/' . $pluginname;
    }
    if (!file_exists($pluginpath . '/version.php')) {
        throw new InstallationException(get_string('versionphpmissing1', 'error', $plugintype, $pluginname, $pluginname, $pluginpath));
    }
    safe_require($plugintype, $pluginname);
    $classname = generate_class_name($plugintype, $pluginname);
    if (!class_exists($classname)) {
        throw new InstallationException(get_string('classmissing', 'error', $classname, $plugintype, $pluginname));
    }
    require_once(get_config('docroot') . $plugintype . '/lib.php');
    $funname = $plugintype . '_check_plugin_sanity';
    if (function_exists($funname)) {
        $funname($pluginname);
    }
}

/*
* the order things are installed/upgraded in matters
*/

function sort_upgrades($k1, $k2) {
    if ($k1 == 'core') {
        return -1;
    }
    else if ($k2 == 'core') {
        return 1;
    }
    else if ($k1 == 'firstcoredata') {
        return -1;
    }
    else if ($k2 == 'firstcoredata') {
        return 1;
    }
    else if ($k1 == 'localpreinst') {
        return -1;
    }
    else if ($k2 == 'localpreinst') {
        return 1;
    }
    else if ($k1 == 'localpostinst') {
        return 1;
    }
    else if ($k2 == 'localpostinst') {
        return -1;
    }
    else if ($k1 == 'lastcoredata') {
        return 1;
    }
    else if ($k2 == 'lastcoredata') {
        return -1;
    }
    // else obey the order plugin types returns (strip off plugintype. from the start)
    $weight1 = array_search(substr($k1, 0, strpos($k1, '.')), plugin_types());
    $weight2 = array_search(substr($k2, 0, strpos($k2, '.')), plugin_types());
    return ($weight1 > $weight2);
}

/** blocktype categories the system exports (including artefact categories)
*/
function get_blocktype_categories() {
    return array('shortcut', 'fileimagevideo', 'blog', 'general', 'internal', 'resume', 'external');
}

function install_blocktype_categories_for_plugin($blocktype) {
    if (!safe_require('blocktype', $blocktype, 'lib.php', 'require_once', true)) {
        // Block has been uninstalled or is missing, so no category data to enter.
        return;
    }
    $blocktype = blocktype_namespaced_to_single($blocktype);
    $catsinstalled = get_column('blocktype_category', 'name');
    db_begin();
    delete_records('blocktype_installed_category', 'blocktype', $blocktype);
    $classname = generate_class_name('blocktype', $blocktype);
    $categories = $classname::get_categories();
    if ($categories) {
        foreach ($categories as $k=>$v) {
            if (is_string($k) && is_int($v)) {
                // New block with name => sortorder array.
                $cat = $k;
                $sortorder = $v;
            }
            else {
                // Legacy block with just categories, no sortorders. Give it the default sortorder.
                $cat = $v;
                $sortorder = PluginBlocktype::$DEFAULT_SORTORDER;
            }
            if (in_array($cat, $catsinstalled)) {
                insert_record('blocktype_installed_category', (object)array(
                    'blocktype' => $blocktype,
                    'category' => $cat,
                    'sortorder' => $sortorder,
                ));
            }
        }
    }
    db_commit();
}

function install_blocktype_viewtypes_for_plugin($blocktype) {
    if (!safe_require('blocktype', $blocktype, 'lib.php', 'require_once', true)) {
        // Block has been uninstalled or is missing, so no viewtype data to enter.
        return;
    }
    $blocktype = blocktype_namespaced_to_single($blocktype);
    $vtinstalled = get_column('view_type', 'type');
    db_begin();
    delete_records('blocktype_installed_viewtype', 'blocktype', $blocktype);
    $classname = generate_class_name('blocktype', $blocktype);
    $viewtypes = $classname::get_viewtypes();
    if ($viewtypes) {
        foreach($viewtypes as $vt) {
            if (in_array($vt, $vtinstalled)) {
                insert_record('blocktype_installed_viewtype', (object)array(
                    'blocktype' => $blocktype,
                    'viewtype'  => $vt,
                ));
            }
        }
    }
    db_commit();
}

function install_blocktype_extras() {
    db_begin();

    $categories = get_blocktype_categories();
    $installedcategories = get_column('blocktype_category', 'name');
    $sort = empty($installedcategories) ? -1 : get_record_sql('SELECT MAX(sort) AS maxsort FROM {blocktype_category}')->maxsort;

    if ($toinstall = array_diff($categories, $installedcategories)) {
        foreach ($toinstall as $i) {
            insert_record('blocktype_category', (object)array('name' => $i, 'sort' => (++$sort)));
        }
    }

    db_commit();


    // poll all the installed blocktype plugins and ask them what categories they export
    if ($blocktypes = plugins_installed('blocktype', true)) {
        foreach ($blocktypes as $bt) {
            install_blocktype_categories_for_plugin(blocktype_single_to_namespaced($bt->name, $bt->artefactplugin));
            install_blocktype_viewtypes_for_plugin(blocktype_single_to_namespaced($bt->name, $bt->artefactplugin));
        }
    }
}

/**
 * Reload htmlpurifier filters from the XML configuration file.
 */
function reload_html_filters() {
    require_once('xmlize.php');

    $newlist = xmlize(file_get_contents(get_config('libroot') . 'htmlpurifiercustom/filters.xml'));
    $filters = $newlist['filters']['#']['filter'];
    foreach ($filters as &$f) {
        $f = (object) array(
            'site' => $f['#']['site'][0]['#'],
            'file' => $f['#']['filename'][0]['#']
        );
    }
    set_config('filters', serialize($filters));
    log_info('Enabled ' . count($filters) . ' HTML filters.');
}

/**
 * Update safe iframe regex
 *
 * @return bool
 * @throws SystemException
 */
function update_safe_iframe_regex() {
    $prefixes = get_column('iframe_source', 'prefix');
    if (!empty($prefixes)) {
        // We must generate a guaranteed valid regex here that's not
        // too slack.  It's easiest to whitelist a few characters, but
        // in future we may need to be more clever.  Admins who know
        // what they're doing, and need something fancy, can always
        // override this in config.php.
        foreach ($prefixes as $key => $r) {
            if (!preg_match('/^[\?a-zA-Z0-9\/\._-]+$/', $r)) {
                throw new SystemException('Invalid site passed to update_safe_iframe_regex');
            }
            if (substr($r, -1) == '/') {
                $prefixes[$key] = substr($r, 0, -1) . '($|[/?#])';
            }
        }

        // Allowed iframe URLs should be one of the partial URIs in iframe_source,
        // prefaced by http:// or https:// or just // (which is a protocol-relative URL)
        $iframeregexp = '%^(http:|https:|)//(' . str_replace('.', '\.', implode('|', $prefixes)) . ')%';
    }
    return set_config('iframeregexp', isset($iframeregexp) ? $iframeregexp : null);
}

function update_safe_iframes(array $iframesources, array $iframedomains) {
    db_begin();

    delete_records('iframe_source_icon');
    foreach ($iframedomains as $name => $domain) {
        insert_record('iframe_source_icon', (object) array('name' => $name, 'domain' => $domain));
    }

    delete_records('iframe_source');
    foreach ($iframesources as $prefix => $name) {
        insert_record('iframe_source', (object) array('prefix' => $prefix, 'name' => $name));
    }

    update_safe_iframe_regex();
    db_commit();
}

/**
 * Use meaningful defaults for the antispam settings.
 */
function set_antispam_defaults() {
    set_config('formsecret', get_random_key());
    require_once(get_config('docroot') . 'lib/antispam.php');
    if(checkdnsrr('test.uribl.com.black.uribl.com', 'A')) {
        set_config('antispam', 'advanced');
    }
    else {
        set_config('antispam', 'simple');
    }
    set_config('spamhaus', 0);
    set_config('surbl', 0);
}

function activate_plugin_form($plugintype, $plugin) {
    // Check if there is a displayname
    $classname = generate_class_name($plugintype, $plugin->name);
    $plugin->displayname = $classname::get_plugin_display_name();

    return pieform(array(
        'name'            => 'activate_' . $plugintype . '_' . $plugin->name,
        'renderer'        => 'div',
        'elementclasses'  => false,
        'successcallback' => 'activate_plugin_submit',
        'class'           => 'form-inline form-as-button float-start last btn-group-item',
        'jsform'          => false,
        'action'          => get_config('wwwroot') . 'admin/extensions/pluginconfig.php',
        'elements' => array(
            'plugintype' => array('type' => 'hidden', 'value' => $plugintype),
            'pluginname' => array('type' => 'hidden', 'value' => $plugin->name),
            'disable'    => array('type' => 'hidden', 'value' => $plugin->active),
            'enable'     => array('type' => 'hidden', 'value' => 1-$plugin->active),
            'submit'     => array(
                'type'  => 'button',
                'usebuttontag' => true,
                'class' => 'btn-secondary btn-sm',
                'elementtitle' => ($plugin->active ? get_string('hide') : get_string('show')) . ' ' . $plugintype . ' ' . (($plugin->displayname) ? $plugin->displayname : $plugin->name),
                'hiddenlabel' => true,
                'value' => $plugin->active ? '<span class="icon icon-eye"><span class="visually-hidden" aria-hidden="true">' . get_string('hide') . '</span></span>' : '<span class="icon icon-eye-slash"><span class="visually-hidden" aria-hidden="true">' . get_string('show') . '</span></span>',
            ),
        ),
    ));
}

function activate_plugin_submit(Pieform $form, $values) {
    global $SESSION;
    if ($values['plugintype'] == 'blocktype') {
        if (strpos($values['pluginname'], '/') !== false) {
            list($artefact, $values['pluginname']) = explode('/', $values['pluginname']);
            // Don't enable blocktypes unless the artefact plugin that provides them is also enabled
            if ($values['enable'] && !get_field('artefact_installed', 'active', 'name', $artefact)) {
                $SESSION->add_error_msg(get_string('pluginnotenabled', 'mahara', $artefact));
                redirect('/admin/extensions/plugins.php');
            }
        }
    }
    else if ($values['plugintype'] == 'artefact' && $values['disable']) {
        // Disable all the artefact's blocktypes too
        set_field('blocktype_installed', 'active', 0, 'artefactplugin', $values['pluginname']);
    }
    set_field($values['plugintype'] . '_installed', 'active', $values['enable'], 'name', $values['pluginname']);
    $SESSION->add_ok_msg(get_string('plugin' . (($values['enable']) ? 'enabled' : 'disabled')));
    redirect('/admin/extensions/plugins.php');
}

// site warnings for the admin to consider
function site_warnings() {

    $warnings = array();

    // Warn about nasty php settings that Mahara can still sort of deal with.
    if (ini_get_bool('register_globals')) {
        $warnings[] = get_string('registerglobals', 'error');
    }
    if (!defined('CRON') && ini_get_bool('magic_quotes_gpc')) {
        $warnings[] = get_string('magicquotesgpc', 'error');
    }
    if (ini_get_bool('magic_quotes_runtime')) {
        $warnings[] = get_string('magicquotesruntime', 'error');
    }
    if (ini_get_bool('magic_quotes_sybase')) {
        $warnings[] = get_string('magicquotessybase', 'error');
    }

    // Check if the host returns a usable value for the timezone identifier %z
    $tz_count_date = new DateTime();
    $tz_count = preg_match("/[\+\-][0-9]{4}/", $tz_count_date->format('O'));
    if ($tz_count == 0 || $tz_count == FALSE) {
        $warnings[] = get_string('timezoneidentifierunusable', 'error');
    }
    if (get_config('dbtimezone')) {
        $warnings[] = get_string('updatesitetimezone', 'error');
    }

    // Check for low security (i.e. not random enough) session IDs
    if (version_compare(PHP_VERSION, '7.1.0') < 0 && (int)ini_get('session.entropy_length') < 16) {
        $warnings[] = get_string('notenoughsessionentropy', 'error');
    }

    // Check noreply address is valid.
    if (!sanitize_email(get_config('noreplyaddress')) || get_config('noreplyaddress') == 'noreply@example.org') {
        $warnings[] = get_string('noreplyaddressmissingorinvalid', 'error', get_config('wwwroot') . 'admin/site/options.php?fs=emailsettings');
    }

    // If the configurable themes bug 1760732 was triggered by a recent upgrade, provide a warning that the theme needs resaving.
    $custom_themes = get_records_sql_array("SELECT i.displayname FROM {institution} i
                                            JOIN {institution_config} ic ON ic.institution = i.name
                                            WHERE ic.field = ? AND ic.value = ?
                                            ORDER BY i.displayname", array('customthemeupdate', '1'));
    if ($custom_themes) {
        $warning = get_string('resavecustomthemes', 'error') . "<ul>";
        foreach ($custom_themes as $theme) {
            $warning .= "<li>" . hsc($theme->displayname) . "</li>";
        }
        $warning .= "</ul>";
        $warnings[] = $warning;
    }

    safe_require('auth', 'saml');
    if (PluginAuthSaml::is_active()) {
        // Check if the saml plugin config needs updating
        if (record_exists_select('auth_config', "plugin = ? AND field = ?", array('saml', 'simplesamlphplib'))) {
            $warnings[] = get_string('obsoletesamlplugin', 'auth.saml', get_config('wwwroot') . 'admin/extensions/pluginconfig.php?plugintype=auth&pluginname=saml');
        }
        // Check if all saml instances are configured for new auth/saml plugin.
        if ($samls = get_records_sql_array(
                 "SELECT ai.id, ai.instancename, i.name, i.displayname FROM {auth_instance} ai
                  LEFT JOIN {institution} i ON i.name = ai.institution
                  WHERE ai.id NOT IN (
                                      SELECT instance FROM {auth_instance_config} aic
                                      WHERE aic.field = ?
                  ) AND ai.authname = ? ORDER BY i.displayname", array('institutionidpentityid', 'saml'))) {
            foreach ($samls as $saml) {
                $warnings[] = get_string('obsoletesamlinstance', 'auth.saml', get_config('wwwroot') . 'admin/users/addauthority.php?id=' . $saml->id . '&edit=1&i=' . $saml->name . '&p=saml', $saml->instancename, $saml->displayname);
            }
        }
        // Check if the library is updated to the latest version Mahara supports
        $autoload = get_config('docroot') .'auth/saml/extlib/simplesamlphp/vendor/autoload.php';
        if (file_exists($autoload)) {
            require(get_config('docroot') .'auth/saml/extlib/simplesamlphp/vendor/autoload.php');
            $config = SimpleSAML\Configuration::getInstance();

            $libversion = get_config_plugin('auth', 'saml', 'version');
            if (!empty($libversion) && $config->getVersion() != $libversion) {
                $warnings[] = get_string('errorupdatelib', 'auth.saml');
            }
        }

    }

    // Check that the GD library has support for jpg, png and gif at least
    $gdinfo = gd_info();
    if (!$gdinfo['JPEG Support']) {
        $warnings[] = get_string('gdlibrarylacksjpegsupport', 'error');
    }

    if (!$gdinfo['PNG Support']) {
        $warnings[] = get_string('gdlibrarylackspngsupport', 'error');
    }

    if (!$gdinfo['GIF Read Support'] || !$gdinfo['GIF Create Support']) {
        $warnings[] = get_string('gdlibrarylacksgifsupport', 'error');
    }

    // Check file upload settings.
    $postmax       = ini_get('post_max_size');
    $uploadmax     = ini_get('upload_max_filesize');
    $realpostmax   = get_real_size($postmax);
    $realuploadmax = get_real_size($uploadmax);
    if ($realpostmax && $realpostmax < $realuploadmax) {
        $warnings[] = get_string('postmaxlessthanuploadmax', 'error', $postmax, $uploadmax, $postmax);
    }
    else if ($realpostmax && $realpostmax < 9000000) {
        $warnings[] = get_string('smallpostmaxsize', 'error', $postmax, $postmax);
    }

    if (ini_get('open_basedir')) {
        $warnings[] = get_string('openbasedirenabled', 'error') . ' ' . get_string('openbasedirwarning', 'error');
    }

    $sitesalt = get_config('passwordsaltmain');
    if (empty($sitesalt)) {
        $warnings[] = get_string('nopasswordsaltset', 'error');
    }
    else if ($sitesalt == 'some long random string here with lots of characters'
            || trim($sitesalt) === ''
            || preg_match('/^([a-zA-Z0-9]{0,10})$/', $sitesalt)) {
        $warnings[] = get_string('passwordsaltweak', 'error');
    }

    $urlsecret = get_config('urlsecret');
    if (!empty($urlsecret) && $urlsecret == 'mysupersecret') {
        $warnings[] = get_string('urlsecretweak', 'error');
    }

    if (!extension_loaded('mbstring')) {
        $warnings[] = get_string('mbstringneeded', 'error');
    }

    if (get_config('dbtype') == 'mysql') {
        $warnings[] = get_string('switchtomysqli', 'error');
    }

    return $warnings;
}


/**
 * Increment the cache version number.
 * This is an arbitrary number that we append to the end of static content to make sure the user
 * refreshes it when we update the site.
 */
function bump_cache_version() {
    set_config('cacheversion', get_config('cacheversion') + 1);
}
