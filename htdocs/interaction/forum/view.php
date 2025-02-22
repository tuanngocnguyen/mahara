<?php
/**
 * Forum interaction plugin - helper for displaying the forums summary
 *
 * @package    mahara
 * @subpackage interaction-forum
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

define('INTERNAL', 1);
define('PUBLIC', 1);
define('MENUITEM', 'engage/index');
define('MENUITEM_SUBPAGE', 'forums');
define('SECTION_PLUGINTYPE', 'interaction');
define('SECTION_PLUGINNAME', 'forum');
define('SECTION_PAGE', 'view');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
require_once('group.php');
safe_require('interaction', 'forum');
require_once(get_config('docroot') . 'interaction/lib.php');
define('SUBSECTIONHEADING', get_string('nameplural', 'interaction.forum'));

$forumid = param_integer('id');
$offset = param_integer('offset', 0);
$userid = $USER->get('id');
$topicsperpage = 25;

// if offset isn't a multiple of $topicsperpage, make it the closest smaller multiple
$offset = (int)($offset / $topicsperpage) * $topicsperpage;

$forum = get_record_sql(
    'SELECT f.title, f.description, f.id, COUNT(t.id) AS topiccount, s.forum AS subscribed, g.id AS groupid, g.name AS groupname, ic.value AS newtopicusers
    FROM {interaction_instance} f
    INNER JOIN {group} g ON (g.id = f."group" AND g.deleted = ?)
    LEFT JOIN {interaction_forum_topic} t ON (t.forum = f.id AND t.deleted != 1)
    LEFT JOIN {interaction_forum_subscription_forum} s ON (s.forum = f.id AND s."user" = ?)
    LEFT JOIN {interaction_forum_instance_config} ic ON (f.id = ic.forum AND ic.field = \'createtopicusers\')
    WHERE f.id = ?
    AND f.deleted != 1
    GROUP BY 1, 2, 3, 5, 6, 7, 8',
    array(0, $userid, $forumid)
);

if (!$forum) {
    throw new InteractionInstanceNotFoundException(get_string('cantfindforum', 'interaction.forum', $forumid));
}

define('GROUP', $forum->groupid);

$membership = user_can_access_forum((int)$forumid);
$admin = (bool)($membership & PluginInteractionForum::INTERACTION_FORUM_ADMIN);
$moderator = (bool)($membership & PluginInteractionForum::INTERACTION_FORUM_MOD);
$group = get_group_by_id($forum->groupid, true);
$publicgroup = $group->public;
if (!$membership && !$publicgroup) {
    throw new GroupAccessDeniedException(get_string('cantviewforums', 'interaction.forum'));
}

// Get other forums to which the current user can move topics
$otherforums = array();
if ($admin) {
    $otherforums = get_records_sql_array(
        'SELECT id, title
        FROM {interaction_instance} f
        WHERE f.id <> ?
            AND f.group = ?
            AND f.deleted = 0
            AND f.plugin = ?
        ORDER BY f.title',
        array($forumid, $forum->groupid, 'forum')
    );
}
else if ($moderator) {
    $otherforums = get_records_sql_array(
        'SELECT id, title
        FROM {interaction_instance} f
            INNER JOIN {interaction_forum_moderator} fm ON (fm.forum = f.id)
        WHERE f.id <> ?
            AND f.group = ?
            AND f.deleted = 0
            AND f.plugin = ?
            AND fm.user = ?
        ORDER BY f.title',
        array($forumid, $forum->groupid, 'forum', $userid)
    );
}
$ineditwindow = group_within_edit_window($group);

if (!$ineditwindow) {
    $moderator = false;
}

define('TITLE', $forum->groupname . ' - ' . $forum->title);

$feedlink = get_config('wwwroot') . 'interaction/forum/atom.php?type=f&id=' . $forum->id;

$moderators = get_column_sql(
    'SELECT gm.user FROM {interaction_forum_moderator} gm
    INNER JOIN {usr} u ON (u.id = gm.user AND u.deleted = 0)
    WHERE gm.forum = ?',
    array($forumid)
);

// updates the selected topics as subscribed/closed/sticky
if ($membership && param_exists('checked')) {
    $checked = array_map('intval', array_keys(param_variable('checked')));
    $type = null;

    // get type based on which button was pressed
    if (param_exists('updatetopics')) {
        $type = param_variable('type');
    }
    // check that user is only messing with topics from this forum
    $alltopics = get_column('interaction_forum_topic', 'id', 'forum', $forumid, 'deleted', 0);
    if ($checked == array_intersect($checked, $alltopics)) { // $checked is a subset of the topics in this forum
        form_validate(param_variable('sesskey', null));
        if ($moderator && $type == 'sticky') {
            set_field_select('interaction_forum_topic', 'sticky', 1, 'id IN (' . implode(',', $checked) . ')', array());
            $SESSION->add_ok_msg(get_string('topicstickysuccess', 'interaction.forum'));
        }
        else if ($moderator && $type == 'unsticky') {
            set_field_select('interaction_forum_topic', 'sticky', 0, 'id IN (' . implode(',', $checked) . ')', array());
            $SESSION->add_ok_msg(get_string('topicunstickysuccess', 'interaction.forum'));
        }
        else if ($moderator && $type == 'closed') {
            set_field_select('interaction_forum_topic', 'closed', 1, 'id IN (' . implode(',', $checked) . ')', array());
            $SESSION->add_ok_msg(get_string('topicclosedsuccess', 'interaction.forum'));
        }
        else if ($moderator && $type == 'open') {
            set_field_select('interaction_forum_topic', 'closed', 0, 'id IN (' . implode(',', $checked) . ')', array());
            $SESSION->add_ok_msg(get_string('topicopenedsuccess', 'interaction.forum'));
        }
        else if ($moderator && $type == 'moveto') {
            $newforumid = param_integer('newforum');
            // Check if the new forum is in the current group
            $newforum = interaction_instance_from_id($newforumid);
            if ($newforum && $newforum->get('group') == $forum->groupid) {
                set_field_select('interaction_forum_topic', 'forum', $newforumid, 'id IN (' . implode(',', $checked) . ')', array());
                PluginInteractionForum::interaction_forum_new_post($checked);
                $SESSION->add_ok_msg(get_string('topicmovedsuccess', 'interaction.forum', count($checked)));
            }
        }
        else if ($type == 'subscribe' && !$forum->subscribed) {
            db_begin();
            foreach ($checked as $key => $value) {
                if (!record_exists('interaction_forum_subscription_topic', 'user', $USER->get('id'), 'topic', $value)) {
                    insert_record('interaction_forum_subscription_topic',
                        (object) array(
                            'user'  => $USER->get('id'),
                            'topic' => $value,
                            'key'   => PluginInteractionForum::generate_unsubscribe_key(),
                    ));
                }
            }
            db_commit();
            $SESSION->add_ok_msg(get_string('topicsubscribesuccess', 'interaction.forum'));
        }
        else if ($type == 'unsubscribe' && !$forum->subscribed) {
            delete_records_sql('DELETE FROM {interaction_forum_subscription_topic}
                WHERE topic IN (' . implode(',', $checked) . ') AND "user" = ?',
                array($USER->get('id')
            ));
            $SESSION->add_ok_msg(get_string('topicunsubscribesuccess', 'interaction.forum'));
        }
    }
    else { // $checked contains bad values
        $SESSION->add_error_msg(get_string('topicupdatefailed', 'interaction.forum'));
    }
    redirect('/interaction/forum/view.php?id=' . $forumid . '&offset=' . $offset);
}

$allowunsubscribe =  get_config_plugin_instance('interaction_forum', $forum->id, 'allowunsubscribe');

if ($membership && ( !isset($allowunsubscribe) || $allowunsubscribe == 1)) {
    $forum->subscribe = pieform(array(
        'name' => 'subscribe_forum',
        'renderer' => 'div',
        'plugintype' => 'interaction',
        'pluginname' => 'forum',
        'autofocus' => false,
        'class' => 'form-as-button float-start',
        'elements' => array(
            'submit' => array(
                'type' => 'button',
                'usebuttontag' => true,
                'class' => 'btn-secondary',
                'value' => $forum->subscribed ? '<span class="icon icon-times left text-danger" role="presentation" aria-hidden="true"></span> ' . get_string('unsubscribefromforum', 'interaction.forum') : '<span class="icon icon-star left" role="presentation" aria-hidden="true"></span> ' .  get_string('subscribetoforum', 'interaction.forum'),
                'help' => false
            ),
            'forum' => array(
                'type' => 'hidden',
                'value' => $forumid
            ),
            'redirect' => array(
                'type' => 'hidden',
                'value' => 'view'
            ),
            'group' => array(
                'type' => 'hidden',
                'value' => $forum->groupid
            ),
            'offset' => array(
                'type' => 'hidden',
                'value' => $offset
            ),
            'type' => array(
                'type' => 'hidden',
                'value' => $forum->subscribed ? 'unsubscribe' : 'subscribe'
            )
        )
    ));
}

// gets the info about topics
// the last post is found by taking the max id of the posts in a topic with the max post time
// taking the max id is needed because multiple posts can have the same post time
$sql = 'SELECT t.id, p1.subject, p1.body, p1.poster, p1.deleted, m.user AS moderator,
    COUNT(p2.id) AS postcount, t.closed, s.topic AS subscribed, p4.id AS lastpost, '
    . db_format_tsfield('p4.ctime', 'lastposttime') . ', p4.poster AS lastposter,
    m2.user AS lastpostermoderator, us1.deleted AS deleteduser, us4.deleted AS lastposterdeleteduser,
    p1.approved
    FROM {interaction_forum_topic} t
    INNER JOIN {interaction_forum_post} p1 ON (p1.topic = t.id AND p1.parent IS NULL)
    LEFT JOIN {usr} us1 ON us1.id = p1.poster
    LEFT JOIN (
        SELECT m.forum, m.user
        FROM {interaction_forum_moderator} m
        INNER JOIN {usr} u ON (m.user = u.id AND u.deleted = 0)
    ) m ON (m.forum = t.forum AND p1.poster = m.user)
    INNER JOIN {interaction_forum_post} p2 ON (p2.topic = t.id AND p2.deleted != 1)
    LEFT JOIN {interaction_forum_subscription_topic} s ON (s.topic = t.id AND s."user" = ?)
    INNER JOIN (
        SELECT MAX(p2.id) AS post, t.id AS topic
        FROM {interaction_forum_topic} t
        INNER JOIN (
            SELECT MAX(p.ctime) AS ctime, t.id AS topic
            FROM {interaction_forum_topic} t
            INNER JOIN {interaction_forum_post} p ON (p.topic = t.id AND p.deleted = 0)
            GROUP BY 2
        ) p1 ON t.id = p1.topic
        INNER JOIN {interaction_forum_post} p2 ON (p1.topic = p2.topic AND p1.ctime = p2.ctime AND p2.deleted = 0)
        GROUP BY 2
    ) p3 ON p3.topic = t.id
    LEFT JOIN {interaction_forum_post} p4 ON (p4.id = p3.post)
    LEFT JOIN {usr} us4 ON us4.id = p4.poster
    LEFT JOIN {interaction_forum_topic} t2 ON (p4.topic = t2.id)
    LEFT JOIN (
        SELECT m.forum, m.user
        FROM {interaction_forum_moderator} m
        INNER JOIN {usr} u ON (m.user = u.id AND u.deleted = 0)
    ) m2 ON (p4.poster = m2.user AND t2.forum = m2.forum)
    WHERE t.forum = ?
    AND t.sticky = ?
    AND t.deleted != 1';

$stickyparams = array($userid, $forumid, 1);
$regularparams = array($userid, $forumid, 0);
if (!$moderator) {
      $sql .= ' AND (p1.approved = 1 OR p4.poster= ? )';
      $stickyparams[] = $userid;
      $regularparams[] = $userid;
}

$sql .= ' GROUP BY 1, 2, 3, 4, 5, 6, 8, 9, 10, p4.ctime, p4.poster, p4.id, m2.user,
us1.deleted, us4.deleted, p1.approved
ORDER BY p4.ctime DESC, p4.id DESC';

$stickytopics = get_records_sql_array($sql, $stickyparams);

$regulartopics = get_records_sql_array($sql, $regularparams, $offset, $topicsperpage);

setup_topics($stickytopics);
setup_topics($regulartopics);

$pagination = build_pagination(array(
    'url' => get_config('wwwroot') . 'interaction/forum/view.php?id=' . $forumid,
    'count' => $forum->topiccount,
    'limit' => $topicsperpage,
    'offset' => $offset,
    'jumplinks' => 6,
    'numbersincludeprevnext' => 2,
    'resultcounttext' => get_string('ntopicslower', 'interaction.forum', $forum->topiccount),
));

$inlinejavascript = <<<EOF
jQuery(function($) {
    $('input.topic-checkbox').each(function() {

        var tr = $(this).closest('tr');
        var origColour = tr.css('backgroundColor');
        $(this).on('click', function(e) {
            if (tr.css('backgroundColor') === origColour) {
                tr.css('backgroundColor', '#ffc');
            }
            else {
                tr.css('backgroundColor', origColour);
            }
        });
    });
    if (action = document.getElementById('action')) {
        $(action).on('change', function(e) {
            if (this.options[this.selectedIndex].value == 'moveto') {
                $('#otherforums').removeClass('d-none');
            }
            else {
                $('#otherforums').addClass('d-none');
            }
        });
    }
});
EOF;

$headers = array();
if ($publicgroup) {
    $headers[] = '<link rel="alternate" type="application/atom+xml" href="' . $feedlink . '">';
}

$smarty = smarty(array(), $headers);
$smarty->assign('heading', $forum->groupname);
$smarty->assign('subheading', $forum->title);
$smarty->assign('headingclass', 'page-header');
$smarty->assign('forum', $forum);
$smarty->assign('otherforums', $otherforums);
$smarty->assign('publicgroup', $publicgroup);
$smarty->assign('ineditwindow', $ineditwindow);
$smarty->assign('feedlink', $feedlink);
$smarty->assign('membership', $membership);
$smarty->assign('moderator', $moderator);
$smarty->assign('admin', $admin);
$smarty->assign('groupadmins', group_get_admin_ids($forum->groupid));
$smarty->assign('stickytopics', $stickytopics);
$smarty->assign('regulartopics', $regulartopics);
$smarty->assign('moderators', $moderators);
$smarty->assign('closedicon', 'icon icon-lock-alt');
$smarty->assign('subscribedicon', 'icon icon-star');
$smarty->assign('pagination', $pagination['html']);
$smarty->assign('INLINEJAVASCRIPT', $inlinejavascript);
$smarty->display('interaction:forum:view.tpl');

/**
 * Set up topics
 *
 * format body
 * format lastposttime
 *
 * @param  array|false $topics (reference) Array of objects
 * @return void
 */
function setup_topics(&$topics) {
    global $moderator;
    if ($topics) {
        foreach ($topics as $topic) {
            $topic->lastposttime = relative_date(get_string('strftimerecentrelative', 'interaction.forum'), get_string('strftimerecent'), $topic->lastposttime);
            $topic->feedlink = get_config('wwwroot') . 'interaction/forum/atom.php?type=t&id=' . $topic->id;
            $topic->containsobjectionable = false;
            if ($moderator) {
                $topic->containsobjectionable = (bool) count_records_sql(
                    "SELECT count(fp.id) FROM {interaction_forum_post} fp
                     JOIN {objectionable} o ON (o.objecttype = 'forum' AND o.objectid = fp.id)
                     WHERE fp.deleted = 0 AND o.resolvedby IS NULL AND o.resolvedtime IS NULL AND fp.topic = ?", array($topic->id));
            }
        }
    }
}
