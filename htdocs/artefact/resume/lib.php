<?php
/**
 *
 * @package    mahara
 * @subpackage artefact-resume
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

defined('INTERNAL') || die();

class PluginArtefactResume extends PluginArtefact {

    public static function get_artefact_types() {
        return array(
            'coverletter',
            'contactinformation',
            'personalinformation',
            'employmenthistory',
            'educationhistory',
            'certification',
            'book',
            'membership',
            'interest',
            'personalgoal',
            'academicgoal',
            'careergoal',
            'personalskill',
            'academicskill',
            'workskill'
        );
    }

    public static function get_block_types() {
        return array();
    }

    public static function get_plugin_name() {
        return 'resume';
    }

    /**
     * Fetch the human readable name for the plugin
     *
     * @return string
     */
    public static function get_plugin_display_name() {
        return get_string('pluginname', 'artefact.resume');
    }

    public static function is_active() {
        return get_field('artefact_installed', 'active', 'name', 'resume');
    }

    public static function menu_items() {
        return array(
            'create/resume' => array(
                'path' => 'create/resume',
                'title' => get_string('resume', 'artefact.resume'),
                'url' => 'artefact/resume/index.php',
                'weight' => 60,
            ),
        );
    }

    public static function submenu_items() {
        $tabs = array(
            'subnav' => array(
                'class' => 'resume'
            ),
            'index' => array(
                'page'  => 'index',
                'url'   => 'artefact/resume/index.php',
                'title' => get_string('introduction', 'artefact.resume'),
            ),
            'education' => array(
                'page'  => 'education',
                'url'   => 'artefact/resume/education.php',
                'title' => get_string('education', 'artefact.resume'),
            ),
            'employment' => array(
                'page'  => 'employment',
                'url'   => 'artefact/resume/employment.php',
                'title' => get_string('employment', 'artefact.resume'),
            ),
            'achievements' => array(
                'page'  => 'achievements',
                'url'   => 'artefact/resume/achievements.php',
                'title' => get_string('achievements', 'artefact.resume'),
            ),
            'goalsandskills' => array(
                'page'  => 'goalsandskills',
                'url'   => 'artefact/resume/goalsandskills.php',
                'title' => get_string('goalsandskills', 'artefact.resume'),
            ),
            'interests' => array(
                'page'  => 'interests',
                'url'   => 'artefact/resume/interests.php',
                'title' => get_string('interests', 'artefact.resume'),
            ),
            'license' => array(
                'page'  => 'license',
                'url'   => 'artefact/resume/license.php',
                'title' => get_string('license', 'artefact.resume'),
            ),
        );
        if (!get_config('licensemetadata')) {
            unset($tabs['license']);
        }
        if (defined('MENUITEM_SUBPAGE') && isset($tabs[MENUITEM_SUBPAGE])) {
            $tabs[MENUITEM_SUBPAGE]['selected'] = true;
        }
        return $tabs;
    }

    public static function composite_tabs() {
        return array(
            'educationhistory'  => 'education',
            'employmenthistory' => 'employment',
            'certification'     => 'achievements',
            'book'              => 'achievements',
            'membership'        => 'achievements',
        );
    }

    public static function artefact_export_extra_artefacts($artefactids) {
        if (!$artefacts = get_column_sql("
            SELECT artefact
            FROM {artefact_attachment}
            WHERE artefact IN (" . join(',', $artefactids) . ')', array())) {
            return array();
        }
        if ($attachments = get_column_sql('
            SELECT attachment
            FROM {artefact_attachment}
            WHERE artefact IN (' . join(',', $artefacts). ')')) {
            $artefacts = array_merge($artefacts, $attachments);
        }
        return $artefacts;
    }

    public static function progressbar_link($artefacttype) {
        switch ($artefacttype) {
            case 'coverletter':
            case 'personalinformation':
                return 'artefact/resume/index.php';
                break;
            case 'educationhistory':
                return 'artefact/resume/education.php';
                break;
            case 'employmenthistory':
                return 'artefact/resume/employment.php';
                break;
            case 'certification':
            case 'book':
            case 'membership':
                return 'artefact/resume/achievements.php';
                break;
            case 'personalgoal':
            case 'academicgoal':
            case 'careergoal':
            case 'personalskill':
            case 'academicskill':
            case 'workskill':
                return 'artefact/resume/goalsandskills.php';
                break;
            case 'interest':
                return 'artefact/resume/interests.php';
                break;
            default:
                return '';
        }
    }
}

class ArtefactTypeResume extends ArtefactType {

    public static function get_icon($options=null) {
        return false;
    }

    public function __construct($id=0, $data=array()) {
        if (empty($id)) {
            $data['title'] = get_string($this->get_artefact_type(), 'artefact.resume');
        }
        parent::__construct($id, $data);
    }

    public static function is_singular() {
        return false;
    }

    public static function format_child_data($artefact, $pluginname) {
        $a = new stdClass();
        $a->id         = $artefact->id;
        $a->isartefact = true;
        $a->title      = '';
        $a->text       = get_string($artefact->artefacttype, 'artefact.resume'); // $artefact->title;
        $a->container  = (bool) $artefact->container;
        $a->parent     = $artefact->id;
        return $a;
    }

    public static function get_links($id) {
        // @todo Catalyst IT Limited
    }

    /**
     * Default render method for resume fields - show their description
     */
    public function render_self($options) {
        return array('html' => clean_html($this->description));
    }

    /**
     * Overrides the default commit to make sure that any 'entireresume' blocks
     * in views the user have know about this artefact - but only if necessary.
     * Goals and skills are not in the entireresume block
     */
    public function commit() {
        parent::commit();

        if ($blockinstances = get_records_sql_array('
            SELECT id, "view", configdata
            FROM {block_instance}
            WHERE blocktype = \'entireresume\'
            AND "view" IN (
                SELECT id
                FROM {view}
                WHERE "owner" = ?)', array($this->owner))) {
            foreach ($blockinstances as $blockinstance) {
                $whereobject = (object)array(
                    'view' => $blockinstance->view,
                    'artefact' => $this->get('id'),
                    'block' => $blockinstance->id,
                );
                ensure_record_exists('view_artefact', $whereobject, $whereobject);
            }
        }
    }

    public function get_license_artefact() {
        if ($this->get_artefact_type() == 'personalinformation')
            return $this;

        $pi = get_record('artefact',
                         'artefacttype', 'personalinformation',
                         'owner', $this->owner);
        if (!$pi)
            return null;

        require_once(get_config('docroot') . 'artefact/lib.php');
        return artefact_instance_from_id($pi->id);
    }


    public function render_license($options, &$smarty) {
        if (!empty($options['details']) and get_config('licensemetadata')) {
            $smarty->assign('license', render_license($this->get_license_artefact()));
        }
        else {
            $smarty->assign('license', false);
        }
    }

    /**
     * Render the import entry request for resume fields
     */
    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        return clean_html($entry_content['description']);
    }
}

class ArtefactTypeCoverletter extends ArtefactTypeResume {

    public static function is_singular() {
        return true;
    }

    public function __construct($id=0, $data=array()) {
        if (empty($id)) {
            $data['title'] = get_string($this->get_artefact_type(), 'artefact.resume');
        }
        parent::__construct($id, $data);
    }

    public function render_self($options) {
        $smarty = smarty_core();
        $smarty->assign('content',  $this->description);
        return array('html' => $smarty->fetch('artefact:resume:fragments/coverletter.tpl'));
    }

}

class ArtefactTypeInterest extends ArtefactTypeResume {

    public static function is_singular() {
        return true;
    }

    public function render_self($options) {
        $smarty = smarty_core();
        $smarty->assign('content',  $this->description);
        return array('html' => $smarty->fetch('artefact:resume:fragments/interest.tpl'));
    }

}

class ArtefactTypeContactinformation extends ArtefactTypeResume {

    public function render_self($options) {
        $smarty = smarty_core();
        $fields = ArtefactTypeContactinformation::get_profile_fields();
        foreach ($fields as $f) {
            try {
                $$f = artefact_instance_from_type($f, $this->get('owner'));
                $rendered = $$f->render_self(array());
                $smarty->assign($f, $rendered['html']);
                $smarty->assign('hascontent', true);
            }
            catch (Exception $e) { }
        }

        $this->render_license($options, $smarty);

        return array('html' => $smarty->fetch('artefact:resume:fragments/contactinformation.tpl'));
    }

    public static function is_singular() {
        return true;
    }

    public static function setup_new($userid) {
        try {
            return artefact_instance_from_type('contactinformation', $userid);
        } catch (ArtefactNotFoundException $e) {
            $artefact = new ArtefactTypeContactinformation(null, array(
                'owner' => $userid,
                'title' => get_string('contactinformation', 'artefact.resume')
            ));
            $artefact->commit();
        }
        return $artefact;
    }

    public static function get_profile_fields() {
        static $fields = array(
            'address',
            'town',
            'city',
            'country',
            'faxnumber',
            'businessnumber',
            'homenumber',
            'mobilenumber'
        );
        return $fields;
    }

    public static function is_allowed_in_progressbar() {
        return false;
    }
}

class ArtefactTypePersonalinformation extends ArtefactTypeResume {

    protected $composites;

    public function __construct($id=0, $data=null) {
        if (empty($id)) {
            $data['title'] = get_string('personalinformation', 'artefact.resume');
        }
        parent::__construct($id, $data);
        $this->composites = ArtefactTypePersonalinformation::get_composite_fields();
        if (!empty($id)) {
            $this->composites = (array)get_record('artefact_resume_personal_information', 'artefact', $id,
                null, null, null, null, '*, ' . db_format_tsfield('dateofbirth'));
        }
    }

    public function set_composite($field, $value) {
        if (!array_key_exists($field, $this->composites)) {
            throw new InvalidArgumentException("Tried to set a non existent composite, $field");
        }
        if ($this->composites[$field] == $value) {
            return true;
        }
        // only set it to dirty if it's changed
        $this->dirty = true;
        $this->mtime = time();
        $this->composites[$field] = $value;
    }

    public function get_composite($field) {
        return $this->composites[$field];
    }

    public function commit() {
        if (empty($this->dirty)) {
            return true;
        }

        db_begin();

        $data = new stdClass();
        $have_composites = false;
        foreach ($this->composites as $field => $value) {
            if ($field != 'artefact' && !empty($value)) {
                $have_composites = true;
            }
            if ($field == 'dateofbirth' && !empty($value)) {
                $value = db_format_timestamp($value);
            }
            if ($field == 'gender' && $value=='') {
                $value = null;
            }
            $data->{$field} = $value;
        }
        if (!$have_composites) {
            if (!empty($this->id)) {
                // need to delete empty personal information
                $this->delete();
            }
            db_commit();
            return true;
        }
        $inserting = empty($this->id);
        parent::commit();
        $data->artefact = $this->id;
        if ($inserting) {
            insert_record('artefact_resume_personal_information', $data);
        }
        else {
            update_record('artefact_resume_personal_information', $data, 'artefact');
        }

        db_commit();
    }

    public static function get_composite_fields() {
        static $composites = array(
            'dateofbirth' => null,
            'placeofbirth' => null,
            'citizenship' => null,
            'visastatus' => null,
            'gender' => null,
            'maritalstatus' => null,
        );
        return $composites;
    }

    public static function is_singular() {
        return true;
    }

    public static function render_fields(ArtefactTypePersonalinformation $a=null, $options=array(), $values=null) {
        $smarty = smarty_core();
        $fields = array();
        foreach (array_keys(ArtefactTypePersonalinformation::get_composite_fields()) as $field) {
            if ($values && isset($values[$field])) {
                $value = $values[$field];
                // TODO: Make this be a call to a subclass instead of a hard-coded listing
                // of special behaviors for particular fields
                if ($field == 'dateofbirth') {
                    if (empty($value)) {
                        $value = '';
                    }
                    else {
                        $value = strtotime($value);
                    }
                }
            }
            else if ($a) {
                $value = $a->get_composite($field);
            }
            else {
                continue;
            }
            if ($field == 'gender' && !empty($value)) {
                // lang strings changed so need to make changes to deal with new lang string identifiers
                $field .= '1';
                if ($value == 'male') {
                    $value = get_string('man', 'artefact.resume');
                }
                else if ($value == 'female') {
                    $value = get_string('woman', 'artefact.resume');
                }
            }
            if ($field == 'dateofbirth' && !empty($value)) {
                $value = format_date($value+3600, 'strftimedate');
            }
            $fields[get_string($field, 'artefact.resume')] = $value;
        }
        $smarty->assign('fields', $fields);
        if ($a) {
            $a->render_license($options, $smarty);
        }
        return $smarty->fetch('artefact:resume:fragments/personalinformation.tpl');
    }

    public function render_self($options) {
        return array('html' => self::render_fields($this, $options), 'javascript' => '');
    }

    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        return self::render_fields(null, array(), $entry_content);
    }

    public function delete() {
        db_begin();

        delete_records('artefact_resume_personal_information', 'artefact', $this->id);
        parent::delete();

        db_commit();
    }

    public static function bulk_delete($artefactids, $log=false) {
        if (empty($artefactids)) {
            return;
        }

        $idstr = join(',', array_map('intval', $artefactids));

        db_begin();
        delete_records_select('artefact_resume_personal_information', 'artefact IN (' . $idstr . ')');
        parent::bulk_delete($artefactids);
        db_commit();
    }

    /**
     * returns duplicated artefacts which have the same values of the following fields:
     *  - owner
     *  - type
     *  - content which has:
     *      - dateofbirth
     *      - placeofbirth
     *      - citizenship
     *      - visastatus
     *      - gender
     *      - maritalstatus
     *
     * @param array $values
     */
    public static function get_duplicated_artefacts(array $values) {
        $fields = array('dateofbirth', 'placeofbirth', 'citizenship', 'visastatus', 'gender', 'maritalstatus');
        $where = array();
        $wherevalues = array($values['owner'], $values['type']);
        $wherestr = 'WHERE a.owner = ? AND a.artefacttype = ?';
        $contentvalues = $values['content'];
        foreach ($fields as $f) {
            if (!isset($contentvalues[$f])) {
                $wherestr .= ' AND ar.' . $f . ' IS NULL';
            }
            if (!empty($contentvalues[$f])) {
                $where[] = "ar.$f = ?";
                $wherevalues[] = $contentvalues[$f];
            }
        }
        $wherestr .= (!empty($where) ? ' AND ' . join(' AND ', $where) : '');
        return get_column_sql('
            SELECT DISTINCT a.id
            FROM {artefact} AS a
            INNER JOIN {artefact_resume_personal_information} AS ar
            ON a.id = ar.artefact
            ' . $wherestr, $wherevalues
        );
    }
}

/**
 * Helper interface to hold ArtefactTypeResumeComposite's abstract static methods
 */
interface IArtefactTypeResumeComposite {
    /**
    * This function should return a snippet of javascript
    * to be plugged into a table renderer instantiation
    * it comprises the cell function definition
    */
    public static function get_tablerenderer_js();

    public static function get_tablerenderer_title_js_string();

    public static function get_tablerenderer_body_js_string();

    /**
    * This function should return an array suitable to
    * put into the 'elements' part of a pieform array
    * to generate a form to add an instance
    */
    public static function get_addform_elements();
}

abstract class ArtefactTypeResumeComposite extends ArtefactTypeResume implements IArtefactTypeResumeComposite {

    public static function is_singular() {
        return true;
    }

    public static function is_wysiwyg() {
        return false;
    }

    public function can_have_attachments() {
        return true;
    }

    public static function get_composite_artefact_types() {
        return array(
            'employmenthistory',
            'educationhistory',
            'certification',
            'book',
            'membership'
        );
    }

    public static function get_tablerenderer_extra_js_string() {
        return '';
    }

    public static function get_tablerenderer_attachments_js_string(){
        return '';
    }

    /**
     * Can be overridden to format data retrieved from artefact tables for
     * display of the resume artefact by render_self
     */
    public static function format_render_self_data($data) {
        return $data;
    }

    /**
    * This function processes the form for the composite
    * @throws Exception
    */
    public static function process_compositeform(Pieform $form, $values) {
        global $USER;
        $result = self::ensure_composite_value($values, $values['compositetype'], $USER->get('id'));
        if (isset($result['error'])) {
            $form->reply(PIEFORM_ERR, array('message' => $result['error']));
            if (isset($result['goto'])) {
                redirect($result['goto']);
            }
        }
        else {
            return $result;
        }
    }

    /**
     * Ensures that the given value for the given composite is present
     * TODO: expand on these docs.
     * @param unknown_type $values
     * @param unknown_type $compositetype
     * @param unknown_type $owner
     * @return array If successful, an array containing 'artefactid' and 'itemid'
     *               Otherwise, an array containing 'error' and optionally 'goto'
     * @throws SystemException
     */
    public static function ensure_composite_value($values, $compositetype, $owner) {
        global $USER;
        if (!in_array($compositetype, self::get_composite_artefact_types())) {
            throw new SystemException("ensure_composite_value called with invalid composite type");
        }
        try {
            $a = artefact_instance_from_type($compositetype, $owner);
            $a->set('mtime', time());
        }
        catch (Exception $e) {
            $classname = generate_artefact_class_name($compositetype);
            $a = new $classname(0, array(
                'owner' => $owner,
                'title' => get_string($compositetype, 'artefact.resume'),
                )
            );
        }

        $a->commit();

        $values['artefact'] = $a->get('id');

        $table = 'artefact_resume_' . $compositetype;
        if (!empty($values['id'])) {
            $itemid = $values['id'];
            update_record($table, (object)$values, 'id');
        }
        else {
            if (isset($values['displayorder'])) {
                $values['displayorder'] = intval($values['displayorder']);
            }
            else {
                $max = get_field($table, 'MAX(displayorder)', 'artefact', $values['artefact']);
                $values['displayorder'] = is_numeric($max) ? $max + 1 : 0;
            }
            $itemid = insert_record($table, (object)$values, 'id', true);
        }

        // If there are any attachments, attach them to your Resume...
        if ($compositetype == 'educationhistory') {
            $goto = get_config('wwwroot') . 'artefact/resume/employment.php';
        }
        else if ($compositetype == 'employmenthistory') {
            $goto = get_config('wwwroot') . 'artefact/resume/education.php';
        }
        else {
            $goto = get_config('wwwroot') . 'artefact/resume/achievements.php';
        }

        // Attachments via 'files' pieform element
        // This happens when adding new resume composite...
        if (array_key_exists('attachments', $values)) {
            require_once(get_config('libroot') . 'uploadmanager.php');
            safe_require('artefact', 'file');

            $folderid = null;
            $attachment = (object) array(
                'owner'         => $owner,
                'group'         => null, // Group
                'institution'   => null, // Institution
                'author'        => $owner,
                'allowcomments' => 0,
                'parent'        => $folderid,
                'description'   => null,
            );

            foreach ($values['attachments'] as $filesindex) {
                $originalname = $_FILES[$filesindex]['name'];
                $attachment->title = ArtefactTypeFileBase::get_new_file_title(
                    $originalname,
                    $folderid,
                    $owner,
                    null, // Group
                    null  // Institution
                );

                try {
                    $fileid = ArtefactTypeFile::save_uploaded_file($filesindex, $attachment);
                }
                catch (QuotaExceededException $e) {
                    return array('error'=>$e->getMessage(), 'goto'=>$goto);
                }
                catch (UploadException $e) {
                    return array('error'=>$e->getMessage(), 'goto'=>$goto);
                }

                $a->attach($fileid, $itemid);
            }
        }

        // Attachments via 'filebrowser' pieform element
        // This happens when editing resume composite...
        if (array_key_exists('filebrowser', $values)) {
            $old = $a->attachment_id_list_with_item($itemid);
            $new = is_array($values['filebrowser']) ? $values['filebrowser'] : array();
            // only allow the attaching of files that exist and are editable by user
            foreach ($new as $key => $fileid) {
                $file = artefact_instance_from_id($fileid);
                if (!($file instanceof ArtefactTypeFile) || !$USER->can_publish_artefact($file)) {
                    unset($new[$key]);
                }
            }
            if (!empty($new) || !empty($old)) {
                foreach ($old as $o) {
                    if (!in_array($o, $new)) {
                        try {
                            $a->detach($o, $itemid);
                        }
                        catch (ArtefactNotFoundException $e) {}
                    }
                }
                $is_error = false;
                foreach ($new as $n) {
                    if (!in_array($n, $old)) {
                        // check the new item is not already attached to the
                        // artefact under a different $itemid
                        if (record_exists('artefact_attachment', 'artefact', $a->get('id'), 'attachment', $n)) {
                            $artefactfile = artefact_instance_from_id($n);
                            $is_error[] = $artefactfile->get('title');
                        }
                        else {
                            try {
                                $a->attach($n, $itemid);
                            }
                            catch (ArtefactNotFoundException $e) {}
                        }
                    }
                }
                if (!empty($is_error)) {
                    if (sizeof($is_error) > 1) {
                        $error = get_string('duplicateattachments', 'artefact.resume', implode('\', \'', $is_error));
                    }
                    else {
                        $error = get_string('duplicateattachment', 'artefact.resume', implode(', ', $is_error));
                    }
                    return array('error'=>$error);
                }
            }
        }
        return array('artefactid' => $a->id, 'itemid' => $itemid);
    }

    public function delete() {
        $table = $this->get_other_table_name();
        db_begin();

        delete_records($table, 'artefact', $this->id);
        parent::delete();

        db_commit();
    }

    public static function bulk_delete_composite($artefactids, $compositetype) {
        $table = 'artefact_resume_' . $compositetype;
        if (empty($artefactids)) {
            return;
        }

        $idstr = join(',', array_map('intval', $artefactids));

        db_begin();
        delete_records_select($table, 'artefact IN (' . $idstr . ')');
        parent::bulk_delete($artefactids);
        db_commit();
    }

    /**
    * Takes a pieform that's been set up by all the
    * subclass get_addform_elements functions
    * and puts the default values in (and hidden id field)
    * ready to be an edit form
    *
    * @param $form pieform structure (before calling pieform() on it
    * passed by _reference_
    */
    public static function populate_form(&$form, $id, $type) {
        if (!$composite = get_record('artefact_resume_' . $type, 'id', $id)) {
            throw new InvalidArgumentException("Couldn't find composite of type $type with id $id");
        }
        $datetypes = array('date', 'startdate', 'enddate');
        foreach ($form['elements'] as $k => $element) {
            if ($k == 'submit' || $k == 'submitform' ||$k == 'compositetype') {
                continue;
            }
            if (isset($composite->{$k})) {
                $form['elements'][$k]['defaultvalue'] = $composite->{$k};
            }
        }
        $form['elements']['id'] = array(
            'type' => 'hidden',
            'value' => $id,
        );
        $form['elements']['artefact'] = array(
            'type' => 'hidden',
            'value' => $composite->artefact,
        );
    }


    /**
    * call the parent constructor
    * and then load up the stuff from the supporting table
    */
    public function __construct($id=0, $data=array()) {
        if (empty($id)) {
            $data['container'] = 0;
            $data['title'] = get_string($this->get_artefact_type(), 'artefact.resume');
        }
        parent::__construct($id, $data);
    }

    /**
    * returns the name of the supporting table
    */
    public function get_other_table_name() {
        return 'artefact_resume_' . $this->get_artefact_type();
    }

    public function render_self($options) {
        global $USER;
        $suffix = '_' . substr(md5(microtime()), 0, 4);
        $attachmessage = get_string('fileattachmessage', 'artefact.resume',
                         get_string('fileattachdirname', 'artefact.resume'));
        $smarty = smarty_core();
        $smarty->assign('user', $USER->get('id'));
        $smarty->assign('hidetitle', true);
        $smarty->assign('suffix', $suffix);
        $smarty->assign('attachmessage', $attachmessage);
        $type = $this->get('artefacttype');
        $othertable = 'artefact_resume_' . $type;
        $owner = $USER->get('id');

        $sql = 'SELECT ar.*, a.owner
            FROM {artefact} a
            JOIN {' . $othertable . '} ar ON ar.artefact = a.id
            WHERE a.owner = ? AND a.artefacttype = ?
            ORDER BY ar.displayorder';

        if (!empty($options['viewid'])) {
            require_once('view.php');
            $smarty->assign('viewid', $options['viewid']);
            $v = new View($options['viewid']);
            $owner = $v->get('owner');
        }

        if (!empty($options['artefactid'])) {
            $smarty->assign('artefactid', $options['artefactid']);
        }

        if (!empty($options['editing'])) {
            $smarty->assign('editing', $options['editing']);
        }

        if (!empty($options['blockid'])) {
            $smarty->assign('blockid', $options['blockid']);
        }

        if (!$data = get_records_sql_array($sql, array($owner, $type))) {
            $data = array();
        }

        // Give the artefact type a chance to format the data how it sees fit
        $data = call_static_method(generate_artefact_class_name($type), 'format_render_self_data', $data);

        // Add artefact attachments it there are any
        $datawithattachments = array();
        foreach ($data as $record) {
            // Cannot use $this->get_attachments() as it would return
            // all the attachments for specified resume composite.
            // Instead we want only attachments for single item of the
            // specified resume composite...
            $sql = 'SELECT a.title, a.id, af.size
                    FROM {artefact} a
                    JOIN {artefact_file_files} af ON af.artefact = a.id
                    JOIN {artefact_attachment} at ON at.attachment = a.id
                    WHERE at.artefact = ? AND at.item = ?
                    ORDER BY a.title';
            $attachments = get_records_sql_array($sql, array($record->artefact, $record->id));
            if ($attachments) {
                safe_require('artefact', 'comment');
                foreach ($attachments as &$attachment) {
                    $f = artefact_instance_from_id($attachment->id);
                    $attachment->size = $f->describe_size();
                    $attachment->iconpath = $f->get_icon(array('id' => $attachment->id, 'viewid' => isset($options['viewid']) ? $options['viewid'] : 0));
                    $attachment->artefacttype = $f->get_artefact_type($attachment->id);
                    $attachment->downloadpath = get_config('wwwroot') . 'artefact/file/download.php?file=' . $attachment->id;
                    $attachment->description = $f->description;
                    $attachment->allowcomments = $f->get('allowcomments');
                    if (!empty($options['showcommentcount'])) {
                        $count = ArtefactTypeComment::count_comments(null, array($attachment->id));
                        if ($count) {
                            $attachment->commentcount = $count[$attachment->id]->comments;
                        }
                        else {
                            $attachment->commentcount = 0;
                        }
                    }
                }
            }
            $record->attachments = $attachments;
            if (!is_array($attachments)) {
                $record->clipcount = 0;
            }
            else {
                $record->clipcount = count($attachments);
            }
            // Clean up description before displaying it
            if (isset($record->qualdescription)) {
                $record->qualdescription = clean_html($record->qualdescription);
            }
            else if (isset($record->positiondescription)) {
                $record->positiondescription = clean_html($record->positiondescription);
            }
            else {
                $record->description = clean_html($record->description);
            }

            $datawithattachments[] = $record;
        }

        $smarty->assign('rows', $datawithattachments);
        $this->render_license($options, $smarty);

        $content = array(
            'html'         => $smarty->fetch('artefact:resume:fragments/' . $type . '.tpl'),
            'javascript'   => $this->get_showhide_composite_js()
        );
        return $content;
    }

    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        $smarty = smarty_core();
        $fields = array();
        foreach ($renderfields as $field) {
            $fields[get_string($field, 'artefact.resume')] = isset($entry_content[$field]) ? $entry_content[$field] : '';
        }
        $smarty->assign('fields', $fields);
        return $smarty->fetch('artefact:resume:import/resumecompositefields.tpl');
    }

    public static function get_js(array $compositetypes) {
        $js = self::get_common_js();
        foreach ($compositetypes as $compositetype) {
            $js .= call_static_method(
                generate_artefact_class_name($compositetype),
                'get_artefacttype_js',
                $compositetype
            );
        }
        return $js;
    }

    public static function get_common_js() {
        $cancelstr = json_encode(get_string('cancel'));
        $addstr = json_encode(get_string('add'));
        $confirmdelstr = get_string('compositedeleteconfirm', 'artefact.resume');
        $js = <<<EOF
var tableRenderers = {};

function compositeSaveCallback(form, data) {
    // TODO: currently no data.formelement coming through here, redo/fix resume submit functions to remove subm

    if (typeof(data.formelement)!= 'undefined' && data.formelement.endsWith('_filebrowser')) {
        if (data.formelement.startsWith('addbook')) {
            addbook_filebrowser.callback(form, data);
        }
        else if (data.formelement.startsWith('addcertification')) {
            addcertification_filebrowser.callback(form, data);
        }
        else if (data.formelement.startsWith('addmembership')) {
            addmembership_filebrowser.callback(form, data);
        }
        else if (data.formelement.startsWith('addeducationhistory')) {
            addeducationhistory_filebrowser.callback(form, data);
        }
        else if (data.formelement.startsWith('addemploymenthistory')) {
            addemploymenthistory_filebrowser.callback(form, data);
        }
    }
    else {
        key = form.id.substr(3);

        // Can't reset() the form here, because its values are what were just submitted,
        // thanks to pieforms
        \$j('#' + form.id + ' input:text, #' + form.id + ' textarea').each(function() {
            \$j(this).attr('value', '');
        });
        // Also need to clear the innerHTML for textareas
        \$j('#' + form.id + ' textarea').each(function() {
            tinyMCE.get(\$j(this).attr('id')).setContent('');
        });

        \$j('#' + key + 'form').collapse('hide');

        tableRenderers[key].doupdate(null, { focusid: data['focusid'] });
        \$j('#add' + key + 'button').trigger("focus");
        // Do a double check to make sure the formchange checker for the submitted form is actually reset
        tableRenderers[key].postupdatecallback = function(response) {
            var checkers = formchangemanager.formcheckers;
            for (var i=0; i < checkers.length; i ++) {
                if (checkers[i].id == form.id) {
                    checkers[i].state = FORM_INIT;
                }
            }
            // turn on the 'drop file here' area for browsers that can handle it.
            \$j('.dropzone-previews').hide();
            if ('draggable' in document.createElement('span')) {
                \$j('.dropzone-previews').css('min-height', '50px');
                \$j('.dropzone-previews').show();
            }
        }
        formSuccess(form, data);
    }
}

function deleteComposite(type, id, artefact) {
    if (confirm('{$confirmdelstr}')) {
        sendjsonrequest('compositedelete.json.php',
            {'id': id, 'artefact': artefact},
            'GET',
            function(data) {
                tableRenderers[type].doupdate();
            },
            function() {
                // @todo error
            }
        );
    }
    return false;
}

function moveComposite(type, id, artefact, direction) {
    sendjsonrequest('compositemove.json.php',
        {'id': id, 'artefact': artefact, 'direction':direction},
        'GET',
        function(data) {
            tableRenderers[type].doupdate(null, { focusid: id });
        },
        function() {
            // @todo error
        }
    );
    return false;
}
EOF;
        $js .= self::get_showhide_composite_js();
        return $js;
    }

    static function get_tablerenderer_title_js($titlestring, $extrastring, $bodystring, $attachstring, $addressstring='false') {
        return <<<EOF
                function (row, data) {
                    if (!{$bodystring} && !{$attachstring} && !{$addressstring}) {
                      return jQuery('<td>').append(
                        jQuery('<span>').append({$titlestring}),
                        jQuery('<div>', {'class': 'detail text-midtone'}).append({$extrastring})
                      )[0];
                    }
                    else {
                        var link = jQuery('<a>', {'class': 'toggle textonly', 'href': ''}).append({$titlestring})[0];
                        jQuery(link).on('click', function (e) {
                            e.preventDefault();
                            return showhideComposite(row, {$bodystring}, {$attachstring}, {$addressstring});
                        });
                        var extra = jQuery('<div>', {'class': 'detail text-midtone'}).append({$extrastring});
                        return jQuery('<div>', {'id': 'composite-' + row.artefact + '-' + row.id}).append(
                            jQuery('<div>', {'class': 'expandable-head'}).append(link, extra)
                            )[0];
                    }
                },
EOF;
    }

    static function get_showhide_composite_js() {
        return <<<EOF
            function showhideComposite(row, content, attachments, address) {
                // get the reference for the title we just clicked on
                var titleTD = jQuery('#composite-' + row.artefact + '-' + row.id);
                var bodyNode = jQuery('#composite-body-' + row.artefact +  '-' + row.id);
                if (bodyNode.length) {
                    bodyNode.toggleClass('d-none');
                    return false;
                }

                var newNode = jQuery('<div>', {'id': 'composite-body-' + row.artefact + '-' + row.id}).append(
                    jQuery('<div>', {'class':'content-text'}).append(content).append(address).append(attachments)
                );
                newNode.insertAfter(titleTD.find('.expandable-head').first());
            }
EOF;
    }

    static function get_artefacttype_js($compositetype) {
        global $THEME;
        $titlestring = call_static_method(generate_artefact_class_name($compositetype), 'get_tablerenderer_title_js_string');
        $editstr = json_encode(get_string('edit'));
        $delstr = json_encode(get_string('delete'));
        $editjsstr = json_encode(get_string('editspecific', 'mahara', '%s')) . ".replace('%s', {$titlestring})";
        $deljsstr = json_encode(get_string('deletespecific', 'mahara', '%s')) . ".replace('%s', {$titlestring})";

        $upstr = get_string('moveup', 'artefact.resume');
        $upjsstr = json_encode(get_string('moveupspecific', 'artefact.resume', '%s')) . ".replace('%s', {$titlestring})";
        $downstr = get_string('movedown', 'artefact.resume');
        $downjsstr = json_encode(get_string('movedownspecific', 'artefact.resume', '%s')) . ".replace('%s', {$titlestring})";

        $js = call_static_method(generate_artefact_class_name($compositetype), 'get_composite_js');

        $js .= <<<EOF
tableRenderers.{$compositetype} = new TableRenderer(
    '{$compositetype}list',
    'composite.json.php',
    [
EOF;

        $js .= <<<EOF

        function (row, data) {
            var buttons = [];
            if (row._rownumber > 1) {
                var up =
                    jQuery('<a>', {'href': '', 'class': 'moveup'}).append(
                        jQuery('<span>',{'class': 'icon icon-long-arrow-alt-up','role':'presentation'}),
                        jQuery('<span>',{'class': 'visually-hidden', 'text': {$upjsstr}})
                    );
                    up.on('click', function (e) {
                    e.preventDefault();
                    return moveComposite(data.type, row.id, row.artefact, 'up');
                });
                buttons.push(up);
            }
            if (!row._last) {
                var down =
                    jQuery('<a>', {'href': '', 'class':'movedown'}).append(
                      jQuery('<span>',{'class': 'icon icon-long-arrow-alt-down','role':'presentation'}),
                      jQuery('<span>',{'class': 'visually-hidden', 'text': {$downjsstr}})
                    );
                    down.on('click', function (e) {
                    e.preventDefault();
                    return moveComposite(data.type, row.id, row.artefact, 'down');
                });
                buttons.push(' ');
                buttons.push(down);
            }
            return jQuery('<td>',{'class':'movebuttons'}).append(buttons)[0];
        },
EOF;

        $js .= call_static_method(generate_artefact_class_name($compositetype), 'get_tablerenderer_js');

        $js .= <<<EOF
        function (row, data) {
            var editlink =
                jQuery('<button>', {'data-url': 'editcomposite.php?id=' + row.id + '&artefact=' + row.artefact,
                               'title': {$editstr}, 'class': 'btn btn-secondary btn-sm'}).append(
                                    jQuery('<span>',{'class': 'icon icon-pencil-alt', 'role':'presentation'}),
                                    jQuery('<span>',{'class': 'visually-hidden'}).append({$editjsstr})
                               );
            var dellink =
                jQuery('<button>', {'data-ignore':'true', 'data-url': '', 'title': {$delstr}, 'class': 'btn btn-secondary btn-sm'}).append(
                    jQuery('<span>',{'class': 'icon icon-trash-alt text-danger','role':'presentation'}),
                    jQuery('<span>',{'class': 'visually-hidden'}).append({$deljsstr})
                );
                dellink.on('click', function (e) {
                e.preventDefault();
                return deleteComposite(data.type, row.id, row.artefact);
            });
            return jQuery('<td>', {'class':'control-buttons'}).append(
                jQuery('<div>', {'class':'btn-group'}).append( null, editlink, ' ', dellink))[0];
        }
    ],
    {
        focusElement: 'button'
    },
);

tableRenderers.{$compositetype}.type = '{$compositetype}';
tableRenderers.{$compositetype}.statevars.push('type');
tableRenderers.{$compositetype}.emptycontent = '';
tableRenderers.{$compositetype}.updateOnLoad();

EOF;
        return $js;
    }

    static function get_composite_js() {
        $attachmentsstr = json_encode(get_string('Attachments', 'artefact.resume'));
        $downloadstr = json_encode(get_string('Download', 'artefact.file'));
        $at = get_string('at');
        return <<<EOF
function formatSize(size) {
    size = parseInt(size, 10);
    if (size < 1024) {
        return size <= 0 ? '0' : size.toFixed(1).replace(/\.0$/, '') + 'b';
    }
    if (size < 1048576) {
        return (size / 1024).toFixed(1).replace(/\.0$/, '') + 'K';
    }
    return (size / 1048576).toFixed(1).replace(/\.0$/, '') + 'M';
}
function listAttachments(attachments) {
    if (typeof attachments !== 'undefined' && attachments.length > 0) {
        var togglelink = jQuery('<span>').append({$attachmentsstr});
        var thead = jQuery('<thead>').append(jQuery('<tr>').append(jQuery('<th>').append(togglelink)));
        var tbody = jQuery('<tbody>');
        for (var i=0; i < attachments.length; i++) {
            var item = attachments[i];
            var href = self.config.wwwroot + 'artefact/file/download.php?file=' + item.attachment;
            var linkcontent = '<span class="visually-hidden">' + {$downloadstr} + ' "' + item.title + '"</span>';
            linkcontent += '<span class="icon icon-download icon-lg float-end text-watermark icon-action" role="presentation" aria-hidden="true"></span>';
            var link = jQuery('<a href="' + href + '">' + linkcontent + '</a>');
            var icon = '<span class="icon icon-file left icon-lg text-default file-icon" role="presentation" aria-hidden="true"></span>';
            if (item.icon) {
                icon = '<img class="file-icon" src="' + item.icon + '" alt="' + item.title + '">';
            }
            tbody.append(
              jQuery('<tr>').append(
                jQuery('<td>').append(
                  jQuery(icon),
                  jQuery('<span class="title">').append(item.title),
                  jQuery('<span class="download-link">').append(link)
                )
              )
            );
        }
        return jQuery('<table>', {'class': 'table attachment-table'}).append(thead, tbody)[0];
    }
    else {
        // No attachments
        return '';
    }
}
function formatQualification(name, type, institution) {
    var qual = '';
    if (name && type) {
        qual = name + ' (' + type + ') {$at} ';
    }
    else if (type) {
        qual = type + ' {$at} ';
    }
    else if (name) {
        qual = name + ' {$at} ';
    }
    qual += institution;
    return qual;
}
EOF;
    }

    static function get_forms(array $compositetypes) {
        $compositeforms = array();
        foreach ($compositetypes as $compositetype) {
            $elements = call_static_method(generate_artefact_class_name($compositetype), 'get_addform_elements');
            $elements['submit'] = array(
                'type' => 'submit',
                'name' => 'submitbtn',
                'class' => 'btn-primary',
                'value' => get_string('save'),
            );
            $elements['compositetype'] = array(
                'type' => 'hidden',
                'value' => $compositetype,
            );
            $cform = array(
                'name' => 'add' . $compositetype,
                'plugintype' => 'artefact',
                'pluginname' => 'resume',
                'elements' => $elements,
                'jsform' => true,
                'successcallback' => 'compositeform_submit',
                'jssuccesscallback' => 'compositeSaveCallback',
            );
            $compositeforms[$compositetype] = pieform($cform);
        }
        return $compositeforms;
    }

    public function get_license_artefact() {
        $pi = get_record('artefact',
                         'artefacttype', $this->artefacttype,
                         'owner', $this->owner);
        if (!$pi)
            return null;

        require_once(get_config('docroot') . 'artefact/lib.php');
        return artefact_instance_from_id($pi->id);
    }
}

