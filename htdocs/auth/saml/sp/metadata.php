<?php
/**
 *
 * @package    mahara
 * @subpackage auth-saml
 * @author     Piers Harding <piers@catalyst.net.nz>
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 * @copyright  2001-3001 Moodle Pty Ltd (http://moodle.com)
 *
 * This file incorporates work covered by the following copyright and
 * permission notice:
 *
 *    Moodle - Modular Object-Oriented Dynamic Learning Environment
 *             http://moodle.com
 *
 *    Copyright (C) 2001-3001 Martin Dougiamas        http://dougiamas.com
 *
 *    This program is free software; you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation; either version 2 of the License, or
 *    (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details:
 *
 *             https://www.gnu.org/licenses/gpl-3.0.html
 */

define('INTERNAL', 1);
define('PUBLIC', 1);
define('IGNOREMAINTENANCE', 1);
define('XMLRPC', 1);
define('TITLE', '');
global $CFG, $USER, $SESSION;
require(dirname(dirname(dirname(dirname(__FILE__)))) . '/init.php');
require_once(get_config('docroot') . 'auth/saml/lib.php');

// check that the plugin is active
if (get_field('auth_installed', 'active', 'name', 'saml') != 1) {
    redirect();
}

PluginAuthSaml::init_simplesamlphp();

$config = SimpleSAML\Configuration::getInstance();
if ($config->getBoolean('admin.protectmetadata', false)) {
    SimpleSAML\Utils\Auth::requireAdmin();
}
$sourceId = 'default-sp';
$source = SimpleSAML\Auth\Source::getById($sourceId);
if ($source === null) {
    throw new SimpleSAML\Error\AuthSource($sourceId, 'Could not find authentication source.');
}

if (!($source instanceof \SimpleSAML\Module\saml\Auth\Source\SP)) {
    throw new SimpleSAML\Error\AuthSource($sourceId,
        'The authentication source is not a SAML Service Provider.');
}

$entityId = $source->getEntityId();
$spconfig = $source->getMetadata();
$store = \SimpleSAML\Store::getInstance();

$metaArray20 = array();

$slosvcdefault = array(
    \SAML2\Constants::BINDING_HTTP_REDIRECT,
    \SAML2\Constants::BINDING_SOAP,
);

$slob = $spconfig->getArray('SingleLogoutServiceBinding', $slosvcdefault);
$slol = get_config('wwwroot') . "auth/saml/sp/module.php/saml/sp/saml2-logout.php/{$sourceId}";

foreach ($slob as $binding) {
    if ($binding == \SAML2\Constants::BINDING_SOAP && !($store instanceof \SimpleSAML\Store\SQL)) {
        // we cannot properly support SOAP logout
        continue;
    }
    $metaArray20['SingleLogoutService'][] = array(
        'Binding'  => $binding,
        'Location' => $slol,
    );
}

$assertionsconsumerservicesdefault = array(
    'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
    'urn:oasis:names:tc:SAML:1.0:profiles:browser-post',
    'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
    'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01',
);

if ($spconfig->getString('ProtocolBinding', '') == 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser') {
    $assertionsconsumerservicesdefault[] = 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser';
}

$assertionsconsumerservices = $spconfig->getArray('acs.Bindings', $assertionsconsumerservicesdefault);

$index = 0;
$eps = array();
foreach ($assertionsconsumerservices as $services) {

    $acsArray = array('index' => $index);
    switch ($services) {
        case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST':
            $acsArray['Binding'] = \SAML2\Constants::BINDING_HTTP_POST;
            $acsArray['Location'] = get_config('wwwroot') . "auth/saml/sp/module.php/saml/sp/saml2-acs.php/{$sourceId}";
            break;
        case 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post';
            $acsArray['Location'] = get_config('wwwroot') . "auth/saml/sp/module.php/saml/sp/saml1-acs.php/{$sourceId}";
            break;
        case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact';
            $acsArray['Location'] = get_config('wwwroot') . "auth/saml/sp/module.php/saml/sp/saml2-acs.php/{$sourceId}";
            break;
        case 'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01';
            $acsArray['Location'] = get_config('wwwroot') . "auth/saml/sp/module.php/saml/sp/saml1-acs.php/{$sourceId}".'/artifact';
            break;
        case 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser':
            $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser';
            $acsArray['Location'] = get_config('wwwroot') . "auth/saml/sp/module.php/saml/sp/saml2-acs.php/{$sourceId}";
            $acsArray['hoksso:ProtocolBinding'] = \SAML2\Constants::BINDING_HTTP_REDIRECT;
            break;
    }
    $eps[] = $acsArray;
    $index++;
}

$metaArray20['AssertionConsumerService'] = $eps;

$availableCerts = array();
$keys = array();
$certInfo = SimpleSAML\Utils\Crypto::loadPublicKey($spconfig, false, 'new_');
if ($certInfo !== null && array_key_exists('certData', $certInfo)) {
    $availableCerts['new_idp.crt'] = $certInfo;
    $hasNewCert = true;

    $certData = $certInfo['certData'];

    $keys[] = array(
        'type'            => 'X509Certificate',
        'signing'         => true,
        'encryption'      => true,
        'X509Certificate' => $certInfo['certData'],
    );
}
else {
    $hasNewCert = false;
}

$certInfo = SimpleSAML\Utils\Crypto::loadPublicKey($spconfig);
if ($certInfo !== null && array_key_exists('certData', $certInfo)) {
    $availableCerts['idp.crt'] = $certInfo;
    $certData = $certInfo['certData'];

    $keys[] = array(
        'type'            => 'X509Certificate',
        'signing'         => true,
        'encryption'      => ($hasNewCert ? false : true),
        'X509Certificate' => $certInfo['certData'],
    );
}
else {
    $certData = null;
}

