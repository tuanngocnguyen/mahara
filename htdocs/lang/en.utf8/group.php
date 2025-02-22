<?php
/**
 *
 * @package    mahara
 * @subpackage lang
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

defined('INTERNAL') || die();

// my groups
$string['groupname'] = 'Group name';
$string['groupshortname'] = 'Short name';
$string['associatewithinstitution'] = 'Associate with institution';
$string['associatewithaninstitution'] = 'Associate group \'%s\' with an institution.';
$string['groupassociated'] = 'Group associated with institution successfully';
$string['creategroup'] = 'Create group';
$string['copygroup'] = 'Copy group "%s"';
$string['groupmemberrequests'] = 'Pending membership requests';
$string['membershiprequests'] = 'Membership requests';
$string['sendinvitation'] = 'Send invite';
$string['invitetogroupsubject'] = 'You were invited to join a group';
$string['invitetogroupmessage1'] = "Hi %s,

I'd like to invite you to join the group '%s'.

Thank you
%s

Follow the link to accept or decline this invitation.";
$string['invitetogroupmessagereason'] = "Hi %s,

I'd like to invite you to join the group '%s'.

My reason is:

%s

Thank you
%s

Follow the link to accept or decline this invitation.";
$string['inviteuserfailed'] = 'Failed to invite';
$string['userinvited'] = 'Invite sent';
$string['addedtogroupsubject'] = 'You were added to a group';
$string['addedtogroupmessage'] = '%s has added you to a group, \'%s\'. Click on the link below to see the group.';
$string['adduserfailed'] = 'Failed to add';
$string['useradded'] = 'Person added';
$string['editgroup'] = 'Edit group';
$string['savegroup'] = 'Save group';
$string['groupsaved'] = 'Group saved successfully';
$string['invalidgroup'] = 'The group does not exist';
$string['canteditdontown'] = 'You cannot edit this group because you do not own it.';
$string['groupdescription'] = 'Group description';
$string['groupurl'] = 'Group homepage URL';
$string['groupurldescription'] = "The URL of your group's homepage. This field must be 3-30 characters long.";
$string['groupurltaken'] = 'That URL is already taken by another group.';

$string['Membership'] = 'Membership';
$string['Roles'] = 'Roles';
$string['Open'] = 'Open';
$string['opendescription'] = 'People can join the group without approval from group administrators.';
$string['requestdescription'] = 'People can send membership requests to group administrators.';
$string['Controlled'] = 'Controlled';
$string['controlleddescription'] = 'Group administrators can add people to the group without their consent, and members cannot leave the group.';
$string['membershiptype'] = 'Group membership type';
$string['membershiptype.controlled'] = 'Controlled membership';
$string['membershiptype.approve']    = 'Approved membership';
$string['membershiptype.open']       = 'Open membership';
$string['membershiptype.abbrev.controlled'] = 'Controlled';
$string['membershiptype.abbrev.approve']    = 'Normal';
$string['membershiptype.abbrev.open']       = 'Open';
$string['membershipopencontrolled']  = 'Membership cannot be both open and controlled.';
$string['membershipopenrequest']     = "Open membership groups do not accept membership requests.";
$string['requestmembership']         = 'Request membership';
$string['pendingmembers']            = 'Pending members';
$string['reason']                    = 'Reason';
$string['approve']                   = 'Approve';
$string['reject']                    = 'Reject';
$string['groupalreadyexists'] = 'A group by this name already exists.';
$string['groupalreadyexistssuggest'] = 'A group by this name already exists. The name "%s" is available';
$string['groupshortnamealreadyexists'] = 'A group by this short name already exists.';
$string['invalidshortname'] = 'Invalid group short name.';
$string['shortnameformat1'] = 'Group short names can be from 2 to 255 characters in length and contain only lowercase alphanumeric characters, ".", "-", and "_".';
$string['groupmaxreached'] = 'Groups cannot be added to this institution because the maximum number of groups allowed in the institution has been reached. Please get in touch with the <a href="%sinstitution/index.php?institution=%s">institution administrator</a> to increase the limit.';
$string['groupmaxreachednolink'] = 'Groups cannot be added to this institution because the maximum number of groups allowed in the institution has been reached. Please get in touch with the institution administrator to increase the limit.';
$string['exceedsgroupmax'] = 'Adding this many groups exceeds the group limit for your institution. You can add %s more groups within your limit. Try adding fewer groups or get in touch with the site administrator to discuss raising the limit.';
$string['Created'] = 'Created';
$string['editable'] = 'Editable';
$string['editability'] = 'Editability';
$string['windowstart'] = 'Start date';
$string['windowstartdescription'] = 'The group cannot be edited by normal group members before this date. This date will also be used as default start date for any imported plans.';
$string['windowdatedescriptionadmin'] = 'Only set this date if required and you are adding groups in bulk. Remember to clear this field once you are done.';
$string['windowend'] = 'End date';
$string['windowenddescription'] = 'The group cannot be edited by normal group members after this date. This date will also be used as default completion date for any imported plans.';
$string['editwindowbetween'] = 'Between %s and %s';
$string['editwindowfrom'] = 'From %s';
$string['editwindowuntil'] = 'Until %s';
$string['groupadmins'] = 'Group administrators';
$string['editroles1'] = 'Create and edit';
$string['editrolesdescription2'] = 'Roles with permission to create and edit content and organise it into group portfolios.';
$string['allexceptmember'] = 'Everyone except ordinary members';
$string['Admin'] = 'Administrator';
$string['publiclyviewablegroup'] = 'Publicly viewable group';
$string['publiclyviewablegroupdescription1'] = 'Allow anyone online to view this group including the forums.';
$string['Type'] = 'Type';
$string['publiclyvisible'] = 'Publicly visible';
$string['Public'] = 'Public';
$string['usersautoadded'] = 'Auto-add people';
$string['usersautoaddeddescription1'] = 'Automatically add anybody who joins the site to this group.';
$string['groupcategory'] = 'Group category';
$string['allcategories'] = 'All categories';
$string['groupoptionsset'] = 'Group options have been updated.';
$string['nocategoryselected'] = 'No category selected';
$string['notcategorised'] = 'Not categorised';
$string['hasrequestedmembership'] = 'has requested membership of this group';
$string['hasbeeninvitedtojoin'] = 'has been invited to join this group';
$string['groupinvitesfrom'] = 'Invited to join:';
$string['requestedmembershipin'] = 'Requested membership in:';
$string['viewnotify'] = 'Shared page notifications';
$string['viewnotifydescription3'] = 'Select which group members should receive a notification when a new group portfolio is created and when a group member shares one of their portfolios with the group. The group member sharing the portfolio will not receive this notification. For very large groups it would be best to limit this to non ordinary members as it can produce a lot of notifications.';
$string['commentnotify'] = 'Comment notifications';
$string['commentnotifydescription1'] = 'Select which group members should receive a notification when comments are placed on a group page and artefacts.';
$string['allowsendnow'] = 'Send forum posts immediately';
$string['allowsendnowdescription1'] = 'Any group member can choose to send forum posts immediately. If this option is set to "Off", only group administrators, tutors and moderators can do so.';
$string['hiddengroup'] = 'Hide group';
$string['hiddengroupdescription2'] = 'Hide this group on the "Groups" page.';
$string['hidemembers'] = 'Hide membership';
$string['hidemembersdescription'] = 'Hide the group\'s membership listing from non-members.';
$string['hidemembersfrommembers'] = 'Hide membership from members';
$string['hidemembersfrommembersdescription1'] = 'Hide the members of this group. Only group administrators can see the list of members. Administrators are still shown on the group homepage.';
$string['friendinvitations'] = 'Friend invitations';
$string['invitefriendsdescription1'] = 'Allow members to invite friends to join this group. Regardless of this setting, group administrators can always send invitations to anyone.';
$string['invitefriends'] = 'Invite friends';
$string['Recommendations'] = 'Recommendations';
$string['suggestfriendsdescription1'] = 'Allow members to send a recommendation for joining this group to their friends from a button on the group homepage.';
$string['suggesttofriends'] = 'Recommend to friends';
$string['userstosendrecommendationsto'] = 'People who will be sent a recommendation';
$string['suggestgroupnotificationsubject'] = '%s suggested you join a group';
$string['suggestgroupnotificationmessage'] = '%s suggested that you join the group "%s" on %s';
$string['nrecommendationssent'] = array(
    0 => '1 recommendation sent',
    1 => '%d recommendations sent',
);
$string['suggestinvitefriends'] = 'You cannot enable both friend invitations and recommendations.';
$string['suggestfriendsrequesterror'] = 'You can only enable friend recommendations on open or request groups.';
$string['editwindowendbeforestart'] = 'The end date must be after the start date.';

$string['editgroupmembership'] = 'Edit group membership';
$string['editmembershipforuser'] = 'Edit membership for %s';
$string['changedgroupmembership'] = 'Group membership updated successfully.';
$string['changedgroupmembershipsubject'] = 'Your group memberships have been changed.';
$string['addedtongroupsmessage'] = array(
        0 => "%2\$s has added you to the group:\n\n%3\$s\n\n",
        1 => "%2\$s has added you to the groups:\n\n%3\$s\n\n",
);
$string['removedfromngroupsmessage'] = array(
        0 => "%2\$s has removed you from the group:\n\n%3\$s\n\n",
        1 => "%2\$s has removed you from the groups:\n\n%3\$s\n\n",
);
$string['cantremovememberfromgroup'] = "You cannot remove members from %s.";
$string['current'] = "Current";
$string['requests'] = "Requests";
$string['invites'] = "Invites";

// Used to refer to all the members of a group - NOT a "member" group role!
$string['member'] = 'member';
$string['members'] = 'members';
$string['Members'] = 'Members';
$string['nmembers1'] = array(
    '%s member',
    '%s members',
);

$string['memberrequests'] = 'Membership requests';
$string['declinerequest'] = 'Decline request';
$string['submittedviews'] = 'Submitted pages';
$string['submitted'] = 'Submitted';
$string['releaseview'] = 'Release page';
$string['releasecollection'] = 'Release collection';
$string['invite'] = 'Invite';
$string['remove'] = 'Remove';
$string['updatemembership'] = 'Update membership';
$string['memberchangefailed'] = 'Failed to update some membership information';
$string['memberchangesuccess'] = 'Membership status changed successfully';
$string['portfolioreleasedsubject'] = 'Portfolio "%s" released';
$string['portfolioreleasedmessage'] = 'Your portfolio "%s" has been released from "%s" by %s.';
$string['portfolioreleasedsuccess'] = 'Portfolio was released successfully';
$string['portfolioreleasedsuccesswithname'] = 'Portfolio "%s" was released successfully';
$string['portfolioreleasefailed'] =  'Failed to release "%s" after archiving';
$string['portfolioreleasedpending'] = 'Portfolio will be released after archiving';
$string['leavegroup'] = 'Leave this group';
$string['joingroup'] = 'Join this group';
$string['requestjoingroup'] = 'Request to join this group';
$string['grouphaveinvite'] = 'You have been invited to join this group.';
$string['grouphaveinvitewithrole'] = 'You have been invited to join this group with the role';
$string['groupnotinvited'] = 'You have not been invited to join this group.';
$string['groupinviteaccepted'] = 'Invite accepted successfully. You are now a group member.';
$string['groupinvitedeclined'] = 'Invite declined successfully.';
$string['acceptinvitegroup'] = 'Accept';
$string['declineinvitegroup'] = 'Decline';
$string['leftgroup'] = 'You have now left this group.';
$string['leftgroupfailed'] = 'Leaving group failed';
$string['couldnotleavegroup'] = 'You cannot leave this group.';
$string['joinedgroup'] = 'You are now a group member.';
$string['couldnotjoingroup'] = 'You cannot join this group.';
$string['membershipcontrolled'] = 'Membership of this group is controlled.';
$string['membershipbyinvitationonly'] = 'Membership to this group is by invitation only.';
$string['grouprequestsent'] = 'Group membership request sent';
$string['couldnotrequestgroup'] = 'Could not send group membership request';
$string['cannotrequestjoingroup'] ='You cannot request to join this group.';
$string['grouprequestsubject'] = 'New group membership request';
$string['grouprequestmessage'] = '%s would like to join your group %s.';
$string['grouprequestmessagereason'] = "%s would like to join your group %s. Their reason for wanting to join is:\n\n%s";
$string['cantdeletegroup'] = 'You cannot delete this group.';
$string['groupconfirmdelete'] = "This will delete all pages, files and forums contained within the group. Are you sure you wish to fully delete this group and all its content?";
$string['deletegroup'] = 'Group deleted successfully';
$string['deletegroup1'] = 'Delete group';
$string['allmygroups'] = 'All my groups';
$string['groupsimin']  = 'Groups I\'m in';
$string['groupsiown']  = 'Groups I own';
$string['groupsiminvitedto'] = 'Groups I\'m invited to';
$string['groupsiwanttojoin'] = 'Groups I want to join';
$string['groupsicanjoin'] = 'Groups I can join';
$string['requestedtojoin'] = 'You have requested to join this group';
$string['groupnotfound'] = 'Group with id %s not found';
$string['groupnotfoundname'] = 'Group %s not found';
$string['groupconfirmleave'] = 'Are you sure you want to leave this group?';
$string['cantleavegroup'] = 'You cannot leave this group.';
$string['usercantleavegroup'] = 'This group member cannot leave this group.';
$string['usercannotchangetothisrole'] = 'This group member cannot change to this role.';
$string['leavespecifiedgroup'] = 'Leave group \'%s\'';
$string['memberslist'] = 'Members: ';
$string['nogroups'] = 'No groups';
$string['deletespecifiedgroup'] = 'Delete group \'%s\'';
$string['requestjoinspecifiedgroup'] = 'Request to join group \'%s\'';
$string['youaregroupmember'] = 'You are a member of this group.';
$string['youaregroupadmin'] = 'You are an administrator in this group.';
$string['youowngroup'] = 'You own this group.';
$string['groupsnotin'] = 'Groups I\'m not in';
$string['allgroups'] = 'All groups';
$string['allgroupmembers'] = 'All group members';
$string['trysearchingforgroups1'] = 'Try <a href="%sgroup/index.php?filter=canjoin">searching for groups</a> to join.';
$string['nogroupsfound'] = 'No groups found.';
$string['group'] = 'group';
$string['Group'] = 'Group';
$string['groups'] = 'groups';
$string['ngroups'] = array(
    '%s group',
    '%s groups'
);
$string['notamember'] = 'You are not a member of this group.';
$string['notmembermayjoin'] = 'You must join the group \'%s\' to see this page.';
$string['declinerequestsuccess'] = 'Group membership request has been declined successfully.';
$string['notpublic'] = 'This group is not public.';
$string['moregroups'] = 'More groups';
$string['deletegroupnotificationsubject'] = 'The group "%s" was deleted';
$string['deletegroupnotificationmessage'] = 'You were a member of the group %s on %s. This group has now been deleted.';
$string['hidegroupmembers'] = 'Hide members';
$string['hideonlygrouptutors'] = 'Hide tutors';

// Bulk add, invite
$string['addmembers'] = 'Add members';
$string['invitationssent'] = '%d invitations sent';
$string['newmembersadded'] = 'Added %d new members';
$string['potentialmembers'] = 'Potential members';
$string['sendinvitations'] = 'Send invitations';
$string['userstobeadded'] = 'People to be added';
$string['userstobeinvited'] = 'People to be invited';
$string['potentialmemberstorecommend'] = 'Turn selected potential members into people to send a recommendation';
$string['recommendedtopotentialmembers'] = 'Turn selected recommended people into potential members';
$string['potentialmoderatorstomoderators'] = 'Turn selected potential moderators into moderators';
$string['moderatorstopotentialmoderators'] = 'Turn selected moderators into potential moderators';

// friendslist
$string['reasonoptional'] = 'Reason (optional)';
$string['request'] = 'Request';

$string['friendformaddsuccess'] = 'Added %s to your friends list';
$string['friendformremovesuccess'] = 'Removed %s from your friends list';
$string['friendformrequestsuccess'] = 'Sent a friendship request to %s';
$string['friendformacceptsuccess'] = 'Accepted friend request';
$string['friendformrejectsuccess'] = 'Rejected friend request';

$string['addtofriendslist'] = 'Add to friends';
$string['requestfriendship'] = 'Request friendship';

$string['addedtofriendslistsubject'] = '%s has added you as a friend';
$string['addedtofriendslistmessage'] = '%s added you as a friend. This means that %s is on your friends list now, too. '
    . ' Click on the link below to see their profile page.';

$string['requestedfriendlistsubject'] = 'New friend request';
$string['requestedfriendlistinboxmessage'] = '%s has requested that you add them as a friend.  '
    .' You can either do this by clicking the following link or by going to your friends list page.';

$string['requestedfriendlistmessageexplanation'] = '%s has requested that you add them as a friend.'
    . ' You can either do this by clicking the following link or by going to your friends list page'
    . ' Their reason was:
    ';

$string['removefromfriendslist'] = 'Remove from friends';
$string['removefromfriends'] = 'Remove %s from friends';
$string['removedfromfriendslistsubject'] = 'Removed from friends list';
$string['removedfromfriendslistmessage'] = '%s has removed you from their friends list.';
$string['removedfromfriendslistmessagereason'] = '%s has removed you from their friends list. Their reason was: ';
$string['cantremovefriend'] = 'You cannot remove this person from your friends list.';

$string['friendshipalreadyrequested'] = 'You have requested to be added to %s\'s friends list.';
$string['friendshipalreadyrequestedowner'] = '%s has requested to be added to your friends list.';
$string['rejectfriendshipreason'] = 'Reason for rejecting request';
$string['alreadyfriends'] = 'You are already friends with %s.';

$string['friendrequestacceptedsubject'] = 'Friend request accepted';
$string['friendrequestacceptedmessage'] = '%s has accepted your friend request and they have been added to your friends list.';
$string['friendrequestrejectedsubject'] = 'Friend request rejected';
$string['friendrequestrejectedmessage'] = '%s has rejected your friend request.';
$string['friendrequestrejectedmessagereason'] = '%s has rejected your friend request. Their reason was: ';
$string['acceptfriendshiprequestfailed'] = 'Failed to accept friendship request.';
$string['addtofriendsfailed'] = 'Failed to add %s to your friends list.';

$string['allfriends']     = 'All friends';
$string['currentfriends'] = 'Current friends';
$string['pendingfriends'] = 'Pending friends';
$string['pendingfriend'] =  'Pending friend';
$string['backtofriendslist'] = 'Back to friends list';
$string['findnewfriends'] = 'Find new friends';
$string['Collections']    = 'Collections';
$string['Views']          = 'Pages';
$string['Portfolios'] = 'Portfolios';
$string['Files']          = 'Files';
$string['noviewstosee']   = 'None that you can see';
$string['whymakemeyourfriend'] = 'This is why you should make me your friend:';
$string['approverequest'] = 'Approve request';
$string['denyrequest']    = 'Deny request';
$string['pending']        = 'pending';
$string['pendingsince']   = 'pending since %s';
$string['requestedsince']   = 'requested since %s';
$string['trysearchingforfriends'] = 'Try %ssearching for new friends%s to grow your network.';
$string['nobodyawaitsfriendapproval'] = 'Nobody is awaiting your approval to become your friend.';
$string['sendfriendrequest'] = 'Send friend request';
$string['addtomyfriends'] = 'Add to my friends';
$string['friendshiprequested'] = 'Friendship requested';
$string['existingfriend'] = 'existing friend';
$string['nosearchresultsfound'] = 'No search results found';
$string['friend'] = 'friend';
$string['friends'] = 'friends';
$string['nfriends'] = array(
    '%s friend',
    '%s friends'
);
$string['user'] = 'person';
$string['users'] = 'persons';
$string['nusers'] = array(
    '%s person',
    '%s people'
);
$string['Friends'] = 'Friends';
$string['friendrequests'] = 'Friend requests';
$string['Everyone'] = 'Everyone';
$string['myinstitutions'] = 'My institutions';

$string['friendlistfailure'] = 'Failed to modify your friends list';
$string['userdoesntwantfriends'] = 'This person does not want any new friends.';
$string['cannotrequestfriendshipwithself'] = 'You cannot request friendship with yourself.';
$string['cantrequestfriendship'] = 'You cannot request friendship with this person.';

// Messaging between users
$string['messagebody'] = 'Send message'; // wtf
$string['sendmessage'] = 'Send message';
$string['messagesent'] = 'Message sent';
$string['messagenotsent'] = 'Failed to send message';
$string['newusermessage'] = 'New message from %s';
$string['newusermessageemailbody'] = '%s has sent you a message. To view this message, visit

%s';
$string['sendmessageto'] = 'Send message to %s';
$string['viewmessage'] = 'View message';
$string['Reply'] = 'Reply';

$string['denyfriendrequest'] = 'Deny friend request';
$string['deny'] = 'Deny';
$string['sendfriendshiprequest'] = 'Send %s a friendship request';
$string['cantdenyrequest'] = 'That is not a valid friendship request.';
$string['cantmessageuser'] = 'You cannot send this person a message.';
$string['cantmessageuserdeleted'] = 'You cannot send this person a message because the account has been deleted.';
$string['cantviewmessage'] = 'You cannot view this message.';
$string['requestedfriendship'] = 'requested friendship';
$string['notinanygroups'] = 'Not in any groups';
$string['addusertogroup'] = 'Add to ';
$string['inviteusertojoingroup'] = 'Invite to ';
$string['invitemembertogroup'] = 'Invite %s to join \'%s\'';
$string['cannotinvitetogroup'] = 'You cannot invite this person to this group.';
$string['useralreadyinvitedtogroup'] = 'This person has already been invited to, or is already a member of, this group.';
$string['removefriend'] = 'Remove friend';
$string['denyfriendrequestlower'] = 'Deny friend request';

// Group interactions (activities)
$string['groupinteractions'] = 'Group activities';
$string['nointeractions'] = 'There are no activities in this group.';
$string['notallowedtoeditinteractions'] = 'You are not allowed to add or edit activities in this group.';
$string['notallowedtodeleteinteractions'] = 'You are not allowed to delete activities in this group.';
$string['interactionsaved'] = '%s saved successfully';
$string['deleteinteraction'] = 'Delete %s \'%s\'';
$string['deleteinteractionsure'] = 'Are you sure you want to do this? It cannot be undone.';
$string['interactiondeleted'] = '%s deleted successfully';
$string['addnewinteraction'] = 'Add new %s';
$string['title'] = 'Title';
$string['Role'] = 'Role';
$string['changerole'] = 'Change role';
$string['changeroleofuseringroup'] = 'Change role of %s in %s';
$string['changerolepermissions'] = 'Change %s role for %s';
$string['currentrole'] = 'Current role';
$string['changerolefromto'] = 'Change role from %s to';
$string['rolechanged'] = 'Role changed';
$string['removefromgroup'] = 'Remove from group';
$string['userremoved'] = 'Group member removed';
$string['About'] = 'About';
$string['aboutgroup'] = 'About %s';

$string['Joined'] = 'Joined';

$string['invitemembersdescription'] = 'You can invite people to join this group through their profile pages or <a href="%s">send multiple invitations at once</a>.';
$string['membersdescription:controlled'] = 'This is a controlled membership group. You can add people directly through their profile pages or <a href="%s">add many people at once</a>.';

// View submission
$string['submit'] = 'Submit';
$string['allowssubmissions'] = 'Allows submissions';
$string['allowsubmissions'] = 'Allow submissions';
$string['allowssubmissionsdescription1'] = "Members can submit pages to the group that are then locked. These pages cannot be edited until they are released by a group tutor or administrator.";
$string['allowssubmissionsdescription'] = 'Members can submit pages to the group.';
$string['allowsarchives'] = 'Allow archiving of submissions';
$string['allowsarchiveserror'] = 'You can only allow archiving if submissions are allowed.';
$string['allowsarchivesdescription2'] = 'Portfolios are archived as zipped files during the submission release process.';

// Group reports
$string['report'] = 'Report';
$string['grouphasntcreatedanyviewsyet'] = "This group has not created any pages yet.";
$string['noviewssharedwithgroupyet'] = "There are no pages shared with this group yet.";
$string['groupsharedviewsscrolled'] = "You have scrolled past the end of the shared pages list.";
$string['groupcreatedviewsscrolled'] = "You have scrolled past the end of the group's pages list.";
$string['nnonmembers'] = array(
    '1 non-member',
    '%s non-members',
);
$string['membercommenters'] = "Members involved";
$string['extcommenters'] = "Non-members involved";
$string['groupparticipationreports'] = "Participation report";
$string['groupparticipationreportsdesc1'] = "Group administrators can access a report displaying all group and shared pages and who has commented on them.";

// Group archives
$string['archives'] = 'Archive';
$string['grouparchivereportsheading'] = "Archive of submissions";
$string['grouparchivereports'] = "Access archive of submissions";
$string['grouparchivereportsdesc'] = "Group administrators can access archived submission files.";
$string['grouparchivereportserror'] = 'You can only allow archive submission report if submissions are allowed.';

$string['returntogroupportfolios1'] = "Return to group portfolios";
$string['showintroduction'] = "Introduction";

$string['addgrouplabel'] = 'Add group labels';
$string['addgrouplabeldescription'] = 'Add one or more labels to this group that are only visible to you. By adding a label, you can organise the groups to your liking. You can also determine, which groups you want to see in the sidebar and on your profile page.';
$string['addgrouplabelfilter'] = 'Add the group label filter "%s"';
$string['addlabel'] = 'Add label';
$string['displayonlylabels'] = 'Display only groups labeled with';
$string['filterbygrouplabel'] = 'Filter by label';
$string['grouplabelnotmember'] = 'Currently, you are not a member of this group. Please refresh the page.';
$string['grouplabeladded'] = 'Group label added';
$string['grouplabelupdated'] = 'Group label updated';
$string['groupnovalidlabelsupplied'] = 'Your label must consist of at least two characters.';
$string['label'] = 'Label';
$string['labelfor'] = 'Add a label to group "%s"';
$string['mygrouplabel'] = 'My group labels';
$string['removegrouplabelfilter'] = 'Remove the group label filter "%s"';
$string['agrouplabeltooshort'] = 'One or more group labels are too short, they need at least %s characters.';
$string['agrouplabeltoolong'] = 'One or more group labels are too long, they can have at most %s characters.';

// Current Archive release messages.
$string['currentarchivereleasedsubmittedhostmessage'] = 'Your portfolio "%s" has been released from "%s" by %s. You can submit your portfolio again if needed.';