class ArtefactTypeEmploymenthistory extends ArtefactTypeResumeComposite {

    protected $startdate;
    protected $enddate;
    protected $employer;

    public static function get_tablerenderer_js() {
        return ArtefactTypeResumeComposite::get_tablerenderer_title_js(
                    self::get_tablerenderer_title_js_string(),
                    self::get_tablerenderer_date_js_string(),
                    self::get_tablerenderer_body_js_string(),
                    self::get_tablerenderer_attachments_js_string(),
                    self::get_tablerenderer_address_js_string()
                ) . ",
                function (row, data) {
                    return jQuery('<span>', {'style':'text-align:center'}).append(row.clipcount);
                },
        ";
    }

    public static function is_wysiwyg() {
        return true;
    }

    public static function get_tablerenderer_title_js_string() {
        return " row.jobtitle + ': ' + row.employer";
    }

    public static function get_tablerenderer_date_js_string() {
        return " row.startdate + (row.enddate ? ' - ' + row.enddate : '')";
    }

    public static function get_tablerenderer_body_js_string() {
        return " row.positiondescription";
    }

    public static function get_tablerenderer_attachments_js_string() {
        return " listAttachments(row.attachments)";
    }

    public static function get_tablerenderer_address_js_string() {
      $address = get_string('address', 'blocktype.resume/entireresume');
      return <<<EOF
          (row.employeraddress) ? jQuery('<p>', {'text' : "{$address}: " + row.employeraddress}) : ""
EOF;
    }

