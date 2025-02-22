<?php
/**
 * Utilities to manage page skins
 *
 * @package    mahara
 * @subpackage core
 * @author     Gregor Anzelj
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 * @copyright  (C) 2010-2013 Gregor Anzelj <gregor.anzelj@gmail.com>
 *
 */

defined('INTERNAL') || die();

/**
 * Skin class for the creation and management of skins.
 *
 * Skins are a way to style a personal page. Allowing for
 * coloured backgrounds, image headers, font customisations etc.
 *
 * This is enabled through `$cfg->skins = true` in the config.php.
 * You can then find this option in *Main menu -> Create -> Skins*
 * and set up your skins there.
 * You apply a skin on a page's 'Settings' screen in the *Advanced options*
 * panel.
 */
class Skin {
    /**
     * Constants that represent background repeat options
     */
    const BACKGROUND_REPEAT_NO = 1;
    const BACKGROUND_REPEAT_X = 2;
    const BACKGROUND_REPEAT_Y = 3;
    const BACKGROUND_REPEAT_BOTH = 4;

    /**
     * Constants that represent background positioning options
     */
    const BACKGROUND_POS_LEFT_TOP = 1;
    const BACKGROUND_POS_CENTER_TOP = 2;
    const BACKGROUND_POS_RIGHT_TOP = 3;
    const BACKGROUND_POS_LEFT_CENTER = 4;
    const BACKGROUND_POS_CENTER_CENTER = 5;
    const BACKGROUND_POS_RIGHT_CENTER = 6;
    const BACKGROUND_POS_LEFT_BOTTOM = 7;
    const BACKGROUND_POS_CENTER_BOTTOM = 8;
    const BACKGROUND_POS_RIGHT_BOTTOM = 9;

    /**
     * Settings for dynamically creating a preview image of skin and thumbnail from that image.
     * Image resolution: 1920x1080 (Full HD)
     * Thumbnail resolution: 240x135 (original image shrunk to 12.5%)
     */
    const PREVIEW_WIDTH = 1920;
    const PREVIEW_HEIGHT = 1080;
    const PREVIEW_THUMBNAIL_ZOOM = 0.125;

    /**
     * A regular expression containing characters that should be filtered out of font names.
     * Meant to be used like this: $fontname = preg_replace(Skin::FONTNAME_FILTER_CHARACTERS, '', $fontname);
     */
    const FONTNAME_FILTER_CHARACTERS = "#[^A-Za-z0-9]#";

    /**
     * A setting to tell the commit() and or __destruct() functions whether there is any changes to the object to actually commit to database
     *
     * @var mixed
     */
    private $dirty;
    /**
     * Whether this skin is deleted
     *
     * @var bool
     */
    private $deleted;
    /**
     * The ID of this skin
     *
     * @var int
     */
    private $id;
    /**
     * The title of this skin
     *
     * @var string
     */
    private $title;
    /**
     * The description of this skin
     *
     * @var string
     */
    private $description;
    /**
     * The owner of this skin
     *
     * @var int The user ID
     */
    private $owner;
    /**
     * The privacy type of this skin
     *
     * @var string
     */
    private $type = 'private';
    /**
     * Time of last modification
     *
     * @var mixed
     */
    private $mtime;
    /**
     * Time of creation
     *
     * @var mixed
     */
    private $ctime;

    /**
     * Array holding styling details for this skin
     *
     * @var array
     */
    private $viewskin;

    /**
     * The default styling details for a skin
     */
    public static $defaultviewskin = array(
        'body_background_color' => '#FFFFFF',
        'body_background_image' => 0,
        'body_background_repeat' => 4,
        'body_background_attachment' => 'scroll',
        'body_background_position' => 1,

        'header_background_color' => '#DDDDDD',
        'header_background_image' => 0,

        'view_text_font_family' => 'Arial',
        'view_heading_font_family' => 'Arial',

        'view_block_header_font' => 'Arial',
        'view_block_header_font_color' => '#333333',

        'view_text_font_size' => 'small',
        'view_text_font_color' => '#000000',
        'view_text_heading_color' => '#000000',

        'view_link_normal_color' => '#0000EE',
        'view_link_normal_underline' => true,
        'view_link_hover_color' => '##551A8B',
        'view_link_hover_underline' => true,

        'view_custom_css' => '',
    );


    /**
     * Create a new skin object. If an ID is supplied, we retrieve that skin
     * from the DB. If no ID is supplied, we're creating a new skin object,
     * which can later be inserted into the DB.
     * @param integer $id
     * @param object $data
     * @throws ArtefactNotFoundException
     */
    public function __construct($id = 0, $data = null) {
        if (!empty($id)) {
            $tempdata = get_record('skin','id',$id);
            if (!$tempdata) {
                throw new SkinNotFoundException("Skin with id $id not found");
            }
            if (is_valid_serialized_skin_attribute($tempdata->viewskin)) {
                $tempdata->viewskin = unserialize($tempdata->viewskin);
            }
            else {
                $tempdata->viewskin = array();
            }

            if ($data !== null) {
                $data = array_merge((array)$tempdata, $data);
            }
            else {
                $data = $tempdata; // use what the database has
            }
            $this->id = $id;
        }
        else {
            $this->dirty = true;
        }

        if (empty($data)) {
            $data = array();
        }
        foreach ((array)$data as $field => $value) {
            if (property_exists($this, $field)) {
                if ($field == 'viewskin' && is_array($value)) {
                    // For viewskin, do an array_merge so we get the default values for anything that wasn't
                    // specified
                    $this->viewskin = array_merge(Skin::$defaultviewskin, $value);
                }
                else {
                    $this->{$field} = $value;
                }
            }
        }
    }

    /**
     * Creates a new skin for the given user
     *
     * @param array $skindata Data about the skin. You can pass in most fields
     *                        that appear in the skin table.
     * @return skin              The newly created skin
     * @throws SystemException if the skin data is invalid - mostly this is due
     *                         to owner information being specified incorrectly.
     */
    public static function create($skindata) {
        global $USER;
        $userid = $USER->id;

        // If no owner information is provided, assume that the view is being
        // created by the user for themself.
        if (!isset($skindata['owner'])) {
            $skindata['owner'] = $userid;
        }

        if (isset($skindata['owner'])) {
            if ($skindata['owner'] != $userid) {
                throw new SystemException("Skin::skin_create: User $userid is not allowed to create a skin for owner {$skindata['owner']}");
            }
        }

        // Create the view skin
        $defaultdata = array(
            'title'    => self::new_title(get_string('Untitled', 'skin'), $skindata['owner']),
        );

        $data = (object)array_merge($defaultdata, $skindata);

        $skin = new Skin(0, $data);
        $skin->commit();

        return $skin;
    }


    /**
     * Deletes this skin (the one with its ID) from the database
     */
    public function delete() {
        db_begin();
        delete_records('skin', 'id', $this->id);
        // Reset the view's skin
        set_field('view', 'skin', null, 'skin', $this->id);
        $this->deleted = true;
        db_commit();
    }


    /**
     * Return the selected property of the skin
     * @param mixed $field
     * @throws InvalidArgumentException if the property doesn't match one of the skin's properties
     */
    public function get($field) {
        if (!property_exists($this, $field)) {
            throw new InvalidArgumentException("Field $field wasn't found in class " . get_class($this));
        }
        return $this->{$field};
    }


    /**
     * Update one of the fields of this skin. This also marks the skin as "dirty" so that
     * $this->commit() will know to commit it.
     * @param string $field
     * @param mixed $value
     * @throws InvalidArgumentException
     * @return boolean
     */
    public function set($field, $value) {
        if (property_exists($this, $field)) {
            if ($this->{$field} != $value) {
                // only set it to dirty if it's changed
                $this->dirty = true;
            }
            $this->{$field} = $value;
            //$this->mtime = time();
            return true;
        }
        throw new InvalidArgumentException("Field $field wasn't found in class " . get_class($this));
    }


