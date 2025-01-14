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

define('INTERNAL', 1);
define('PUBLIC', 1);
define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'site');
define('SECTION_PAGE', 'forgotpass');

require('init.php');

if ($SESSION->get('pwchangerequested')) {
    $SESSION->set('pwchangerequested', false);
    die_info(get_string('pwchangerequestsentfullinfo'));
}

if (param_exists('key')) {
    $SESSION->set('forgotpasskey', param_alphanum('key'));
    redirect('/forgotpass.php');
}
if ($SESSION->get('forgotpasskey')) {
    define('TITLE', get_string('changepassword'));

    if (!$pwrequest = get_record('usr_password_request', 'key', $SESSION->forgotpasskey)) {
        $SESSION->set('forgotpasskey', false);
        die_info(get_string('nosuchpasswordrequest'));
    }

    if (strtotime($pwrequest->expiry) < time()) {
        $SESSION->set('forgotpasskey', false);
        die_info(get_string('passwordresetexpired'));
    }

    $form = array(
        'name' => 'forgotpasschange',
        'method' => 'post',
        'action' => '',
        'autofocus' => true,
        'elements' => array(
            'password1' => array(
                'type' => 'password',
                'title' => get_string('password'),
                'description' => get_password_policy_description('user'),
                'showstrength' => true,
                'rules' => array(
                    'required' => true
                )
            ),
            'password2' => array(
                'type' => 'password',
                'title' => get_string('confirmpassword'),
                'rules' => array(
                    'required' => true
                )
            ),
            'user' => array(
                'type' => 'hidden',
                'value' => $pwrequest->usr
            ),
            'submit' => array(
                'type' => 'submit',
                'class' => 'btn-primary',
                'value' => get_string('change')
            )
        )
    );
    $form = pieform($form);
    $smarty = smarty();
    $smarty->assign('forgotpasschange_form', $form);
    $smarty->assign('heading', get_string('changepassword'));
    $smarty->display('forgotpass.tpl');
    exit;
}
else {
    define('TITLE', get_string('forgotusernamepassword'));
}

$form = array(
    'name'      => 'forgotpass',
    'method'    => 'post',
    'action'    => '',
    'autofocus' => true,
    'elements'  => array(
        'emailusername' => array(
            'type' => 'text',
            'title' => get_string('emailaddressorusername'),
            'description' => get_string('emailaddressdescription'),
            'rules' => array(
                'required' => true,
            )
        ),
        'captcha' => array(
            'type' => 'captcha',
        ),
        'submit' => array(
            'type' => 'submit',
            'class' => 'btn-primary',
            'value' => get_string('sendrequest')
        )
    )
);

function forgotpass_submit(Pieform $form, $values) {
    global $SESSION;

    $sendemail = true;
    if (!($user = get_record_sql("SELECT u.*
        FROM {usr} u INNER JOIN {auth_instance} ai ON (u.authinstance = ai.id AND ai.active = 1)
        WHERE (LOWER(u.email) = ? OR LOWER(u.username) = ?)
        AND ai.authname = 'internal'", array_fill(0, 2, strtolower($values['emailusername']))))) {
            $sendemail = false;
    }

    if ($sendemail) {
        try {
            $pwrequest = new stdClass();
            $pwrequest->usr = $user->id;
            $pwrequest->expiry = db_format_timestamp(time() + 86400);
            $pwrequest->key = get_random_key();
            $sitename = get_config('sitename');
            $fullname = display_name($user);
            // Override the disabled status of this e-mail address
            $user->ignoredisabled = true;
            email_user($user, null,
                get_string('forgotusernamepasswordemailsubject', 'mahara', $sitename),
                get_string('forgotusernamepasswordemailmessagetext', 'mahara',
                    $fullname,
                    $sitename,
                    $user->username,
                    get_config('wwwroot') . 'forgotpass.php?key=' . $pwrequest->key,
                    get_config('wwwroot') . 'contact.php',
                    $sitename),
                get_string('forgotusernamepasswordemailmessagehtml', 'mahara',
                    $fullname,
                    $sitename,
                    $user->username,
                    get_config('wwwroot') . 'forgotpass.php?key=' . $pwrequest->key,
                    get_config('wwwroot') . 'forgotpass.php?key=' . $pwrequest->key,
                    get_config('wwwroot') . 'contact.php',
                    $sitename));
            insert_record('usr_password_request', $pwrequest);
        }
        catch (SQLException $e) {
            die_info(get_string('forgotpassemailsendunsuccessful'));
        }
        catch (EmailException $e) {
            die_info(get_string('forgotpassemailsendunsuccessful'));
        }

        // Add a note if this e-mail address is over the bounce threshold to
        // warn users that they may not receive the e-mail
        if ($mailinfo = get_record_select('artefact_internal_profile_email', '"owner" = ? AND principal = 1', array($user->id))) {
            if (check_overcount($mailinfo)) {
                $SESSION->add_info_msg(get_string('forgotpassemailsentanyway1', 'mahara', get_config('sitename')));
            }
        }

        // Unsetting disabled status overriding
        unset($user->ignoredisabled);
    }
    // Add a marker in the session to say that the user has registered
    $SESSION->set('pwchangerequested', true);

    redirect('/forgotpass.php');
}

function forgotpasschange_validate(Pieform $form, $values) {
    $user = new User();
    $user->find_by_id($values['user']);
    password_validate($form, $values, $user);
}

// TODO:
//   password_validate to maharalib, use it in places specified, test with a drop/create run
//   support autofocus => (true|'id'), remove stuff doing autofocus from where it is, focus error fields
//   commit stuff
function forgotpasschange_submit(Pieform $form, $values) {
    global $SESSION, $USER;

    unset($SESSION->forgotpasskey);
    try {
        $user = new User();
        $user->find_by_id($values['user']);
    } catch (AuthUnknownUserException $e) {
        throw new UserException('Request to change the password for a user who does not exist');
    }

    $authobj = AuthFactory::create($user->authinstance);
    if ($password = $authobj->change_password($user, $values['password1'])) {

        // Remove the password request(s) for the user
        delete_records('usr_password_request', 'usr', $values['user']);

        ensure_user_account_is_active($user);

        $USER->reanimate($user->id, $user->authinstance);

        // Destroy other sessions of the user
        remove_user_sessions($USER->get('id'));

        $SESSION->add_ok_msg(get_string('passwordchangedok'));
        redirect();
        exit;
    }

    throw new SystemException('User "' . $user->username
        . ' tried to change their password, but the attempt failed');
}
$form = pieform($form);
$smarty = smarty();
$smarty->assign('forgotpass_form', $form);
$smarty->assign('heading', get_string('forgotusernamepassword'));
$smarty->display('forgotpass.tpl');