    public static function get_addform_elements() {
        global $USER;

        $folder = param_integer('folder', 0);
        $browse = (int) param_variable('browse', 0);
        $highlight = null;
        if ($file = param_integer('file', 0)) {
            $highlight = array($file);
        }

        return array(
            'startdate' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('startdate', 'artefact.resume'),
                'size' => 20,
                'help' => true,
                'helpformname' => 'addemploymenthistory',
            ),
            'enddate' => array(
                'type' => 'text',
                'title' => get_string('enddate', 'artefact.resume'),
                'size' => 20,
            ),
            'employer' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('employer', 'artefact.resume'),
                'size' => 50,
            ),
            'employeraddress' => array(
                'type' => 'text',
                'title' => get_string('employeraddress', 'artefact.resume'),
                'size' => 50,
            ),
            'jobtitle' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('jobtitle', 'artefact.resume'),
                'size' => 50,
            ),
            'positiondescription' => array(
                'type' => 'wysiwyg',
                'rows' => 10,
                'cols' => 50,
                'rules' => array('maxlength' => 1000000),
                'title' =>  get_string('positiondescription', 'artefact.resume'),
            ),
            'filebrowser' => array(
                'type'         => 'filebrowser',
                'title'        => get_string('attachfile', 'artefact.resume'),
                'defaultvalue' => array(),
                'maxfilesize'  => get_max_upload_size(true),
                'folder'       => $folder,
                'highlight'    => $highlight,
                'browse'       => $browse,
                'page'         => get_config('wwwroot') . 'artefact/resume/employment.php',
                'browsehelp'   => 'browsemyfiles',
                'config'       => array(
                    'upload'          => true,
                    'uploadagreement' => get_config_plugin('artefact', 'file', 'uploadagreement'),
                    'resizeonuploaduseroption' => get_config_plugin('artefact', 'file', 'resizeonuploaduseroption'),
                    'resizeonuploaduserdefault' => $USER->get_account_preference('resizeonuploaduserdefault'),
                    'createfolder'    => false,
                    'edit'            => false,
                    'select'          => true,
                ),
                'selectlistcallback' => 'artefact_get_records_by_id',
                'selectcallback'     => 'add_resume_attachment',
                'unselectcallback'   => 'delete_resume_attachment',
            ),
        );
    }

    public static function bulk_delete($artefactids, $log=false) {
        ArtefactTypeResumeComposite::bulk_delete_composite($artefactids, 'employmenthistory');
    }

    /**
     * returns the employmenthistory artefacts which have the same values of the following fields:
     *  - owner
     *  - type
     *  - content which has
     *      - startdate
     *      - enddate
     *      - employer
     *      - jobtitle
     *      - positiondescription
     *
     * @param array $values
     */
    public static function get_duplicated_artefacts(array $values) {
        $fields = array('startdate', 'enddate', 'employer', 'jobtitle', 'positiondescription');
        $where = array();
        $wherevalues = array($values['owner'], $values['type']);
        $contentvalues = $values['content'];
        foreach ($fields as $f) {
            if (!isset($contentvalues[$f])) {
                return array();
            }
            if (!empty($contentvalues[$f])) {
                $where[] = "ar.$f = ?";
                $wherevalues[] = $contentvalues[$f];
            }
        }
        $wherestr = 'WHERE a.owner = ? AND a.artefacttype = ?' . (!empty($where) ? ' AND ' . join(' AND ', $where) : '');
        return get_column_sql('
            SELECT DISTINCT a.id
            FROM {artefact} AS a
            INNER JOIN {artefact_resume_employmenthistory} AS ar
            ON a.id = ar.artefact
            ' . $wherestr, $wherevalues
        );
    }

    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        return parent::render_import_entry_request($entry_content, array_keys(self::get_addform_elements()));
    }

}

