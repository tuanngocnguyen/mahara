<?php
/**
 *
 * @package    mahara
 * @subpackage mobile
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

define('INTERNAL', 1);
define('PUBLIC', 1);
define('JSON', 1);
define('NOSESSKEY', 1);

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
require_once('lib.php');

mobile_api_json_reply( array('fail' => get_string('deprecatedmobileapp', 'admin') ) );