    /**
     * Commit changes in this skin back into the skin table in the database
     */
    public function commit() {
        if (empty($this->dirty)) {
            return;
        }
        $fordb = new stdClass();
        foreach (get_object_vars($this) as $k => $v) {
            $fordb->{$k} = $v;
        }
        $fordb->mtime = db_format_timestamp(time());

        try {
            if (isset($this->viewskin['body_background_image']) && record_exists('artefact', 'id', $this->viewskin['body_background_image'])
                && artefact_instance_from_id($this->viewskin['body_background_image'])) {
                $fordb->bodybgimg = $this->viewskin['body_background_image'];
            }
            else {
                $fordb->bodybgimg = null;
                unset($fordb->viewskin['body_background_image']);
            }
        }
        catch (ArtefactNotFoundException $e) {
            $fordb->bodybgimg = null;
            unset($fordb->viewskin['body_background_image']);
        }
        try {
            if (isset($this->viewskin['header_background_image']) && record_exists('artefact', 'id', $this->viewskin['header_background_image'])
            && artefact_instance_from_id($this->viewskin['header_background_image'])) {
                $fordb->headingbgimg = $this->viewskin['header_background_image'];
            }
            else {
                $fordb->headingbgimg = null;
                unset($fordb->viewskin['header_background_image']);
            }
        }
        catch (ArtefactNotFoundException $e) {
            $fordb->headingbgimg = null;
            unset($fordb->viewskin['header_background_image']);
        }
        if (isset($fordb->viewskin) && !empty($fordb->viewskin )) {
            $fordb->viewskin = serialize($fordb->viewskin);
        }

        db_begin();

        if (empty($this->id)) {
            $fordb->ctime = $fordb->mtime;
            $this->id = insert_record('skin', $fordb, 'id', true);
        }
        else {
            $ctime = get_field('skin', 'ctime', 'id', $this->id);
            $fordb->ctime = ($ctime) ? $ctime : $fordb->mtime;
            update_record('skin', $fordb, 'id');
        }

        db_commit();

        self::generate_thumbnail($this->id);

        $this->dirty = false;
        $this->deleted = false;
    }