class ArtefactTypeEducationhistory extends ArtefactTypeResumeComposite {

    protected $startdate;
    protected $enddate;
    protected $qualtype;
    protected $institution;

    public static function get_tablerenderer_js() {

        return ArtefactTypeResumeComposite::get_tablerenderer_title_js(
                    self::get_tablerenderer_title_js_string(),
                    self::get_tablerenderer_date_js_string(),
                    self::get_tablerenderer_body_js_string(),
                    self::get_tablerenderer_attachments_js_string(),
                    self::get_tablerenderer_address_js_string()
                ) . ",
                function (row, data) {
                    return jQuery('<span>', {'style':'text-align:center'}).append(row.clipcount);
                },
        ";
    }

    public static function is_wysiwyg() {
        return true;
    }

    public static function get_tablerenderer_title_js_string() {
        return " formatQualification(row.qualname, row.qualtype, row.institution)";
    }

    public static function get_tablerenderer_date_js_string() {
        return " row.startdate + (row.enddate ? ' - ' + row.enddate : '')";
    }

    public static function format_render_self_data($data) {
        $at = get_string('at');
        foreach ($data as &$row) {
            $row->qualification = '';
            if (strlen($row->qualname) && strlen($row->qualtype)) {
                $row->qualification = $row->qualname. ' (' . $row->qualtype . ') ' . $at . ' ';
            }
            else if (strlen($row->qualtype)) {
                $row->qualification = $row->qualtype . ' ' . $at . ' ';
            }
            else if (strlen($row->qualname)) {
                $row->qualification = $row->qualname . ' ' . $at . ' ';
            }
            $row->qualification .= $row->institution;
        }
        return $data;
    }