$format = $spconfig->getString('NameIDPolicy', null);
if ($format !== null) {
    $metaArray20['NameIDFormat'] = $format;
}

$name = $spconfig->getLocalizedString('name', null);
$attributes = $spconfig->getArray('attributes', array());

if ($name !== null && !empty($attributes)) {
    $metaArray20['name'] = $name;
    $metaArray20['attributes'] = $attributes;
    $metaArray20['attributes.required'] = $spconfig->getArray('attributes.required', array());

    if (empty($metaArray20['attributes.required'])) {
        unset($metaArray20['attributes.required']);
    }

    $description = $spconfig->getArray('description', null);
    if ($description !== null) {
        $metaArray20['description'] = $description;
    }

    $nameFormat = $spconfig->getString('attributes.NameFormat', null);
    if ($nameFormat !== null) {
        $metaArray20['attributes.NameFormat'] = $nameFormat;
    }
}

// add organization info
$orgName = $spconfig->getLocalizedString('OrganizationName', null);
if ($orgName !== null) {
    $metaArray20['OrganizationName'] = $orgName;

    $metaArray20['OrganizationDisplayName'] = $spconfig->getLocalizedString('OrganizationDisplayName', null);
    if ($metaArray20['OrganizationDisplayName'] === null) {
        $metaArray20['OrganizationDisplayName'] = $orgName;
    }

    $metaArray20['OrganizationURL'] = $spconfig->getLocalizedString('OrganizationURL', null);
    if ($metaArray20['OrganizationURL'] === null) {
        throw new SimpleSAML\Error\Exception('If OrganizationName is set, OrganizationURL must also be set.');
    }
}

if ($spconfig->hasValue('contacts')) {
    $contacts = $spconfig->getArray('contacts');
    foreach ($contacts as $contact) {
        $metaArray20['contacts'][] = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
    }
}

// add technical contact
$email = $config->getString('technicalcontact_email', 'na@example.org', false);
if ($email && $email !== 'na@example.org') {
    $techcontact['emailAddress'] = $email;
    $techcontact['name'] = $config->getString('technicalcontact_name', null);
    $techcontact['contactType'] = 'technical';
    $metaArray20['contacts'][] = \SimpleSAML\Utils\Config\Metadata::getContact($techcontact);
}

// add certificate
if (count($keys) === 1) {
    $metaArray20['certData'] = $keys[0]['X509Certificate'];
}
else if (count($keys) > 1) {
    $metaArray20['keys'] = $keys;
}

// add EntityAttributes extension
if ($spconfig->hasValue('EntityAttributes')) {
    $metaArray20['EntityAttributes'] = $spconfig->getArray('EntityAttributes');
}

// add UIInfo extension
if ($spconfig->hasValue('UIInfo')) {
    $metaArray20['UIInfo'] = $spconfig->getArray('UIInfo');
}

// add RegistrationInfo extension
if ($spconfig->hasValue('RegistrationInfo')) {
    $metaArray20['RegistrationInfo'] = $spconfig->getArray('RegistrationInfo');
}

// add signature options
if ($spconfig->hasValue('WantAssertionsSigned')) {
    $metaArray20['saml20.sign.assertion'] = $spconfig->getBoolean('WantAssertionsSigned');
}
if ($spconfig->hasValue('redirect.sign')) {
    $metaArray20['redirect.validate'] = $spconfig->getBoolean('redirect.sign');
}
else if ($spconfig->hasValue('sign.authnrequest')) {
    $metaArray20['validate.authnrequest'] = $spconfig->getBoolean('sign.authnrequest');
}

$supported_protocols = array('urn:oasis:names:tc:SAML:1.1:protocol', \SAML2\Constants::NS_SAMLP);

$metaArray20['metadata-set'] = 'saml20-sp-remote';
$metaArray20['entityid'] = $entityId;

$metaBuilder = new SimpleSAML\Metadata\SAMLBuilder($entityId);
$metaBuilder->addMetadataSP20($metaArray20, $supported_protocols);
$metaBuilder->addOrganizationInfo($metaArray20);

$xml = $metaBuilder->getEntityDescriptorText();

unset($metaArray20['UIInfo']);
unset($metaArray20['metadata-set']);
unset($metaArray20['entityid']);

// sanitize the attributes array to remove friendly names
if (isset($metaArray20['attributes']) && is_array($metaArray20['attributes'])) {
    $metaArray20['attributes'] = array_values($metaArray20['attributes']);
}

// sign the metadata if enabled
$xml = SimpleSAML\Metadata\Signer::sign($xml, $spconfig->toArray(), 'SAML 2 SP');

if (array_key_exists('output', $_REQUEST) && $_REQUEST['output'] == 'xhtml') {

    $t = new SimpleSAML\XHTML\Template($config, 'metadata.php', 'admin');

    $t->data['clipboard.js'] = true;
    $t->data['available_certs'] = $availableCerts;
    $t->data['header'] = 'saml20-sp'; // TODO: Replace with headerString in 2.0
    $t->data['headerString'] = $t->noop('metadata_saml20-sp');
    $t->data['metaurl'] = get_config('wwwroot') . "auth/saml/sp/metadata.php";
    $t->data['metadata'] = htmlspecialchars($xml);
    $t->data['metadataflat'] = '$metadata[' . var_export($entityId, true) . '] = ' . var_export($metaArray20, true) . ';';

    $t->show();
}
else {
    header('Content-Type: application/samlmetadata+xml');
    echo($xml);
}
