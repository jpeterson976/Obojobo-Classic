<?php
require_once(dirname(__FILE__)."/../internal/app.php");

$config = new \Amfphp_Core_Config();
$config->checkArgumentCount = false;
$config->serviceFolders = [\AppCfg::DIR_BASE . 'internal/includes/amfphpServices/'];
$config->pluginsFolders[] = \AppCfg::DIR_BASE . 'internal/includes/amfphpPlugins';
$config->disabledPlugins = ['AmfphpLogger', 'AmfphpErrorHandler','AmfphpMonitor','AmfphpJson', 'AmfphpGet', 'ObojoboAmfphpGet', 'LegacyAmfphpGet', 'AmfphpDummy', 'AmfphpDiscovery', 'AmfphpAuthentication', 'AmfphpVoConverter'];

// $voFolders[] = array(dirname(__FILE__) . '/NamespaceVo/', 'NVo');
// $config->pluginsConfig['AmfphpVoConverter'] = array('voFolders' => $voFolders);
//set this to enforce vo conversion. If you do that, only sending UserVo1 shall work, not UserVo2
$config->pluginsConfig = [
	'ObojoboAmfphpVoConverter' => [
		'voFolders' => [\AppCfg::DIR_BASE . \AppCfg::DIR_CLASSES],
		'enforceConversion' => false
	]
];


$gateway = \Amfphp_Core_HttpRequestGatewayFactory::createGateway($config);
$gateway->service();
$gateway->output();