    public static function get_tablerenderer_body_js_string() {
        return " row.qualdescription";
    }

    public static function get_tablerenderer_attachments_js_string() {
        return " listAttachments(row.attachments)";
    }

    public static function get_tablerenderer_address_js_string() {
      $address = get_string('address', 'blocktype.resume/entireresume');
      return <<<EOF
          (row.institutionaddress) ? jQuery('<p>', {'text' : "{$address}: " + row.institutionaddress}) : ""
EOF;
    }

    public static function get_addform_elements() {
        global $USER;

        $folder = param_integer('folder', 0);
        $browse = (int) param_variable('browse', 0);
        $highlight = null;
        if ($file = param_integer('file', 0)) {
            $highlight = array($file);
        }

        return array(
            'startdate' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('startdate', 'artefact.resume'),
                'size' => 20,
                'help' => true,
            ),
            'enddate' => array(
                'type' => 'text',
                'title' => get_string('enddate', 'artefact.resume'),
                'size' => 20,
            ),
            'institution' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('institution', 'artefact.resume'),
                'size' => 50,
            ),
            'institutionaddress' => array(
                'type' => 'text',
                'title' => get_string('institutionaddress', 'artefact.resume'),
                'size' => 50,
            ),
            'qualtype' => array(
                'type' => 'text',
                'title' => get_string('qualtype', 'artefact.resume'),
                'size' => 50,
            ),
            'qualname' => array(
                'type' => 'text',
                'title' => get_string('qualname', 'artefact.resume'),
                'size' => 50,
            ),
            'qualdescription' => array(
                'type' => 'wysiwyg',
                'rows' => 10,
                'cols' => 50,
                'rules' => array('maxlength' => 1000000),
                'title' => get_string('qualdescription', 'artefact.resume'),
            ),
            'filebrowser' => array(
                'type'         => 'filebrowser',
                'title'        => get_string('attachfile', 'artefact.resume'),
                'defaultvalue' => array(),
                'maxfilesize'  => get_max_upload_size(true),
                'folder'       => $folder,
                'highlight'    => $highlight,
                'browse'       => $browse,
                'page'         => get_config('wwwroot') . 'artefact/resume/education.php',
                'browsehelp'   => 'browsemyfiles',
                'config'       => array(
                    'upload'          => true,
                    'uploadagreement' => get_config_plugin('artefact', 'file', 'uploadagreement'),
                    'resizeonuploaduseroption' => get_config_plugin('artefact', 'file', 'resizeonuploaduseroption'),
                    'resizeonuploaduserdefault' => $USER->get_account_preference('resizeonuploaduserdefault'),
                    'createfolder'    => false,
                    'edit'            => false,
                    'select'          => true,
                ),
                'selectlistcallback' => 'artefact_get_records_by_id',
                'selectcallback'     => 'add_resume_attachment',
                'unselectcallback'   => 'delete_resume_attachment',
            ),
        );
    }

    public static function bulk_delete($artefactids, $log=false) {
        ArtefactTypeResumeComposite::bulk_delete_composite($artefactids, 'educationhistory');
    }

    /**
     * returns the artefacts which have the same values of the following fields:
     *  - owner
     *  - type == 'educationhistory'
     *  - content, which has
     *      - startdate
     *      - enddate
     *      - institution
     *      - qualtype
     *      - qualname
     *
     * @param array $values
     */
    public static function get_duplicated_artefacts(array $values) {
        $fields = array('startdate', 'enddate', 'institution', 'qualtype', 'qualname');
        $where = array();
        $wherevalues = array($values['owner'], $values['type']);
        $contentvalues = $values['content'];
        foreach ($fields as $f) {
            if (!isset($contentvalues[$f])) {
                return array();
            }
            if (!empty($contentvalues[$f])) {
                $where[] = "ar.$f = ?";
                $wherevalues[] = $contentvalues[$f];
            }
        }
        $wherestr = 'WHERE a.owner = ? AND a.artefacttype = ?' . (!empty($where) ? ' AND ' . join(' AND ', $where) : '');
        return get_column_sql('
            SELECT DISTINCT a.id
            FROM {artefact} AS a
                INNER JOIN {artefact_resume_educationhistory} AS ar
                    ON a.id = ar.artefact
            ' . $wherestr, $wherevalues
        );
    }

    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        return parent::render_import_entry_request($entry_content, array_keys(self::get_addform_elements()));
    }

}