    /**
     * Generates a title for a newly created skin
     *
     * @param string $title
     * @param int $owner ID of the user who will own it
     * @return string
     */
    private static function new_title($title, $owner) {
        $taken = get_column_sql('
            SELECT title
            FROM {skin}
            WHERE owner = ' . (int)$owner . "
                AND title LIKE ? || '%'", array($title));
        $ext = ''; $i = 0;
        if ($taken) {
            while (in_array($title . $ext, $taken)) {
                $ext = ' (' . ++$i . ')';
            }
        }
        return $title . $ext;
    }


    /**
     * Returns data about available skins.
     * Tightly coupled with get_skin_elements() function in view/editlayout.php,
     * which uses it to display the skins picker
     * @param int $limit
     * @param int $offset
     * @param string $filter Should be: all, public, user, or site
     * @return object
     */
    public static function get_myskins_data($limit=9, $offset=0, $filter='all') {

        global $USER;
        $userid = $USER->get('id');
        $owner = null;
        $favorites = get_field('skin_favorites', 'favorites', 'user', $userid);
        $favorites = unserialize($favorites);
        if (!is_array($favorites)) { $favorites = array(); }

        $sort = 'title, id';
        $cols = 'id, title, description, owner, type, ctime, mtime';
        switch ($filter) {
            case 'public':
                $count = count_records('skin', 'type', 'public');
                $skindata = get_records_array('skin', 'type', 'public', $sort, $cols, $offset, $limit);
                break;
            case 'user':
                $count = count_records_select('skin', 'owner = ? and type != ?', array($userid, 'site'));
                $skindata = get_records_select_array('skin', 'owner = ? and type != ?', array($userid, 'site'), $sort, $cols, $offset, $limit);
                break;
            case 'site':
                $count = count_records('skin', 'type', 'site');
                $skindata = get_records_array('skin', 'type', 'site', $sort, $cols, $offset, $limit);
                break;
            default:
                $count = count_records_select('skin', 'owner = ? or type in (?, ?)', array($userid, 'site', 'public'));
                $skindata = get_records_select_array('skin', 'owner = ? or type in (?, ?)', array($userid, 'site', 'public'), $sort, $cols, $offset, $limit);
                break;
        }

        $data = array();
        if ($skindata) {
            for ($i = 0; $i < count($skindata); $i++) {
                $skinobj = new Skin(0, $skindata[$i]);
                $index[$skindata[$i]->id] = $i;
                $data[$i]['id'] = $skindata[$i]->id;
                $data[$i]['title'] = $skindata[$i]->title;
                $data[$i]['owner'] = $skindata[$i]->owner;
                $data[$i]['type'] = $skindata[$i]->type;
                if ($skinobj->can_edit()) {
                    $data[$i]['removable'] = true;
                    $data[$i]['editable']  = true;
                }
                if (in_array($skindata[$i]->id, $favorites)) {
                    $data[$i]['favorite'] = true;
                }
                else {
                    $data[$i]['favorite'] = false;
                }
                $owner = new User();
                $owner->find_by_id($skindata[$i]->owner);
                $data[$i]['metadata'] = array('displayname' => display_name($owner),
                                              'profileurl' => profile_url($owner),
                                              'description' => nl2br($skindata[$i]->description),
                                              'ctime' => format_date(strtotime($skindata[$i]->ctime)),
                                              'mtime' => format_date(strtotime($skindata[$i]->mtime)),
                                              );
            }

        }

        return (object) array(
            'data'  => $data,
            'count' => $count,
        );
    }

    /**
     * Gets all site skins
     * @return array
     */
    public static function get_site_skins() {
        $site_skins = get_records_array('skin', 'type', 'site', 'title, id', 'id, title, owner, type');
        return $site_skins;
    }

    /**
     * Get the default skin
     *
     * @return Object $defaultskin The skin for one with no customisations
     */
    public static function get_default_skin() {
        $defaultskin = new stdClass();
        $defaultskin->id = 0;
        $defaultskin->title = get_string('noskin', 'skin');
        return $defaultskin;
    }


    /**
     * Gets all user skins for the current user
     * @return array
     */
    public static function get_user_skins() {
        global $USER;
        $userid = $USER->get('id');

        $data = get_records_sql_array('SELECT s.id, s.title, s.owner, s.type
            FROM {skin} s
            WHERE s.type != ? AND s.owner = ?
            ORDER BY s.title, s.id', array('site', $userid));
        return $data;
    }


    /**
     * Gets the current user's favorite skins
     * @return array
     */
    public static function get_favorite_skins() {
        // Select public skins, which were tagged as favorites by the user.
        global $USER;
        $userid = $USER->get('id');

        $favorites = unserialize(get_field('skin_favorites', 'favorites', 'user', $userid));

        if (!empty($favorites)) {
            $data = get_records_sql_array('SELECT s.id, s.title, s.owner, s.type
                FROM {skin} s
                WHERE s.id IN (' . join(',', array_map('intval', $favorites)) . ')
                ORDER BY s.title, s.id', array());
            return $data;
        }
        return false;
    }


    /**
     * Gets all public skins
     * @return array
     */
    public static function get_public_skins() {
        // Select public skins, but don't select current user's public skins.
        global $USER;
        $userid = $USER->get('id');

        $data = get_records_sql_array('SELECT s.id, s.title, s.owner, s.type
            FROM {skin} s
            WHERE s.type = ? AND s.owner != ?
            ORDER BY s.title, s.id', array('public', $userid));
        return $data;
    }


    /**
     * Maps the integer constants we use to store background repeat options, to the CSS text for them
     * @param int $number
     * @return string
     */
    public static function background_repeat_number_to_value($number) {
        switch (intval($number)) {
            case Skin::BACKGROUND_REPEAT_NO:
                return 'no-repeat';
                break;
            case Skin::BACKGROUND_REPEAT_X:
                return 'repeat-x';
                break;
            case Skin::BACKGROUND_REPEAT_Y:
                return 'repeat-y';
                break;
            case Skin::BACKGROUND_REPEAT_BOTH:
            default:
                return 'repeat';
                break;
        }
    }


    /**
     * Maps the CSS string for a background repeat value, to the integer constant we store in the DB
     * @param string $value
     * @return number
     */
    public static function background_repeat_value_to_number($value) {
        switch ($value) {
            case 'no-repeat':
                return Skin::BACKGROUND_REPEAT_NO;
                break;
            case 'repeat-x':
                return Skin::BACKGROUND_REPEAT_X;
                break;
            case 'repeat-y':
                return Skin::BACKGROUND_REPEAT_Y;
                break;
            case 'repeat':
            default:
                return Skin::BACKGROUND_REPEAT_BOTH;
                break;
        }
    }


    /**
     * Maps from integer constants for background positioning, to CSS strings
     * @param int $number
     * @return string
     */
    public static function background_position_number_to_value($number) {
        switch (intval($number)) {
            case Skin::BACKGROUND_POS_LEFT_TOP:
                return 'left top';
                break;
            case Skin::BACKGROUND_POS_CENTER_TOP:
                return 'center top';
                break;
            case Skin::BACKGROUND_POS_RIGHT_TOP:
                return 'right top';
                break;
            case Skin::BACKGROUND_POS_LEFT_CENTER:
                return 'left center';
                break;
            case Skin::BACKGROUND_POS_CENTER_CENTER:
                return 'center center';
                break;
            case Skin::BACKGROUND_POS_RIGHT_CENTER:
                return 'right center';
                break;
            case Skin::BACKGROUND_POS_LEFT_BOTTOM:
                return 'left bottom';
                break;
            case Skin::BACKGROUND_POS_CENTER_BOTTOM:
                return 'center bottom';
                break;
            case Skin::BACKGROUND_POS_RIGHT_BOTTOM:
                return 'right bottom';
                break;
            default:
                return 'left top';
                break;
        }
    }


    /**
     * maps from CSS strings to integer constants for background positioning
     * @param string $value
     * @return integer
     */
    public static function background_position_value_to_number($value) {
        switch ($value) {
            case 'left top':
                return Skin::BACKGROUND_POS_LEFT_TOP;
                break;
            case 'center top':
                return Skin::BACKGROUND_POS_CENTER_TOP;
                break;
            case 'right top':
                return Skin::BACKGROUND_POS_RIGHT_TOP;
                break;
            case 'left center':
                return Skin::BACKGROUND_POS_LEFT_CENTER;
                break;
            case 'center center':
                return Skin::BACKGROUND_POS_CENTER_CENTER;
                break;
            case 'right center':
                return Skin::BACKGROUND_POS_RIGHT_CENTER;
                break;
            case 'left bottom':
                return Skin::BACKGROUND_POS_LEFT_BOTTOM;
                break;
            case 'center bottom':
                return Skin::BACKGROUND_POS_CENTER_BOTTOM;
                break;
            case 'right bottom':
                return Skin::BACKGROUND_POS_RIGHT_BOTTOM;
                break;
            default:
                return Skin::BACKGROUND_POS_LEFT_TOP;
                break;
        }
    }

    /**
     * Get the CSS font family from the given front name
     *
     * e.g. via Google Web Font Helper https://google-webfonts-helper.herokuapp.com/ or
     * Font Squirrel: https://www.fontsquirrel.com/tools/webfont-generator/
     *
     * @param  string $font Name of the font
     * @param  mixed $type OPTIONAL - the usage type for for $font
     *      e.g. 'heading', 'text'
     * @return string The name of the font family matching the given arguments
     *      or empty string if no matches found.
     */
    public static function get_css_font_family_from_font_name($font, $type='text') {
        if (empty($font)) {
            global $THEME;
            $fonts = Skin::get_all_theme_fonts($type);
            $font = isset($fonts[$THEME->basename]) ? $fonts[$THEME->basename] : '';
        }
        $fontdata = get_record('skin_fonts', 'name', $font);
        if (!$fontdata) {
            return '';
        }
        return $fontdata->fontstack . ', ' . $fontdata->genericfont;
    }

    /**
     * Gets the CSS for a font-face, based on the font face's name in the DB
     * @param string $font
     * @return string
     */
    public static function get_css_font_face_from_font_name($font) {
        $fontdata = get_record('skin_fonts', 'name', $font);
        // If the font is missing, just quietly omit it. The page will still display, just with the wrong font
        if (!$fontdata) {
            return '';
        }
        $fontface = '';
        if (preg_match('/^t_(.*)/', $fontdata->fonttype, $matches)) {
            $theme = $matches[1];
            $fontfamily = urlencode($fontdata->title);
            // We need to create @font-face css rule for each variant of the font
            $variants = unserialize($fontdata->variants);
            foreach ($variants as $variant) {
                $baseurl = get_config('wwwroot') . 'theme/' . $theme . '/fonts/' . strtolower($fontdata->name) . '/';
                $fontface .= '@font-face {';
                $fontface .= 'font-family: \'' . escape_css_string($fontdata->title) . '\'; ';
                $fontface .= 'src: url(\'' . $baseurl . $variant['WOFF'] . '\') format(\'woff\'); '; // The only type that is crossbrowser compatible
                $fontface .= 'font-weight: ' . $variant['font-weight'] . '; ';
                $fontface .= 'font-style: ' . $variant['font-style'] . '; ';
                $fontface .= '}';
            }
        }
        else if ($fontdata->fonttype == 'site') {
            $fontfamily = urlencode($fontdata->title);
            // We need to create @font-face css rule for each variant of the font
            $variants = unserialize($fontdata->variants);
            foreach ($variants as $variant) {
                $baseurl = get_config('wwwroot') . 'skin/font.php?family=' . $fontfamily . '&variant=' . $variant['variant'];
                $fontface .= '@font-face {';
                $fontface .= 'font-family: \''. escape_css_string($fontdata->title) . '\'; ';
                $fontface .= 'src: url(\'' . $baseurl . '&format=EOT\'); ';
                $fontface .= 'src: local(\'' . escape_css_string($fontdata->title) . '\'), local(\'' . escape_css_string($fontdata->name) . '\'), url(\'' . $baseurl . '&format=WOFF\') format(\'woff\'), ';
                $fontface .= 'url(\'' . $baseurl . '&format=TTF\') format(\'truetype\'), ';
                $fontface .= 'url(\'' . $baseurl . '&format=SVG#' . $variant['SVGid'] . '\') format(\'svg\'); ';
                $fontface .= 'font-weight: ' . $variant['font-weight'] . '; ';
                $fontface .= 'font-style: ' . $variant['font-style'] . '; ';
                $fontface .= '}';
            }
        }

        return $fontface;
    }


    /**
     * Gets the font notice from the DB
     * @param string $font
     */
    public static function get_css_font_notice_from_font_name($font) {
        $fontnotice = get_record('skin_fonts', 'name', $font);
        // If the font is missing, just quietly return nothing
        if (!$fontnotice) {
            return '';
        }
        return $fontnotice->notice;
    }


    // TODO remove this - collection nav isn't tabs any more
    /**
     * Get the height to use for tabs, based on which font is selected. (For unknown faults we just deault to 25px)
     * TODO: It would be good to provide a way for sites to provide the heights of further types of fonts. Perhaps
     * a config setting, or an editable field of the font table.
     * @param string $font
     * @return string A CSS string of the height of the font
     */
    public static function get_tabs_height_from_font_name($font) {
        switch ($font) {
            case 'Arial':
                return '25px';
                break;
            case 'BookAntiqua':
                return '26px';
                break;
            case 'Gothic':
                return '25px';
                break;
            case 'Courier':
                return '25px';
                break;
            case 'Georgia':
                return '25px';
                break;
            case 'Helvetica':
                return '25px';
                break;
            case 'Palatino':
                return '26px';
                break;
            case 'Tahoma':
                return '25px';
                break;
            case 'Times':
                return '25px';
                break;
            case 'Trebuchet':
                return '26px';
                break;
            case 'Verdana':
                return '25px';
                break;
            default:
                return '25px';
                break;
        }
    }

    /**
     * Get path for a font file
     *
     * Return the filesystem path to the file for a font (used in generating thumbnails)
     * @param string $fontname The name of the font
     * @param string $type OPTIONAL - The usage type for the $fontname
     * @return string|false The path to the font, or false if not found
     */
    public static function get_path_to_previewfile($fontname, $type='text') {
        $font = false;
        $theme = '';
        if ($fontname === '') {
            global $THEME;
            $fonts = Skin::get_all_theme_fonts($type);
            $fontname = isset($fonts[$THEME->basename]) ? $fonts[$THEME->basename] : '';

        }
        $fontdata = get_record('skin_fonts', 'name', $fontname);
        if (!$fontdata) {
            $font = false;
        }
        else if ($fontdata->fonttype == 'common') {
            $font = get_config('docroot') . 'lib/fonts/' . $fontdata->previewfont;
        }
        else if (preg_match('/^t_(.*)/', $fontdata->fonttype, $matches)) {
            if ($matches[1]) {
                $theme = $matches[1];
                $fontfile = get_config('docroot') . 'theme/' . $theme . '/fonts/' . strtolower($fontdata->name) . '/' . $fontdata->previewfont;
                if (file_exists($fontfile) && is_readable($fontfile)) {
                    $font = $fontfile;
                }
            }
        }
        else {
            $fontfile = get_config('dataroot') . 'skins/fonts/' . $fontdata->name . '/' . $fontdata->previewfont;
            if (file_exists($fontfile)) {
                $font = $fontfile;
            }
            else {
                $font = false;
            }
        }
        // If we can't find a preview file for this font, just use a default
        $font = ($font) ? ($font) : get_config('docroot') . 'lib/fonts/NimbusSansL.ttf';
        return $font;
    }

    /**
     * Converts 6-digit hex color #RRGGBB to rgb(RRR, GGG, BBB)
     *
     * @param string $color_hex
     * @return array The RGB code.
     */
    private static function get_rgb_from_hex($color_hex) {
        $color_hex = $color_hex?:'#FFFFFF';
        return array_map('hexdec', explode('|', wordwrap(substr($color_hex, 1), 2, '|', 1)));
    }


    /**
     * Gets font sizes for heading, sub-heading and normal text from given relative size (as in CSS).
     * Relative size can be one of following: small, medium, large, x-large or xx-large
     * The font size of a page header is not affected by user chosen font size
     *
     * @param string $font_size
     * @return array
     */
    private static function get_font_sizes($font_size) {
        switch ($font_size) {
            case 'small':
                return array(9, 7, 5);
                break;
            case 'medium':
                return array(9, 8, 6);
                break;
            case 'large':
                return array(9, 10, 8);
                break;
            case 'x-large':
                return array(9, 12, 10);
                break;
            case 'xx-large':
                return array(9, 14, 12);
                break;
            default:
                return array(9, 8, 6);
                break;
        }
    }


    /**
     * Convert background positioning constant, into an array of image positioning data
     * @param int $dst_w Destination width
     * @param int $dst_h Destination height
     * @param int $tile_w Tile width
     * @param int $tile_h Tile height
     * @param int $align_fill The Background positioning
     * @return array
     */
    private static function imagebackgroundfillalign($dst_w, $dst_h, $tile_w, $tile_h, $align_fill) {
        $tilepositionx = -1;
        $tilepositiony = -1;
        switch ($align_fill) {
            case Skin::BACKGROUND_POS_LEFT_TOP:
                $tilepositionx = 0;
                $tilepositiony = 0;
                break;
            case Skin::BACKGROUND_POS_CENTER_TOP:
                $tilepositionx = ($dst_w-$tile_w)/2;
                $tilepositiony = 0;
                break;
            case Skin::BACKGROUND_POS_RIGHT_TOP:
                $tilepositionx = $dst_w-$tile_w;
                $tilepositiony = 0;
                break;
            case Skin::BACKGROUND_POS_LEFT_CENTER:
                $tilepositionx = 0;
                $tilepositiony = ($dst_h-$tile_h)/2;
                break;
            case Skin::BACKGROUND_POS_CENTER_CENTER:
                $tilepositionx = ($dst_w-$tile_w)/2;
                $tilepositiony = ($dst_h-$tile_h)/2;
                break;
            case Skin::BACKGROUND_POS_RIGHT_CENTER:
                $tilepositionx = $dst_w-$tile_w;
                $tilepositiony = ($dst_h-$tile_h)/2;
                break;
            case Skin::BACKGROUND_POS_LEFT_BOTTOM:
                $tilepositionx = 0;
                $tilepositiony = $dst_h-$tile_h;
                break;
            case Skin::BACKGROUND_POS_CENTER_BOTTOM:
                $tilepositionx = ($dst_w-$tile_w)/2;
                $tilepositiony = $dst_h-$tile_h;
                break;
            case Skin::BACKGROUND_POS_RIGHT_BOTTOM:
                $tilepositionx = $dst_w-$tile_w;
                $tilepositiony = $dst_h-$tile_h;
                break;
        }
        return array('x' => $tilepositionx, 'y' => $tilepositiony);
    }


    /**
     * Create an image resource for the header background for previews
     *
     * This is used for page skin previews.
     * @see https://www.php.net/manual/en/function.imagecreatetruecolor.php
     * @see generate_thumbnail()
     *
     * @param resource $dst_im (Reference) Image resource identifier. Use `imagecreatetruecolor()`
     * @param mixed $src_fill Image resource identifier for header image
     * @param float $zoom_fill Zoom factor
     * @param int $header_width Width of the header
     * @param int $header_height Height of the header
     * @param mixed $align_fill OPTIONAL
     * @param mixed $xoffset OPTIONAL
     */
    private static function imageheaderfill(&$dst_im, $src_fill, $zoom_fill, $header_width, $header_height, $align_fill=1, $xoffset = 0) {
        $layer = imagecreatetruecolor(imagesx($dst_im), imagesy($dst_im));
        // Turn off alpha blending and set alpha flag
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        // Create resized (zoomed) version of the tile image, used for filling...
        $tile = imagecreatetruecolor(imagesx($src_fill) * $zoom_fill, imagesy($src_fill) * $zoom_fill);
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        imagecopyresampled($tile, $src_fill, $xoffset, 0, 0, 0, imagesx($src_fill) * $zoom_fill, imagesy($src_fill) * $zoom_fill, imagesx($src_fill), imagesy($src_fill));
        imagecopyresampled($dst_im, $tile, 0, 0, 0, 0, $header_width, $header_height, $header_width, $header_height); // black
    }

    /**
     * Fill in the backgrounds in the thumbnail image
     * @param resource (Reference) $dst_im An ImageMagick resource
     * @param resource $src_fill background image
     * @param int $zoom_fill zoom factor
     * @param int $repeat_fill background repeat
     * @param int $align_fill background positioning
     * @return void The changes are made in-place to the $dst_im
     */
    private static function imagebackgroundfill(&$dst_im, $src_fill, $zoom_fill, $repeat_fill=0, $align_fill=1) {
        $layer = imagecreatetruecolor(imagesx($dst_im), imagesy($dst_im));
        // Turn off alpha blending and set alpha flag
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        // Create resized (zoomed) version of the tile image, used for filling...
        $tile = imagecreatetruecolor(imagesx($src_fill) * $zoom_fill, imagesy($src_fill) * $zoom_fill);
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        imagecopyresampled($tile, $src_fill, 0, 0, 0, 0, imagesx($src_fill) * $zoom_fill, imagesy($src_fill) * $zoom_fill, imagesx($src_fill), imagesy($src_fill));

        switch ($repeat_fill) {
            case Skin::BACKGROUND_REPEAT_NO:
                $position = self::imagebackgroundfillalign(imagesx($dst_im), imagesy($dst_im), imagesx($tile), imagesy($tile), $align_fill);
                imagecopyresampled($dst_im, $tile, $position['x'], $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                break;
            case Skin::BACKGROUND_REPEAT_X:
                $position = self::imagebackgroundfillalign(imagesx($dst_im), imagesy($dst_im), imagesx($tile), imagesy($tile), $align_fill);
                imagecopyresampled($dst_im, $tile, $position['x'], $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                $steps = ceil(imagesx($dst_im) / imagesx($tile));
                for ($x = 1; $x <= $steps; $x++) {
                    imagecopyresampled($dst_im, $tile, $position['x'] - $x * imagesx($tile), $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                    imagecopyresampled($dst_im, $tile, $position['x'] + $x * imagesx($tile), $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                }
                break;
            case Skin::BACKGROUND_REPEAT_Y:
                $position = self::imagebackgroundfillalign(imagesx($dst_im), imagesy($dst_im), imagesx($tile), imagesy($tile), $align_fill);
                imagecopyresampled($dst_im, $tile, $position['x'], $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                $steps = ceil(imagesy($dst_im) / imagesy($tile));
                for ($y = 1; $y <= $steps; $y++) {
                    imagecopyresampled($dst_im, $tile, $position['x'], $position['y'] - $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                    imagecopyresampled($dst_im, $tile, $position['x'], $position['y'] + $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                }
                break;
            case Skin::BACKGROUND_REPEAT_BOTH:
                $position = self::imagebackgroundfillalign(imagesx($dst_im), imagesy($dst_im), imagesx($tile), imagesy($tile), $align_fill);
                imagecopyresampled($dst_im, $tile, $position['x'], $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                $steps = max(ceil(imagesx($dst_im) / imagesx($tile)), ceil(imagesy($dst_im) / imagesy($tile)));
                for ($x = 1; $x <= $steps; $x++) {
                    for ($y = 1; $y <= $steps; $y++) {
                        imagecopyresampled($dst_im, $tile, $position['x'], $position['y'] - $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                        imagecopyresampled($dst_im, $tile, $position['x'], $position['y'] + $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                        imagecopyresampled($dst_im, $tile, $position['x'] - $x*imagesx($tile), $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                        imagecopyresampled($dst_im, $tile, $position['x'] + $x*imagesx($tile), $position['y'], 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                        imagecopyresampled($dst_im, $tile, $position['x'] - $x*imagesx($tile), $position['y'] - $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                        imagecopyresampled($dst_im, $tile, $position['x'] - $x*imagesx($tile), $position['y'] + $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                        imagecopyresampled($dst_im, $tile, $position['x'] + $x*imagesx($tile), $position['y'] + $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                        imagecopyresampled($dst_im, $tile, $position['x'] + $x*imagesx($tile), $position['y'] - $y * imagesy($tile), 0, 0, imagesx($tile), imagesy($tile), imagesx($tile), imagesy($tile));
                    }
                }
                break;
        }
    }


    /**
     * Generates thumbnail for skin with given $id.
     *
     * @param  mixed $id
     * @throws SystemException
     */
    private static function generate_thumbnail($id) {
        global $THEME;

        $skindata = get_record('skin', 'id', $id);
        if ($skindata == false) {
            // Couldn't find the record in the database, so
            // we need to create a new one, with default settings...
            $skindata = new Skin();
            $skin = $skindata->viewskin;
        }
        else {
            // Found the record, now we need to unserialize it...
            $skin = unserialize($skindata->viewskin);
        }

        // ========== BODY BACKGROUND COLOR ==========
        $img = imagecreatetruecolor(Skin::PREVIEW_WIDTH * Skin::PREVIEW_THUMBNAIL_ZOOM, Skin::PREVIEW_HEIGHT * Skin::PREVIEW_THUMBNAIL_ZOOM);
        // Turn off alpha blending and set alpha flag
        imagealphablending($img, true);
        imagesavealpha($img, true);

        list($r, $g, $b) = self::get_rgb_from_hex($skin['body_background_color']);
        $bodybackgroundcolor = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $bodybackgroundcolor);

        // ========== BODY BACKGROUND IMAGE ==========
        if (!empty($skin['body_background_image'])) {
            require_once(get_config('docroot') . 'artefact/file/lib.php');
            $fileid = $skin['body_background_image'];
            $fileobj = artefact_instance_from_id($fileid);
            $filetype = $fileobj->get('filetype');

            switch ($filetype) {
                case "image/gif":
                    $bodybackgroundfill = imagecreatefromgif($fileobj->get_path());
                    break;
                case "image/jpeg":
                    $bodybackgroundfill = imagecreatefromjpeg($fileobj->get_path());
                    break;
                case "image/png":
                default:
                    $bodybackgroundfill = imagecreatefrompng($fileobj->get_path());
                    break;
            }
            imagealphablending($bodybackgroundfill, false);
            imagesavealpha($bodybackgroundfill, true);

            self::imagebackgroundfill($img, $bodybackgroundfill, Skin::PREVIEW_THUMBNAIL_ZOOM, intval($skin['body_background_repeat']), intval($skin['body_background_position']));
        }

        // ========== VIEW BACKGROUND COLOR ==========
        $viewwidth = Skin::PREVIEW_WIDTH - intval(0.2  * Skin::PREVIEW_WIDTH);
        $viewheight = Skin::PREVIEW_HEIGHT;

        $img2 = imagecreatetruecolor($viewwidth * Skin::PREVIEW_THUMBNAIL_ZOOM + 1, $viewheight * Skin::PREVIEW_THUMBNAIL_ZOOM);
        // Turn off alpha blending and set alpha flag
        imagealphablending($img2, true);
        imagesavealpha($img2, true);

        list($r, $g, $b) = self::get_rgb_from_hex('#FFFFFF');
        $viewbackgroundcolor1 = imagecolorallocatealpha($img2, $r, $g, $b, 127);
        imagefill($img2, 0, 0, $viewbackgroundcolor1);

        // ========== SAMPLE HEADING BACKGROUND IMAGE/COLOUR AND TEXT ==========

        // The text in the page header is not affected by the font size chosen by the user
        $header_font_size = self::get_font_sizes($skin['view_text_font_size'])[0];

        // Allocate heading sizes, text size and emphasized size
        list($heading_size, $emphasized_size, $text_size) = self::get_font_sizes($skin['view_text_font_size']);
        list($r, $g, $b) = self::get_rgb_from_hex($skin['view_text_heading_color']);

        // Allocate heading_text_color
        list($r, $g, $b) = self::get_rgb_from_hex($skin['view_text_heading_color']);
        $heading_text_color = imagecolorallocate($img, $r, $g, $b);

        // Allocate heading_background_color
        list($r, $g, $b) = self::get_rgb_from_hex($skin['header_background_color']);
        $heading_background_color = imagecolorallocate($img, $r, $g, $b);

        $headerwidth = Skin::PREVIEW_WIDTH - intval(0.2 * Skin::PREVIEW_WIDTH);
        $headerheight = $heading_size;

        list($r, $g, $b) = self::get_rgb_from_hex('#FFFFFF');
        $viewbackgroundcolor2 = imagecolorallocate($img2, $r, $g, $b);
        imagefilledrectangle($img2, 0, $headerheight + ($header_font_size * 2.5) + 1, $viewwidth, $viewheight, $viewbackgroundcolor2);

        // Draw header background colour block on the VIEW and BODY
        imagefilledrectangle($img, 0, 0, $headerwidth, $headerheight + ($header_font_size * 2.5), $heading_background_color);

        // Replace header colour with sample header image if there is one allocated
        if (!empty($skin['header_background_image'])) {
            require_once(get_config('docroot') . 'artefact/file/lib.php');
            $fileid = $skin['header_background_image'];
            $fileobj = artefact_instance_from_id($fileid);
            $filetype = $fileobj->get('filetype');

            switch ($filetype) {
              case "image/gif":
                  $headerimage = imagecreatefromgif($fileobj->get_path());
                  break;
              case "image/jpeg":
                  $headerimage = imagecreatefromjpeg($fileobj->get_path());
                  break;
              case "image/png":
              default:
                  $headerimage = imagecreatefrompng($fileobj->get_path());
                  break;

            }

            $headerimage = imagescale($headerimage, Skin::PREVIEW_WIDTH);
            // Draw header image on the VIEW and BODY
            self::imageheaderfill($img, $headerimage,  Skin::PREVIEW_THUMBNAIL_ZOOM*1.2, $headerwidth, $headerheight + ($header_font_size * 2.6));
        }

        // Even though this text is only used in preview images, it's possible the site might want to change
        // it for localization purposes, for instance if they're primarily using a non-Latin alphabet
        $heading_text = get_string('previewheading', 'skin');
        $heading_font = self::get_path_to_previewfile($skin['view_heading_font_family'], 'heading');

        // Add the sample heading title - the font size does not affect the text within page header
        imagettftext($img2, $heading_size, 0, 10, $header_font_size+10, $heading_text_color, $heading_font, $heading_text);

        list($r, $g, $b) = self::get_rgb_from_hex($skin['view_block_header_font_color']);
        $emphasized_color = imagecolorallocate($img, $r, $g, $b);
        list($r, $g, $b) = self::get_rgb_from_hex('#CCCCCC');
        $line_color = imagecolorallocate($img, $r, $g, $b);
        $emphasized_text1 = get_string('previewsubhead1', 'skin'); // Latin for text
        $emphasized_text2 = get_string('previewsubhead2', 'skin');    // Latin for image
        $emphasized_font = self::get_path_to_previewfile($skin['view_block_header_font'], 'heading');

        // Calculate y positions for drawing
        $subheading_y_pos = $header_font_size*4 + $emphasized_size + $emphasized_size;
        $underline_y_pos = $subheading_y_pos + ($heading_size/2) ;
        $sampleimage_y_pos = $underline_y_pos + $text_size;

        // Add the sample sub-heading 10000
        imagettftext($img2, $emphasized_size, 0, 10, $subheading_y_pos, $emphasized_color, $emphasized_font, $emphasized_text1);

        // Add second column to the preview thumbnail, if the width of the view is greater or equal 70%
        // or the size of regular text is less than 8...
        if ($text_size < 8) {
            // Add sample picture
            $sample_img = imagecreatefrompng($THEME->get_path('images/skin_preview_img.png'));
            // Add the sample sub-heading 2 and underline, then merge $sample_img into $img2
            imagettftext($img2, $emphasized_size, 0, imagesx($img2) - imagesx($sample_img) - 10, $subheading_y_pos, $emphasized_color, $emphasized_font, $emphasized_text2);
            imagefilledrectangle($img2, imagesx($img2) - imagesx($sample_img) - 15, $underline_y_pos, imagesx($img2) - 5, $underline_y_pos, $line_color);

            // Add underline for sub-heading 1 where x pos stops at second column break
            imagefilledrectangle($img2, 5, $underline_y_pos, imagesx($img2) - imagesx($sample_img) - 20, $underline_y_pos, $line_color);
            imagecopyresampled($img2, $sample_img, imagesx($img2) - imagesx($sample_img) - 10, $sampleimage_y_pos, 0, 0, imagesx($sample_img), imagesy($sample_img), imagesx($sample_img), imagesy($sample_img));
        }
        else {
            imagefilledrectangle($img2, 5, $underline_y_pos, imagesx($img2) - 10, $underline_y_pos, $line_color);
        }

        // Add some sample lines of text
        list($r, $g, $b) = self::get_rgb_from_hex($skin['view_text_font_color']);
        $text_color = imagecolorallocate($img, $r, $g, $b);
        $text_font = self::get_path_to_previewfile($skin['view_text_font_family'], 'text');
        for ($i = 1; $i <= 9; $i++) {
            imagettftext($img2, $text_size, 0, 10, $underline_y_pos + $i*(2*$text_size), $text_color, $text_font, get_string("previewtextline{$i}", 'skin'));
        }

        // ========== COPY VIEW PART OVER BODY PART OF THE THUMBNAIL ==========
        $viewbackgroundmargin = intval(((Skin::PREVIEW_WIDTH - intval(0.8 * Skin::PREVIEW_WIDTH)) / 2) * Skin::PREVIEW_THUMBNAIL_ZOOM);
        $VIEWOFFSETX = $viewbackgroundmargin - 1;
        $VIEWOFFSETY = 0;

        imagecopyresampled($img, $img2, $VIEWOFFSETX, $VIEWOFFSETY, 0, 0, imagesx($img2), imagesy($img2), imagesx($img2), imagesy($img2));

        // ========== SAVE GENERATED THUMBNAIL ==========
        if (!check_dir_exists(get_config('dataroot') . 'skins/', true, true)) {
            $fontpath = get_config('dataroot') . 'skins/';
            throw new SystemException("Unable to create folder $fontpath");
        }
        else {
            $thumbnail = get_config('dataroot') . 'skins/' . $id . '.png';
            imagepng($img, $thumbnail);
            imagedestroy($img);
        }
    }


    /**
     * Given a background image to delete, remove it from skin and update the skin thumbs
     *
     * @param int $aid Artefact id of the background image to remove from skins
     */
    public static function remove_background($aid) {
        $skinstoupdate = get_records_select_array('skin', 'bodybgimg = ? OR headingbgimg = ?', array($aid, $aid), 'id');
        if (!empty($skinstoupdate) && is_array($skinstoupdate)) {
            foreach ($skinstoupdate as $skin) {
                $skin = new Skin($skin->id);
                $viewskin = $skin->get('viewskin');
                if (isset($viewskin['body_background_image']) && $viewskin['body_background_image'] == $aid) {
                    $viewskin['body_background_image'] = 0;
                }
                if (isset($viewskin['header_background_image']) && $viewskin['header_background_image'] == $aid) {
                    $viewskin['header_background_image'] = 0;
                }
                $skin->set('viewskin', $viewskin);
                $skin->commit();
            }
        }
    }

    /**
     * Fetches data about site fonts
     * @param int $limit
     * @param int $offset
     * @param string $fonttype a filter which will only fetch fonts of this type (or 'all' to fetch all fonts)
     * @return array The 'data' element is the paged set of fonts, while the 'count' field is the count of all the fonts
     */
    public static function get_sitefonts_data($limit=9, $offset=0, $fonttype='site') {

        if ($fonttype == 'theme') {
            $count = count_records_sql('
                SELECT COUNT(f.name) FROM {skin_fonts} f
                WHERE f.fonttype LIKE ?', array('t_%'));
            $fontdata = get_records_sql_array('
                SELECT * FROM {skin_fonts} WHERE fonttype LIKE ? ORDER BY fonttype', array('t_%'), $offset, $limit);
        }
        else if ($fonttype != 'all') {
            $count = count_records_sql('
                SELECT COUNT(f.name) FROM {skin_fonts} f
                WHERE f.fonttype = ?', array($fonttype));
            $fontdata = get_records_array('skin_fonts', 'fonttype', $fonttype, 'title', '*', $offset, $limit);
        }
        else {
            // site and google fonts, that means all fonts except 'common'...
            $count = count_records_sql('
                SELECT COUNT(f.name) FROM {skin_fonts} f
                WHERE f.fonttype != ?', array('common'));
            $fontdata = get_records_select_array('skin_fonts', 'fonttype != ?', array('common'), 'title', '*', $offset, $limit);
        }

        $data = array();
        if ($fontdata) {
            for ($i = 0; $i < count($fontdata); $i++) {
                //$index[$fontdata[$i]->id] = $i;
                //$data[$i]['id'] = $fontdata[$i]->id;
                $data[$i]['name'] = $fontdata[$i]->name;
                $data[$i]['title'] = $fontdata[$i]->title;
                $data[$i]['urlencode'] = urlencode($fontdata[$i]->title);
                $data[$i]['variants'] = unserialize($fontdata[$i]->variants);
                $data[$i]['fonttype'] = $fontdata[$i]->fonttype;
                $data[$i]['fontstack'] = explode(",", trim($fontdata[$i]->fontstack, "'"));
                $data[$i]['genericfont'] = $fontdata[$i]->genericfont;
            }
        }

        return (object) array(
            'data'  => $data,
            'count' => $count,
        );
    }


    /**
     * Returns a list of all the font options
     * @return array
     */
    public static function get_all_font_options() {
        $fontdata = get_records_array('skin_fonts', '', '', 'title', 'name, title, fontstack, genericfont');

        $options = array();
        if ($fontdata) {
            foreach ($fontdata as $singlefont) {
                $options[$singlefont->name] = array(
                    'value' => $singlefont->title,
                    'style' => 'font-family: '.$singlefont->fontstack.', '.$singlefont->genericfont.';'
                );
            }
        }
        return $options;
    }

    /**
     * Returns an array of theme's with their theme fonts.
     * Currently all themes have one font for both heading and text except for 'raw'
     * @param string $type   Set the type of font, eg text vs heading
     * @return array of themes with their fonts
     */
    public static function get_all_theme_fonts($type='text') {
        $genericfont = ($type == 'text') ? 'sans-serif' : 'serif';
        $fontdata = get_records_sql_array('
            SELECT SUBSTRING(fonttype, 3) AS theme, fonttype, name, genericfont FROM {skin_fonts} WHERE fonttype LIKE ? ORDER BY fonttype', array('t_%')
        );
        $data = array();
        if ($fontdata) {
            foreach ($fontdata as $font) {
                if ($font->theme != 'raw' || ($font->theme == 'raw' && $font->genericfont == $genericfont)) {
                    $data[$font->theme] = $font->name;
                }
            }
        }
        return $data;
    }


    /**
     * Returns all "text only" fonts
     * @return array
     */
    public static function get_textonly_font_options() {
        $fontdata = get_records_array('skin_fonts', 'onlyheading', 0, 'title', 'name, title, fontstack, genericfont');

        $options = array();
        if ($fontdata) {
            foreach ($fontdata as $singlefont) {
                $options[$singlefont->name] = array(
                    'value' => $singlefont->title,
                    'style' => 'font-family: '.$singlefont->fontstack.', '.$singlefont->genericfont.';'
                );
            }
        }
        return $options;
    }


    /**
     * Checks and generates a unique font name for newly imported/uploaded Font
     * @param unknown_type $fontname
     * @return string
     */
    public static function new_font_name($fontname) {
        $taken = get_column_sql('
            SELECT name
            FROM {skin_fonts}');
        $ext = ''; $i = 0;
        if ($taken) {
            while (in_array($fontname . $ext, $taken)) {
                $ext = ++$i;
            }
        }
        return $fontname . $ext;
    }

    /**
     * Checks to see if font exists during importation
     * @param unknown_type $fonttitle
     * @return string
     */
    public static function font_exists($fonttitle) {
        $titles = get_column_sql('
            SELECT title
            FROM {skin_fonts}');
        if ($titles) {
            if (in_array($fonttitle, $titles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the stylesheets needed to display this skin.
     * @param  int|null $viewid The view ID to display the skin on
     * @return array one stylesheet per entry
     */
    public function get_stylesheets($viewid = null) {
        if (!$this->id) {
            throw new SkinNotFoundException("Can't display the stylesheet for a skin that hasn't been committed to the DB yet.");
        }
        $sheets = array();
        $skinversion = preg_replace('/[^0-9]/', '', $this->mtime);
        $sheets[] = get_config('wwwroot') . 'skin/style.php?skin=' . $this->id . '&skinversion=' . $skinversion
            . ($viewid ? "&view={$viewid}" : '');
        $skindata = $this->viewskin;
        // If google web font is selected, than add appropriate CSS...
        $textfont = get_field('skin_fonts', 'fonttype', 'name', $skindata['view_text_font_family']);
        $protocol = (is_https()) ? 'https://' : 'http://';
        if ($textfont == 'google') {
            $sheets[] = $protocol . 'fonts.googleapis.com/css?family=' . str_replace('_', '+', $skindata['view_text_font_family']);
        }
        $headingfont = get_field('skin_fonts', 'fonttype', 'name', $skindata['view_heading_font_family']);
        if ($headingfont == 'google') {
            $sheets[] = $protocol . 'fonts.googleapis.com/css?family=' . str_replace('_', '+', $skindata['view_heading_font_family']);
        }
        return $sheets;
    }


    /**
     * Indicates whether or not the current user is allowed to edit this skin
     * @return boolean
     */
    public function can_edit() {
        global $USER;
        // You can edit a view if you're the owner, or if it's a site skin and you're the admin
        return $this->owner == $USER->get('id') || ($this->type == 'site' && $USER->get('admin'));
    }

    /**
     * Indicates whether or not the current user is allowed to assign this skin to a View
     * @return boolean
     */
    public function can_use() {
        global $USER;
        return $this->type == 'public' || $this->type == 'site' || ($this->owner == $USER->get('id'));
    }

    /**
     * Indicates whether or not the current user is allowed to see this skin at all
     * @return boolean
     */
    public function can_view() {
        // TODO: For tighter control of views, it might be good to implement this. It would have to check for whether it's
        // public, or whether it's used in a view that you are allowed to look at
        return true;
    }
}


/**
 * Installs default skin data.
 * This function is now designed so that it can be run more than once.
 * To add more fonts set up the "ensure_record_exists( ... )" blocks for them
 * To remove a font set up a "delete_record( ... )" for it
 */
function install_skins_default() {
    // Add data for the 'common' and theme fonts.
    // Set up the possible variations
    // - add in new ones if needed
    $fv = array(
        'regular'     => array('variant' => 'regular', 'font-weight' => 'normal', 'font-style' => 'normal'),
        'bold'        => array('variant' => 'bold', 'font-weight' => 'bold', 'font-style' => 'normal'),
        'italic'      => array('variant' => 'italic', 'font-weight' => 'normal', 'font-style' => 'italic'),
        'bolditalic'  => array('variant' => 'bolditalic', 'font-weight' => 'bold', 'font-style' => 'italic'),
        'light'       => array('variant' => 'light', 'font-weight' => 'lighter', 'font-style' => 'normal'),
        'lightitalic' => array('variant' => 'light', 'font-weight' => 'lighter', 'font-style' => 'italic'),
        '300'         => array('variant' => 'light', 'font-weight' => 'lighter', 'font-style' => 'normal'),
        '700'         => array('variant' => 'bold', 'font-weight' => 'bold', 'font-style' => 'normal'),
        '900'         => array('variant' => 'bold', 'font-weight' => 'bolder', 'font-style' => 'normal'),
    );
    // The basic regular/bold/italic/bolditalic combo
    $basicvariants = serialize(array($fv['regular'], $fv['bold'], $fv['italic'], $fv['bolditalic']));

    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Arial',
            'title' => 'Arial'
        ),
        (object) array(
            'name' => 'Arial',
            'title' => 'Arial',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'NimbusSansL.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Arial\', \'Helvetica\', \'Nimbus Sans L\', \'FreeSans\'',
            'genericfont' => 'sans-serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'BookAntiqua',
            'title' => 'Book Antiqua'
        ),
        (object) array(
            'name' => 'BookAntiqua',
            'title' => 'Book Antiqua',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'URWPalladioL.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Book Antiqua\', \'Palatino Linotype\', \'Palatino\', \'URW Palladio L\'',
            'genericfont' => 'serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Bookman',
            'title' => 'Bookman'
        ),
        (object) array(
            'name' => 'Bookman',
            'title' => 'Bookman',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'URWBookmanL.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Bookman Old Style\', \'Bookman\', \'URW Bookman L\'',
            'genericfont' => 'serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Chancery',
            'title' => 'Chancery'
        ),
        (object) array(
            'name' => 'Chancery',
            'title' => 'Chancery',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'URWChanceryL.ttf',
            'variants' => serialize(array($fv['italic'])),
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Monotype Corsiva\', \'Apple Chancery\', \'Zapf Chancery\', \'URW Chancery L\'',
            'genericfont' => 'cursive'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Courier',
            'title' => 'Courier New'
        ),
        (object) array(
            'name' => 'Courier',
            'title' => 'Courier New',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'NimbusMonoL.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Courier New\', \'Courier\', \'Nimbus Mono L\', \'FreeMono\'',
            'genericfont' => 'monospace'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Georgia',
            'title' => 'Georgia'
        ),
        (object) array(
            'name' => 'Georgia',
            'title' => 'Georgia',
            'licence' => 'Charis SIL Open Font Licence.txt',
            'previewfont' => 'CharisSILR.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Georgia\', \'Charis SIL\'',
            'genericfont' => 'serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Gothic',
            'title' => 'Century Gothic'
        ),
        (object) array(
            'name' => 'Gothic',
            'title' => 'Century Gothic',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'URWGothicL.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Century Gothic\', \'Avant Garde\', \'URW Gothic L\'',
            'genericfont' => 'sans-serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Helvetica',
            'title' => 'Helvetica'
        ),
        (object) array(
            'name' => 'Helvetica',
            'title' => 'Helvetica',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'NimbusSansL.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Helvetica\', \'Arial\', \'Nimbus Sans L\', \'FreeSans\'',
            'genericfont' => 'sans-serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Palatino',
            'title' => 'Palatino'
        ),
        (object) array(
            'name' => 'Palatino',
            'title' => 'Palatino',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'URWPalladioL.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Palatino Linotype\', \'Palatino\', \'URW Palladio L\', \'Book Antiqua\'',
            'genericfont' => 'serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Tahoma',
            'title' => 'Tahoma'
        ),
        (object) array(
            'name' => 'Tahoma',
            'title' => 'Tahoma',
           'licence' => 'DejaVu Font Licence.txt',
            'previewfont' => 'DejaVuSans.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Tahoma\', \'DejaVu Sans\'',
            'genericfont' => 'sans-serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Times',
            'title' => 'Times New Roman'
        ),
        (object) array(
            'name' => 'Times',
            'title' => 'Times New Roman',
            'licence' => 'GPL-2.0.txt',
            'previewfont' => 'NimbusRomanNo9L.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Times New Roman\', \'Times\', \'Nimbus Roman No9\', \'FreeSerif\'',
            'genericfont' => 'serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Trebuchet',
            'title' => 'Trebuchet'
        ),
        (object) array(
            'name' => 'Trebuchet',
            'title' => 'Trebuchet',
            'licence' => 'Aurulent Open Font Licence.txt',
            'previewfont' => 'AurulentSans.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Trebuchet MS\', \'Aurulent Sans\'',
            'genericfont' => 'sans-serif'
        )
    );
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Verdana',
            'title' => 'Verdana'
        ),
        (object) array(
            'name' => 'Verdana',
            'title' => 'Verdana',
            'licence' => 'DejaVu Font Licence.txt',
            'previewfont' => 'DejaVuSans.ttf',
            'variants' => $basicvariants,
            'fonttype' => 'common',
            'onlyheading' => 0,
            'fontstack' => '\'Verdana\', \'DejaVu Sans\'',
            'genericfont' => 'sans-serif'
        )
    );

    // Theme fonts
    $filetypes = array('EOT', 'SVG', 'TTF', 'WOFF', 'WOFF2', 'OTF');
    $robotoslabvariants = array();
    foreach (array('bold', 'regular', 'light') as $option) {
        $robotoslabvariants[$option] = $fv[$option];
        foreach ($filetypes as $type) {
            $robotoslabvariants[$option][$type] = 'robotoslab-' . $option . '.' . strtolower($type);
        }
    }
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'RobotoSlab',
            'title' => 'Roboto Slab'
        ),
        (object) array(
            'name' => 'RobotoSlab',
            'title' => 'Roboto Slab',
            'licence' => '',
            'previewfont' => 'robotoslab-regular.ttf',
            'variants' => serialize($robotoslabvariants),
            'fonttype' => 't_raw',
            'onlyheading' => 0,
            'fontstack' => '\'Roboto Slab\', \'Roboto\'',
            'genericfont' => 'serif'
        )
    );

    $opensansvariants = array();
    foreach (array('bold', 'regular', 'light', 'lightitalic') as $option) {
        $opensansvariants[$option] = $fv[$option];
        foreach ($filetypes as $type) {
            if ($option == 'lightitalic') {
                $opensansvariants[$option][$type] = 'OpenSansLightItalic.' . strtolower($type);
            }
            else {
                $opensansvariants[$option][$type] = 'OpenSans' . ucfirst($option) . '.' . strtolower($type);
            }
        }
    }
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'OpenSans',
            'title' => 'Open Sans'
        ),
        (object) array(
            'name' => 'OpenSans',
            'title' => 'Open Sans',
            'licence' => '',
            'previewfont' => 'OpenSansRegular.ttf',
            'variants' => serialize($opensansvariants),
            'fonttype' => 't_raw',
            'onlyheading' => 0,
            'fontstack' => '\'Open Sans\', \'Verdana\'',
            'genericfont' => 'sans-serif'
        )
    );

    $osvaldovariants = array();
    foreach (array('regular') as $option) {
        $osvaldovariants[$option] = $fv[$option];
        foreach ($filetypes as $type) {
            $osvaldovariants[$option][$type] = 'osvaldo-' . $option . '.' . strtolower($type);
        }
    }
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Osvaldo',
            'title' => 'Osvaldo'
        ),
        (object) array(
            'name' => 'Osvaldo',
            'title' => 'Osvaldo',
            'licence' => 'SIL Open Font License.txt',
            'previewfont' => 'osvaldo-regular.ttf',
            'variants' => serialize($osvaldovariants),
            'fonttype' => 't_ocean',
            'onlyheading' => 0,
            'fontstack' => '\'Osvaldo\', \'Verdana\'',
            'genericfont' => 'sans-serif'
        )
    );

    $ralewayvariants = array();
    foreach (array('bold', 'regular', 'italic', 'light', 'lightitalic') as $option) {
        $ralewayvariants[$option] = $fv[$option];
        foreach ($filetypes as $type) {
            $ralewayvariants[$option][$type] = 'raleway-' . $option . '.' . strtolower($type);
        }
    }
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Raleway',
            'title' => 'Raleway'
        ),
        (object) array(
            'name' => 'Raleway',
            'title' => 'Raleway',
            'licence' => 'OFL.txt',
            'previewfont' => 'raleway-regular.ttf',
            'variants' => serialize($ralewayvariants),
            'fonttype' => 't_modern',
            'onlyheading' => 0,
            'fontstack' => '\'Raleway\'',
            'genericfont' => 'sans-serif'
        )
    );

    $shadowsintolight2variants = array();
    foreach (array('regular') as $option) {
        $shadowsintolight2variants[$option] = $fv[$option];
        foreach ($filetypes as $type) {
            $shadowsintolight2variants[$option][$type] = 'shadows-into-light-two-v6-latin-ext_latin-' . $option . '.' . strtolower($type);
        }
    }
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'ShadowsIntoLightTwo',
            'title' => 'Shadows Into Light Two'
        ),
        (object) array(
            'name' => 'ShadowsIntoLightTwo',
            'title' => 'Shadows Into Light Two',
            'licence' => 'OFL.txt',
            'previewfont' => 'shadows-into-light-two-v6-latin-ext_latin-regular.ttf',
            'variants' => serialize($shadowsintolight2variants),
            'fonttype' => 't_primaryschool',
            'onlyheading' => 0,
            'fontstack' => '\'Shadows Into Light Two\'',
            'genericfont' => 'sans-serif'
        )
    );

    $alegreyavariants = array();
    foreach (array('700', '900') as $option) {
        $alegreyavariants[$option] = $fv[$option];
        foreach ($filetypes as $type) {
            $alegreyavariants[$option][$type] = 'alegreya-v13-latin-ext_latin-' . $option . '.' . strtolower($type);
        }
    }
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'Alegreya',
            'title' => 'Alegreya'
        ),
        (object) array(
            'name' => 'Alegreya',
            'title' => 'Alegreya',
            'licence' => 'OFL.txt',
            'previewfont' => 'alegreya-v13-latin-ext_latin-700.ttf',
            'variants' => serialize($alegreyavariants),
            'fonttype' => 't_maroon',
            'onlyheading' => 1,
            'fontstack' => '\'Alegreya\'',
            'genericfont' => 'serif'
        )
    );

    $alegreyasansvariants = array();
    foreach (array('300', '700', '900', 'italic', 'regular') as $option) {
        $alegreyasansvariants[$option] = $fv[$option];
        foreach ($filetypes as $type) {
            $alegreyasansvariants[$option][$type] = 'alegreya-sans-v10-latin-ext_latin-' . $option . '.' . strtolower($type);
        }
    }
    ensure_record_exists('skin_fonts',
        (object) array(
            'name' => 'AlegreyaSans',
            'title' => 'Alegreya Sans'
        ),
        (object) array(
            'name' => 'AlegreyaSans',
            'title' => 'Alegreya Sans',
            'licence' => 'OFL.txt',
            'previewfont' => 'alegreya-sans-v10-latin-ext_latin-regular.ttf',
            'variants' => serialize($alegreyasansvariants),
            'fonttype' => 't_maroon',
            'onlyheading' => 0,
            'fontstack' => '\'Alegreya Sans\'',
            'genericfont' => 'sans-serif'
        )
    );
}