class ArtefactTypeCertification extends ArtefactTypeResumeComposite {

    protected $date;

    public static function get_tablerenderer_js() {
        return ArtefactTypeResumeComposite::get_tablerenderer_title_js(
                    self::get_tablerenderer_title_js_string(),
                    self::get_tablerenderer_date_js_string(),
                    self::get_tablerenderer_body_js_string(),
                    self::get_tablerenderer_attachments_js_string()
                ) . ",
                function (row, data) {
                    return jQuery('<span>', {'style':'text-align:center'}).append(row.clipcount);
                },
        ";
    }

    public static function is_wysiwyg() {
        return true;
    }

    public static function get_tablerenderer_title_js_string() {
        return "row.title";
    }

    public static function get_tablerenderer_date_js_string() {
        return " row.date";
    }

    public static function get_tablerenderer_body_js_string() {
        return "row.description";
    }

    public static function get_tablerenderer_attachments_js_string() {
        return " listAttachments(row.attachments)";
    }

    public static function get_addform_elements() {
        global $USER;

        $folder = param_integer('folder', 0);
        $browse = (int) param_variable('browse', 0);
        $highlight = null;
        if ($file = param_integer('file', 0)) {
            $highlight = array($file);
        }

        return array(
            'date' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('date', 'artefact.resume'),
                'size' => 20,
                'help' => true,
                'helpformname' => 'addcertification',
            ),
            'title' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('title', 'artefact.resume'),
                'size' => 20,
            ),
            'description' => array(
                'type' => 'wysiwyg',
                'rows' => 10,
                'cols' => 50,
                'rules' => array('maxlength' => 1000000),
                'title' => get_string('description'),
            ),
            'filebrowser' => array(
                'type'         => 'filebrowser',
                'title'        => get_string('attachfile', 'artefact.resume'),
                'defaultvalue' => array(),
                'maxfilesize'  => get_max_upload_size(true),
                'folder'       => $folder,
                'highlight'    => $highlight,
                'browse'       => $browse,
                'page'         => get_config('wwwroot') . 'artefact/resume/achievements.php',
                'browsehelp'   => 'browsemyfiles',
                'config'       => array(
                    'upload'          => true,
                    'uploadagreement' => get_config_plugin('artefact', 'file', 'uploadagreement'),
                    'resizeonuploaduseroption' => get_config_plugin('artefact', 'file', 'resizeonuploaduseroption'),
                    'resizeonuploaduserdefault' => $USER->get_account_preference('resizeonuploaduserdefault'),
                    'createfolder'    => false,
                    'edit'            => false,
                    'select'          => true,
                ),
                'selectlistcallback' => 'artefact_get_records_by_id',
                'selectcallback'     => 'add_resume_attachment',
                'unselectcallback'   => 'delete_resume_attachment',
            ),
        );
    }

    public static function bulk_delete($artefactids, $log=false) {
        ArtefactTypeResumeComposite::bulk_delete_composite($artefactids, 'certification');
    }

    /**
     * returns certificate artefacts which have the same values of the following fields:
     *  - owner
     *  - type
     *  - content which has:
     *      - date
     *      - title
     *      - description
     *
     * @param array $values
     */
    public static function get_duplicated_artefacts(array $values) {
        $fields = array('date', 'title', 'description');
        $where = array();
        $wherevalues = array($values['owner'], $values['type']);
        $contentvalues = $values['content'];
        foreach ($fields as $f) {
            if (!isset($contentvalues[$f])) {
                return array();
            }
            if (!empty($contentvalues[$f])) {
                $where[] = "ar.$f = ?";
                $wherevalues[] = $contentvalues[$f];
            }
        }
        $wherestr = 'WHERE a.owner = ? AND a.artefacttype = ?' . (!empty($where) ? ' AND ' . join(' AND ', $where) : '');
        return get_column_sql('
            SELECT DISTINCT a.id
            FROM {artefact} AS a
            INNER JOIN {artefact_resume_certification} AS ar
            ON a.id = ar.artefact
            ' . $wherestr, $wherevalues
        );
    }

    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        return parent::render_import_entry_request($entry_content, array_keys(self::get_addform_elements()));
    }

}

class ArtefactTypeBook extends ArtefactTypeResumeComposite {

    protected $date;
    protected $contribution;

    public static function get_tablerenderer_js() {
        return ArtefactTypeResumeComposite::get_tablerenderer_title_js(
                    self::get_tablerenderer_title_js_string(),
                    self::get_tablerenderer_date_js_string(),
                    self::get_tablerenderer_body_js_string(),
                    self::get_tablerenderer_attachments_js_string(),
                    self::get_tablerenderer_urladdress_js_string()
                ) . ",
                function (row, data) {
                    return jQuery('<span>', {'style':'text-align:center'}).append(row.clipcount);
                },
        ";
    }

    public static function is_wysiwyg() {
        return true;
    }

    public static function get_tablerenderer_title_js_string() {
        return "row.title + ' (' + row.contribution + ')'";
    }

    public static function get_tablerenderer_date_js_string() {
        return " row.date";
    }

    public static function get_tablerenderer_body_js_string() {
      return " row.description";
    }

    public static function get_tablerenderer_attachments_js_string() {
        return " listAttachments(row.attachments)";
    }

    public static function get_tablerenderer_urladdress_js_string() {
      return <<<EOF
              jQuery('<div>', {'id':'composite-book-url'}).append(
                  jQuery('<a>', {'href':row.url, 'text' : row.url})
              )
EOF;
    }

    public static function get_addform_elements() {
        global $USER;

        $folder = param_integer('folder', 0);
        $browse = (int) param_variable('browse', 0);
        $highlight = null;
        if ($file = param_integer('file', 0)) {
            $highlight = array($file);
        }

        return array(
            'date' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('date', 'artefact.resume'),
                'help' => true,
                'helpformname' => 'addbook',
                'size' => 20,
            ),
            'title' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('title', 'artefact.resume'),
                'size' => 50,
            ),
            'contribution' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('contribution', 'artefact.resume'),
                'size' => 50,
            ),
            'description' => array(
                'type' => 'wysiwyg',
                'rows' => 10,
                'cols' => 50,
                'rules' => array('maxlength' => 1000000),
                'title' => get_string('detailsofyourcontribution', 'artefact.resume'),
            ),
            'filebrowser' => array(
                'type'         => 'filebrowser',
                'title'        => get_string('attachfile', 'artefact.resume'),
                'defaultvalue' => array(),
                'maxfilesize'  => get_max_upload_size(true),
                'folder'       => $folder,
                'highlight'    => $highlight,
                'browse'       => $browse,
                'page'         => get_config('wwwroot') . 'artefact/resume/achievements.php',
                'browsehelp'   => 'browsemyfiles',
                'config'       => array(
                    'upload'          => true,
                    'uploadagreement' => get_config_plugin('artefact', 'file', 'uploadagreement'),
                    'resizeonuploaduseroption' => get_config_plugin('artefact', 'file', 'resizeonuploaduseroption'),
                    'resizeonuploaduserdefault' => $USER->get_account_preference('resizeonuploaduserdefault'),
                    'createfolder'    => false,
                    'edit'            => false,
                    'select'          => true,
                ),
                'selectlistcallback' => 'artefact_get_records_by_id',
                'selectcallback'     => 'add_resume_attachment',
                'unselectcallback'   => 'delete_resume_attachment',
            ),
            'url' => array(
                'type' => 'text',
                'title' => get_string('bookurl', 'artefact.resume'),
                'size' => 70,
                'help' => true,
                'helpformname' => 'addbook',
            ),
        );
    }

    public static function bulk_delete($artefactids, $log=false) {
        ArtefactTypeResumeComposite::bulk_delete_composite($artefactids, 'book');
    }
    /**
     * returns the book artefacts which have the same values of the following fields:
     *  - owner
     *  - type
     *  - content which has:
     *      - date
     *      - title
     *      - contribution
     *
     * @param array $values
     */
    public static function get_duplicated_artefacts(array $values) {
        $fields = array('date', 'title', 'contribution');
        $where = array();
        $wherevalues = array($values['owner'], $values['type']);
        $contentvalues = $values['content'];
        foreach ($fields as $f) {
            if (!isset($contentvalues[$f])) {
                return array();
            }
            if (!empty($contentvalues[$f])) {
                $where[] = "ar.$f = ?";
                $wherevalues[] = $contentvalues[$f];
            }
        }
        $wherestr = 'WHERE a.owner = ? AND a.artefacttype = ?' . (!empty($where) ? ' AND ' . join(' AND ', $where) : '');
        return get_column_sql('
            SELECT DISTINCT a.id
            FROM {artefact} AS a
            INNER JOIN {artefact_resume_book} AS ar
            ON a.id = ar.artefact
            ' . $wherestr, $wherevalues
        );
    }

    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        return parent::render_import_entry_request($entry_content, array_keys(self::get_addform_elements()));
    }

}

class ArtefactTypeMembership extends ArtefactTypeResumeComposite {

    protected $startdate;
    protected $enddate;

    public static function get_tablerenderer_js() {
        return ArtefactTypeResumeComposite::get_tablerenderer_title_js(
                    self::get_tablerenderer_title_js_string(),
                    self::get_tablerenderer_date_js_string(),
                    self::get_tablerenderer_body_js_string(),
                    self::get_tablerenderer_attachments_js_string()
                ) . ",
                function (row, data) {
                    return jQuery('<span>', {'style':'text-align:center'}).append(row.clipcount);
                },
        ";
    }

    public static function is_wysiwyg() {
        return true;
    }

    public static function get_tablerenderer_title_js_string() {
        return "row.title";
    }

    public static function get_tablerenderer_date_js_string() {
        return " row.startdate + (row.enddate ? ' - ' + row.enddate : '')";
    }

    public static function get_tablerenderer_body_js_string() {
        return "row.description";
    }

    public static function get_tablerenderer_attachments_js_string() {
        return " listAttachments(row.attachments)";
    }

    public static function get_addform_elements() {
        global $USER;

        $folder = param_integer('folder', 0);
        $browse = (int) param_variable('browse', 0);
        $highlight = null;
        if ($file = param_integer('file', 0)) {
            $highlight = array($file);
        }

        return array(
            'startdate' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('startdate', 'artefact.resume'),
                'help' => true,
                'helpformname' => 'addmembership',
                'size' => 20,
            ),
            'enddate' => array(
                'type' => 'text',
                'title' => get_string('enddate', 'artefact.resume'),
                'size' => 20,
            ),
            'title' => array(
                'type' => 'text',
                'rules' => array(
                    'required' => true,
                ),
                'title' => get_string('title', 'artefact.resume'),
                'size' => 50,
            ),
            'description' => array(
                'type' => 'wysiwyg',
                'rows' => 10,
                'cols' => 50,
                'rules' => array('maxlength' => 1000000),
                'title' => get_string('description', 'artefact.resume'),
            ),
            'filebrowser' => array(
                'type'         => 'filebrowser',
                'title'        => get_string('attachfile', 'artefact.resume'),
                'defaultvalue' => array(),
                'maxfilesize'  => get_max_upload_size(true),
                'folder'       => $folder,
                'highlight'    => $highlight,
                'browse'       => $browse,
                'page'         => get_config('wwwroot') . 'artefact/resume/achievements.php',
                'browsehelp'   => 'browsemyfiles',
                'config'       => array(
                    'upload'          => true,
                    'uploadagreement' => get_config_plugin('artefact', 'file', 'uploadagreement'),
                    'resizeonuploaduseroption' => get_config_plugin('artefact', 'file', 'resizeonuploaduseroption'),
                    'resizeonuploaduserdefault' => $USER->get_account_preference('resizeonuploaduserdefault'),
                    'createfolder'    => false,
                    'edit'            => false,
                    'select'          => true,
                ),
                'selectlistcallback' => 'artefact_get_records_by_id',
                'selectcallback'     => 'add_resume_attachment',
                'unselectcallback'   => 'delete_resume_attachment',
            ),
        );
    }

    public static function bulk_delete($artefactids, $log=false) {
        ArtefactTypeResumeComposite::bulk_delete_composite($artefactids, 'membership');
    }

    /**
     * returns membership artefacts which have the same values of the following fields:
     *  - owner
     *  - type
     *  - content which has:
     *      - startdate
     *      - enddate
     *      - title
     *      - description
     *
     * @param array $values
     */
    public static function get_duplicated_artefacts(array $values) {
        $fields = array('startdate', 'enddate', 'title', 'description');
        $where = array();
        $wherevalues = array($values['owner'], $values['type']);
        $contentvalues = $values['content'];
        foreach ($fields as $f) {
            if (!isset($contentvalues[$f])) {
                return array();
            }
            if (!empty($contentvalues[$f])) {
                $where[] = "ar.$f = ?";
                $wherevalues[] = $contentvalues[$f];
            }
        }
        $wherestr = 'WHERE a.owner = ? AND a.artefacttype = ?' . (!empty($where) ? ' AND ' . join(' AND ', $where) : '');
        return get_column_sql('
            SELECT DISTINCT a.id
            FROM {artefact} AS a
            INNER JOIN {artefact_resume_membership} AS ar
            ON a.id = ar.artefact
            ' . $wherestr, $wherevalues
        );
    }

    public static function render_import_entry_request($entry_content, $renderfields=array()) {
        return parent::render_import_entry_request($entry_content, array_keys(self::get_addform_elements()));
    }

}

class ArtefactTypeResumeGoalAndSkill extends ArtefactTypeResume {

    public static function is_singular() {
        return true;
    }

    public function can_have_attachments() {
        return true;
    }

    public function render_self($options) {
        global $USER;
        $smarty = smarty_core();
        $smarty->assign('description', $this->get('description'));
        if (!empty($options['artefactid'])) {
            $smarty->assign('artefactid', $options['artefactid']);
        }
        if (!empty($options['editing'])) {
            $smarty->assign('editing', $options['editing']);
        }

        $attachments = $this->get_attachments();
        if ($attachments) {
            safe_require('artefact', 'comment');
            foreach ($attachments as &$attachment) {
                $f = artefact_instance_from_id($attachment->id);
                $attachment->size = $f->describe_size();
                $attachment->iconpath = $f->get_icon(array('id' => $attachment->id, 'viewid' => isset($options['viewid']) ? $options['viewid'] : 0));
                $attachment->downloadpath = get_config('wwwroot') . 'artefact/file/download.php?file=' . $attachment->id;
                $attachment->description = $f->description;
                $attachment->allowcomments = $f->get('allowcomments');
                if (!empty($options['showcommentcount'])) {
                    $count = ArtefactTypeComment::count_comments(null, array($attachment->id));
                    if ($count) {
                        $attachment->commentcount = $count[$attachment->id]->comments;
                    }
                    else {
                        $attachment->commentcount = 0;
                    }
                }
            }
            $smarty->assign('attachments', $attachments);
            $smarty->assign('count', count($attachments));
        }

        $smarty->assign('id', $this->get('id'));

        $result = array(
            'html' => $smarty->fetch('artefact:resume:fragments/goalsandskills.tpl')
        );
        return $result;
    }

    public static function get_goals_and_skills($type='') {
        global $USER;
        switch ($type) {
            case 'goals':
                $artefacts = array('personalgoal', 'academicgoal', 'careergoal');
                break;
            case 'skills':
                $artefacts = array('personalskill', 'academicskill', 'workskill');
                break;
            default:
                $artefacts = array('personalgoal', 'academicgoal', 'careergoal',
                                   'personalskill', 'academicskill', 'workskill');
        }

        $data = array();
        foreach ($artefacts as $artefact) {
            $record = get_record('artefact', 'artefacttype', $artefact, 'owner', $USER->get('id'));
            if ($record) {
                $record->exists = 1;
                // Add attachments
                if ($files = ArtefactType::attachments_from_id_list(array($record->id))) {
                    foreach ($files as $file) {
                        $record->files[] = $file;
                    }
                    $record->count = count($files);
                }
                else {
                    $record->count = 0;
                }
            }
            else {
                $record = new stdClass();
                $record->artefacttype = $artefact;
                $record->exists = 0;
                $record->count = 0;
            }
            $data[] = $record;
        }
        return $data;
    }

}

class ArtefactTypePersonalgoal extends ArtefactTypeResumeGoalAndSkill { }
class ArtefactTypeAcademicgoal extends ArtefactTypeResumeGoalAndSkill { }
class ArtefactTypeCareergoal extends ArtefactTypeResumeGoalAndSkill { }
class ArtefactTypePersonalskill extends ArtefactTypeResumeGoalAndSkill { }
class ArtefactTypeAcademicskill extends ArtefactTypeResumeGoalAndSkill { }
class ArtefactTypeWorkskill extends ArtefactTypeResumeGoalAndSkill { }

function editcomposite_validate(Pieform $form, $values) {
    $elements = $form->get_property('elements');
    if (!empty($elements['compositetype']['value'])) {
        $compositetype = $elements['compositetype']['value'];
        if (function_exists('add' . $compositetype . '_validate')) {
            call_user_func('add' . $compositetype . '_validate', $form, $values);
        }
    }
}

function addbook_validate(Pieform $form, $values) {
    // Check if string entered by user is a valid URL and reachable from here
    if (array_key_exists('url', $values) && !empty($values['url'])) {
        $isvalid = is_valid_url($values['url']);
        if (!$isvalid) {
            $form->set_error('url', get_string('notvalidurl', 'artefact.resume'));
        }
    }
}

function compositeform_submit(Pieform $form, $values) {
    $result = array();
    try {
        $result = call_static_method(generate_artefact_class_name($values['compositetype']),
            'process_compositeform', $form, $values);
    }
    catch (Exception $e) {
        $form->json_reply(PIEFORM_ERR, $e->getMessage());
    }
    $form->json_reply(PIEFORM_OK, array(
        'focusid' => $result['itemid'], 'message' => get_string('compositesaved', 'artefact.resume')
    ));
}

function compositeformedit_submit(Pieform $form, $values) {
    global $SESSION;

    $tabs = PluginArtefactResume::composite_tabs();
    $goto = get_config('wwwroot') . 'artefact/resume/';
    if (isset($tabs[$values['compositetype']])) {
        $goto .= $tabs[$values['compositetype']] . '.php';
    }
    else {
        $goto .= 'index.php';
    }

    try {
        call_static_method(generate_artefact_class_name($values['compositetype']),
            'process_compositeform', $form, $values);
    }
    catch (Exception $e) {
        $SESSION->add_error_msg(get_string('compositesavefailed', 'artefact.resume'));
        redirect($goto);
    }

    $result = array(
        'error'   => false,
        'message' => get_string('compositesaved', 'artefact.resume'),
        'goto'    => $goto,
    );
    if ($form->submitted_by_js()) {
        // Redirect back to the resume composite page from within the iframe
        $SESSION->add_ok_msg($result['message']);
        $form->json_reply(PIEFORM_OK, $result, false);
    }
    $form->reply(PIEFORM_OK, $result);
}

function simple_resumefield_form($defaults, $goto, $options = array()) {
    safe_require('artefact', 'file');
    global $USER, $simple_resume_artefacts, $simple_resume_types;
    $simple_resume_artefacts = array();
    $simple_resume_types = array_keys($defaults);

    $form = array(
        'name'              => 'resumefieldform',
        'plugintype'        => 'artefact',
        'pluginname'        => 'resume',
        'jsform'            => true,
        'class'             => 'form-group-nested',
        'successcallback'   => 'simple_resumefield_submit',
        'jssuccesscallback' => 'simple_resumefield_success',
        'jserrorcallback'   => 'simple_resumefield_error',
        'configdirs'        => array(get_config('libroot') . 'form/', get_config('docroot') . 'artefact/file/form/'),
        'elements'          => array(),
    );

    foreach ($simple_resume_types as $t) {
        try {
            $simple_resume_artefacts[$t] = artefact_instance_from_type($t);
            $content = clean_html($simple_resume_artefacts[$t]->get('description'));
        }
        catch (Exception $e) {
            $content = $defaults[$t]['default'];
        }

        if (!empty($options['editortitle'])) {
            $editortitle = $options['editortitle'];
        }
        else {
            $editortitle = get_string('description', 'artefact.resume');
        }

        $fieldset = $t . 'fs';
        $form['elements'][$fieldset] = array(
            'type' => 'fieldset',
            'legend' => get_string($t, 'artefact.resume'),
            'elements' => array(
                $t => array(
                    'type'  => 'wysiwyg',
                    'class' => 'js-hidden tinymce-large',
                    'title' => $editortitle,
                    'hiddenlabel' => true,
                    'rows'  => 100,
                    'cols'  => 365,
                    'defaultvalue' => $content,
                    'rules' => array('maxlength' => 1000000),
                ),
                $t . 'display' => array(
                    'type' => 'html',
                    'value' => $content,
                ),
                $t . 'submit' => array(
                    'type' => 'submitcancel',
                    'class' => 'js-hidden',
                    'subclass' => array('btn-primary'),
                    'value' => array(get_string('save'), get_string('cancel')),
                    'goto' => get_config('wwwroot') . $goto,
                ),
                $t . 'edit' => array(
                    'type' => 'button',
                    'class' => 'nojs-hidden-block openedit btn-primary',
                    'value' => get_string('edit'),
                ),
            ),
        );
        if (!empty($defaults[$t]['fshelp'])) {
            $form['elements'][$fieldset]['help'] = true;
        }
    }

    $form['elements']['goto'] = array(
        'type'  => 'hidden',
        'value' => $goto,
    );

    return $form;
}

function simple_resumefield_submit(Pieform $form, $values) {
    global $simple_resume_types, $simple_resume_artefacts, $USER;
    require_once('embeddedimage.php');
    $owner = $USER->get('id');

    if (isset($values['coverletter'])) {
        $newcoverletter = EmbeddedImage::prepare_embedded_images($values['coverletter'], 'resumecoverletter', $USER->get('id'));
        $values['coverletter'] = $newcoverletter;
    }
    else if (isset($values['interest'])) {
        $newinterest = EmbeddedImage::prepare_embedded_images($values['interest'], 'resumeinterest', $USER->get('id'));
        $values['interest'] = $newinterest;
    }

    foreach ($simple_resume_types as $t) {
        if (isset($values[$t . 'submit']) && isset($values[$t])) {
            if (!isset($simple_resume_artefacts[$t])) {
                $classname = generate_artefact_class_name($t);
                $simple_resume_artefacts[$t] = new $classname(0, array(
                    'owner' => $USER->get('id'),
                    'title' => get_string($t),
                ));
            }
            $simple_resume_artefacts[$t]->set('description', $values[$t]);
            $simple_resume_artefacts[$t]->commit();

            $data = array(
                'message' => get_string('goalandskillsaved', 'artefact.resume'),
                'update'  => $t,
                'content' => clean_html($values[$t]),
                'goto'    => get_config('wwwroot') . $values['goto'],
            );
            $form->reply(PIEFORM_OK, $data);
        }
    }

    $form->reply(PIEFORM_OK, array('goto' => get_config('wwwroot') . $values['goto']));
}

function add_resume_attachment($attachmentid) {
    global $artefact;
    if ($artefact) {
        $artefact->attach($attachmentid);
    }
}

function delete_resume_attachment($attachmentid) {
    global $artefact;
    if ($artefact) {
        $artefact->detach($attachmentid);
    }
}
