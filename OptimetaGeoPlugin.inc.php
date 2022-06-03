<?php

/**
 * @file OptimetaGeoPlugin.inc.php
 *
 * Copyright (c) 2022 OPTIMETA project
 * Copyright (c) 2022 Daniel Nüst
 * Distributed under the GNU GPL v3. For full terms see the file dLICENSE.
 *
 * @class OptimetaGeoPlugin
 * @brief Plugin class for the OPTIMETA project's geo plugin.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

use phpDocumentor\Reflection\Types\Null_;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldHTML; // needed for function extendScheduleForPublication

class OptimetaGeoPlugin extends GenericPlugin
{

	protected $ojsVersion = '3.3.0.0';

	protected $templateParameters = [
		'pluginStylesheetURL' => '',
		'pluginJavaScriptURL' => '',
		//'pluginImagesURL' => '',
		//'citationsKeyForm' => '',
		'pluginApiUrl' => ''
	];

	public function register($category, $path, $mainContextId = NULL)
	{
		// Register the plugin even when it is not enabled
		$success = parent::register($category, $path, $mainContextId);

		// Current Request / Context
		$request = $this->getRequest();

		// Fill generic template parameters
		$this->templateParameters['pluginStylesheetURL'] = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css';
		$this->templateParameters['pluginJavaScriptURL'] = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js';

		// important to check if plugin is enabled before registering the hook, cause otherwise plugin will always run no matter enabled or disabled! 
		if ($success && $this->getEnabled()) {
			// custom page handler, see https://docs.pkp.sfu.ca/dev/plugin-guide/en/examples-custom-page
			HookRegistry::register('LoadHandler', array($this, 'setPageHandler'));

			// Hooks for changing the frontent Submit an Article 3. Enter Metadata 
			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'extendSubmissionMetadataFormTemplate'));

			// Hooks for changing the Metadata right before Schedule for Publication (not working yet)
			//HookRegistry::register('Form::config::before', array($this, 'extendScheduleForPublication'));

			// Hooks for changing the article page 
			HookRegistry::register('Templates::Article::Main', array(&$this, 'extendArticleMainTemplate'));
			HookRegistry::register('Templates::Article::Details', array(&$this, 'extendArticleDetailsTemplate'));
			// Templates::Article::Main 
			// Templates::Article::Details
			// Templates::Article::Footer::PageFooter

			// Hooks for changing the issue page 
			HookRegistry::register('Templates::Issue::TOC::Main', array(&$this, 'extendIssueTocTemplate'));
			HookRegistry::register('Templates::Issue::Issue::Article', array(&$this, 'extendIssueTocArticleTemplate'));

			// Hook for adding a tab to the publication phase
			HookRegistry::register('Template::Workflow::Publication', array($this, 'extendPublicationTab'));

			// Hook for creating and setting a new field in the database 
			HookRegistry::register('Schema::get::publication', array($this, 'addToSchema'));
			HookRegistry::register('Publication::edit', array($this, 'editPublication')); // Take care, hook is called twice, first during Submission Workflow and also before Schedule for Publication in the Review Workflow!!!

			// https://forum.pkp.sfu.ca/t/add-item-to-navigation-menu-from-a-generic-plugin/73414/5
			//HookRegistry::register('TemplateManager::display', array($this, 'addMapPageToMenu'));

			$request = Application::get()->getRequest();
			$templateMgr = TemplateManager::getManager($request);

			$request = Application::get()->getRequest();
			$contextId = $this->getCurrentContextId();

			// jQuery is already loaded via ojs/lib/pkp/classes/template/PKPTemplateManager.inc.php 
			$urlLeafletCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/Leaflet-1.6.0/dist/leaflet.css';
			$urlLeafletJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/Leaflet-1.6.0/dist/leaflet.js';
			$urlLeafletDrawCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/Leaflet.draw-1.0.4/dist/leaflet.draw.css';
			$urlLeafletDrawJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/Leaflet.draw-1.0.4/dist/leaflet.draw.js';
			$urlMomentJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/moment-2.18.1/moment.js';
			$urlDaterangepickerJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/daterangepicker-3.1/daterangepicker.js';
			$urlDaterangepickerCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/daterangepicker-3.1/daterangepicker.css';
			$urlLeafletControlGeocodeJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/Control.Geocoder.js';
			$urlLeafletControlGeocodeCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/Control.Geocoder.css';

			// loading the leaflet scripts, source: https://leafletjs.com/examples/quick-start/
			$templateMgr->addStyleSheet('leafletCSS', $urlLeafletCSS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addJavaScript('leafletJS', $urlLeafletJS, array('contexts' => array('frontend', 'backend')));

			// loading the leaflet draw scripts, source: https://www.jsdelivr.com/package/npm/leaflet-draw?path=dist
			$templateMgr->addStyleSheet("leafletDrawCSS", $urlLeafletDrawCSS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addJavaScript("leafletDrawJS", $urlLeafletDrawJS, array('contexts' => array('frontend', 'backend')));

			// loading the daterangepicker scripts, source: https://www.daterangepicker.com/#example2 
			//$templateMgr->addJavaScript("jqueryJS", $urlJqueryJS, array('contexts' => array('frontend', 'backend')));
			// jquery no need to load, already loaded here: ojs/lib/pkp/classes/template/PKPTemplateManager.inc.php 
			$templateMgr->addJavaScript("momentJS", $urlMomentJS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addJavaScript("daterangepickerJS", $urlDaterangepickerJS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addStyleSheet("daterangepickerCSS", $urlDaterangepickerCSS, array('contexts' => array('frontend', 'backend')));

			// loading leaflet control geocoder (search), source: https://github.com/perliedman/leaflet-control-geocoder 
			$templateMgr->addJavaScript("leafletControlGeocodeJS", $urlLeafletControlGeocodeJS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addStyleSheet("leafletControlGeocodeCSS", $urlLeafletControlGeocodeCSS, array('contexts' => array('frontend', 'backend')));

			// plugins JS scripts and CSS
			$templateMgr->assign('optimetageo_submissionMetadataFormFieldsJS', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/submissionMetadataFormFields.js');
			$templateMgr->assign('optimetageo_article_detailsJS', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/article_details.js');
			$templateMgr->assign('optimetageo_issueJS', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/issue.js');
			$templateMgr->assign('optimetageo_markerBaseUrl', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/lib/leaflet-color-markers-1.0.0/img/');

			// publication tab
		}
		return $success;
	}

	/**
	 * @param hookName
	 * @param params
	 */
	public function setPageHandler($hookName, $params)
	{
		$page = $params[0];
		if ($page === 'map') {
			$this->import('classes/handler/JournalMapHandler');
			define('HANDLER_CLASS', 'JournalMapHandler');
			return true;
		}
		return false;
	}

	public function addMapPageToMenu($hookName, $args)
	{
		$smarty = $args[0];
		$smarty->unregisterPlugin('function', 'load_menu');
		$smarty->registerPlugin('function', 'load_menu', [$this, 'doSomethingWithMenu']);
		return false;
	}

	public function doSomethingWithMenu($params, $smarty)
	{
		// TODO run smartyLoadNavigationMenuArea and then add my own item somehow 
		print_r($params);
		// modify behavior
		//print_r($smarty);
	}

	/**
	 * Function which extends the submissionMetadataFormFields template and adds template variables concerning temporal- and spatial properties 
	 * and the administrative unit if there is already a storage in the database. 
	 * @param hook Templates::Submission::SubmissionMetadataForm::AdditionalMetadata
	 */
	public function extendSubmissionMetadataFormTemplate($hookName, $params)
	{
		/*
		This way templates are loaded. 
		Its important that the corresponding hook is activated. 
		If you want to override a template you need to create a .tpl-file which is in the plug-ins template path which the same 
		path it got in the regular ojs structure. E.g. if you want to override/add something to this template 
		'/ojs/lib/pkp/templates/submission/submissionMetadataFormTitleFields.tpl'
		you have to store in in the plug-ins template path under this path 'submission/form/submissionMetadataFormFields.tpl'. 
		Further details can be found here: https://docs.pkp.sfu.ca/dev/plugin-guide/en/templates
		Where are templates located: https://docs.pkp.sfu.ca/pkp-theming-guide/en/html-smarty
		*/

		$templateMgr = &$params[1];
		$output = &$params[2];

		// example: the arrow is used to access the attribute smarty of the variable smarty 
		// $templateMgr = $smarty->smarty; 

		$request = Application::get()->getRequest();
		$context = $request->getContext();

		/*
		Check if the user has entered an username in the plugin settings for the GeoNames API (https://www.geonames.org/login). 
		The result is passed on accordingly to submissionMetadataFormFields.js as template variable. 
		*/
		$usernameGeonames = $this->getSetting($context->getId(), 'optimetaGeo_geonames_username');
		$templateMgr->assign('usernameGeonames', $usernameGeonames);
		$baseurlGeonames = $this->getSetting($context->getId(), 'optimetaGeo_geonames_baseurl');
		$templateMgr->assign('baseurlGeonames', $baseurlGeonames);

		/*
		In case the user repeats the step "3. Enter Metadata" in the process 'Submit an Article' and comes back to this step to make changes again, 
		the already entered data is read from the database, added to the template and displayed for the user.
		Data is loaded from the database, passed as template variable to the 'submissionMetadataFormFiels.tpl' 
	 	and requested from there in the 'submissionMetadataFormFields.js' to display coordinates in a map, the date and coverage information if available.
		*/
		$publicationDao = DAORegistry::getDAO('PublicationDAO');
		$submissionId = $request->getUserVar('submissionId');
		$publication = $publicationDao->getById($submissionId);
		// TODO check if the submission really belongs to the journal

		$temporalProperties = $publication->getData('optimetaGeo::temporalProperties');
		$spatialProperties = $publication->getData('optimetaGeo::spatialProperties');
		$administrativeUnit = $publication->getLocalizedData('coverage', 'en_US');

		// for the case that no data is available 
		if ($temporalProperties === null) {
			$temporalProperties = 'no data';
		}

		if ($spatialProperties === null || $spatialProperties === '{"type":"FeatureCollection","features":[],"administrativeUnits":{},"temporalProperties":{"unixDateRange":"not available","provenance":{"description":"not available","id":"not available"}}}') {
			$spatialProperties = 'no data';
		}

		if ($administrativeUnit === null || current($administrativeUnit) === '' || $administrativeUnit === '') {
			$administrativeUnit = 'no data';
		}

		//assign data as variables to the template 
		$templateMgr->assign('temporalPropertiesFromDb', $temporalProperties);
		$templateMgr->assign('spatialPropertiesFromDb', $spatialProperties);
		$templateMgr->assign('administrativeUnitFromDb', $administrativeUnit);

		// here the original template is extended by the additional template for entering geospatial metadata  
		$output .= $templateMgr->fetch($this->getTemplateResource('submission/form/submissionMetadataFormFields.tpl'));

		return false;
	}

	/**
	 * Function which extends ArticleMain Template by geospatial properties if available. 
	 * Data is loaded from the database, passed as template variable to the 'article_details.tpl' 
	 * and requested from there in the 'article_details.js' to display coordinates in a map, the date and coverage information if available.
	 * @param hook Templates::Article::Main
	 */
	public function extendArticleMainTemplate($hookName, $params)
	{
		$templateMgr = &$params[1];
		$output = &$params[2];

		$publication = $templateMgr->getTemplateVars('publication');
		$submission = $templateMgr->getTemplateVars('article');
		$submissionId = $submission->getId();

		// get data from database 
		$temporalProperties = $publication->getData('optimetaGeo::temporalProperties');
		$spatialProperties = $publication->getData('optimetaGeo::spatialProperties');
		$administrativeUnit = $publication->getLocalizedData('coverage', 'en_US');

		// for the case that no data is available 
		if ($temporalProperties === null || $temporalProperties === '') {
			$temporalProperties = 'no data';
		}

		if (($spatialProperties === null || $spatialProperties === '{"type":"FeatureCollection","features":[],"administrativeUnits":{},"temporalProperties":{"unixDateRange":"not available","provenance":"not available"}}')) {
			$spatialProperties = 'no data';
		}

		if ($administrativeUnit === null || $administrativeUnit === '') {
			$administrativeUnit = 'no data';
		}

		//assign data as variables to the template 
		$templateMgr->assign('temporalProperties', $temporalProperties);
		$templateMgr->assign('spatialProperties', $spatialProperties);
		$templateMgr->assign('administrativeUnit', $administrativeUnit);

		$output .= $templateMgr->fetch($this->getTemplateResource('frontend/objects/article_details.tpl'));

		return false;
	}

	/**
	 * Function which extends the ArticleMain Template by a download button for the geospatial Metadata as geoJSON. 
	 * @param hook Templates::Article::Details
	 */
	public function extendArticleDetailsTemplate($hookName, $params)
	{
		$templateMgr = &$params[1];
		$output = &$params[2];

		$templateMgr->assign($this->templateParameters);

		$output .= $templateMgr->fetch($this->getTemplateResource('frontend/objects/article_details_download.tpl'));

		return false;
	}

	/**
	 * Function which extends the issue TOC with a timeline and map view - needs the optimetaGeoTheme plugin!
	 * @param hook Templates::Issue::TOC::Main
	 */
	public function extendIssueTocTemplate($hookName, $params)
	{
		$templateMgr = &$params[1];
		$output = &$params[2];

		$templateMgr->assign($this->templateParameters);

		$output .= $templateMgr->fetch($this->getTemplateResource('frontend/objects/issue_map.tpl'));

		return false;
	}

	/**
	 * Function which extends each article in an issue TOC with hidden fields with geospatial data
	 * @param hook Templates::Issue::Issue::Article
	 */
	public function extendIssueTocArticleTemplate($hookName, $params)
	{
		$templateMgr = &$params[1];
		$output = &$params[2];

		$templateMgr->assign($this->templateParameters);

		$publication = $templateMgr->getTemplateVars('publication');
		$submission = $templateMgr->getTemplateVars('article');
		$submissionId = $submission->getId();

		$spatialProperties = $publication->getData('optimetaGeo::spatialProperties');
		if (($spatialProperties === null || $spatialProperties === '{"type":"FeatureCollection","features":[],"administrativeUnits":{},"temporalProperties":{"unixDateRange":"not available","provenance":"not available"}}')) {
			$spatialProperties = 'no data';
		}
		$templateMgr->assign('spatialProperties', $spatialProperties);

		//$temporalProperties = $publication->getData('optimetaGeo::temporalProperties');
		//if ($temporalProperties === null || $temporalProperties === '') {
		//	$temporalProperties = 'no data';
		//}
		//$templateMgr->assign('temporalProperties', $temporalProperties);

		//$administrativeUnit = $publication->getLocalizedData('coverage', 'en_US');
		//if ($administrativeUnit === null || $administrativeUnit === '') {
		//	$administrativeUnit = 'no data';
		//}
		//$templateMgr->assign('administrativeUnit', $administrativeUnit);

		$output .= $templateMgr->fetch($this->getTemplateResource('frontend/objects/issue_details.tpl'));

		return false;
	}

	/**
	 * @param string $hookname
	 * @param array $args [string, TemplateManager]
	 * @brief Show tab under Publications
	 */
	public function extendPublicationTab(string $hookName, array $args): void
	{
		$templateMgr = &$args[1];

		$request = $this->getRequest();
		$context = $request->getContext();
		$submission = $templateMgr->getTemplateVars('submission');
		$submissionId = $submission->getId();

		$dispatcher = $request->getDispatcher();
		//$latestPublication = $submission->getLatestPublication();
		//$apiBaseUrl = $dispatcher->url($request, ROUTE_API, $context->getData('urlPath'), '');

		//$form = new PublicationForm(
		//	$apiBaseUrl . 'submissions/' . $submissionId . '/publications/' . $latestPublication->getId(),
		//	$latestPublication,
		//	__('plugins.generic.optimetaCitationsPlugin.publication.success')
		//);

		//$stateVersionDependentName = 'state';
		//if (strstr($this->ojsVersion, '3.2.1')) {
		//	$stateVersionDependentName = 'workflowData';
		//}

		//$state = $templateMgr->getTemplateVars($stateVersionDependentName);
		//$state['components'][$this->publicationForm] = $form->getConfig();
		//$templateMgr->assign($stateVersionDependentName, $state);

		$publicationDao = DAORegistry::getDAO('PublicationDAO');
		$publication = $publicationDao->getById($submissionId);

		$this->templateParameters['submissionId'] = $submissionId;
		//$this->templateParameters['citationsParsed'] = $citationsParsed;
		//$this->templateParameters['citationsRaw'] = $citationsRaw;
		$templateMgr->assign($this->templateParameters);

		$templateMgr->display($this->getTemplateResource("submission/form/publicationTab.tpl"));
	}

	/**
	 * Function which extends the schema of the publication_settings table in the database. 
	 * There are two further rows in the table one for the spatial properties, and one for the timestamp. 
	 * @param hook Schema::get::publication
	 */
	public function addToSchema($hookName, $params)
	{
		// possible types: integer, string, text 
		$schema = $params[0];

		$timestamp = '{
			"type": "string",
			"multilingual": false,
			"apiSummary": true,
			"validation": [
				"nullable"
			]
		}';
		$timestampDecoded = json_decode($timestamp);
		$schema->properties->{'optimetaGeo::temporalProperties'} = $timestampDecoded;

		$spatialProperties = '{
			"type": "string",
			"multilingual": false,
			"apiSummary": true,
			"validation": [
				"nullable"
			]
		}';
		$spatialPropertiesDecoded = json_decode($spatialProperties);
		$schema->properties->{'optimetaGeo::spatialProperties'} = $spatialPropertiesDecoded;

		return false;
	}

	/**
	 * Function which fills the new fields (created by the function addToSchema) in the schema. 
	 * The data is collected using the 'submissionMetadataFormFields.js', then passed as input to the 'submissionMetadataFormFields.tpl'
	 * and requested from it in this php script by a POST-method. 
	 * @param hook Publication::edit
	 */
	function editPublication(string $hookname, array $params)
	{
		$newPublication = $params[0];
		$params = $params[2];

		$temporalProperties = $_POST['temporalProperties'];
		$spatialProperties = $_POST['spatialProperties'];
		$administrativeUnit = $_POST['administrativeUnit'];

		// null if there is no possibility to input data (metadata input before Schedule for Publication)
		if ($spatialProperties !== null) {
			$newPublication->setData('optimetaGeo::spatialProperties', $spatialProperties);
		}

		if ($temporalProperties !== null && $temporalProperties !== "") {
			$newPublication->setData('optimetaGeo::temporalProperties', $temporalProperties);
		}

		if ($administrativeUnit !== null) {
			$newPublication->setData('coverage', $administrativeUnit, 'en_US'); // we don't use locale for the field
		}
	}

	/**
	 * Not working function to edit a form before Schedule for Publication. 
	 * Possible solution can be found here: https://forum.pkp.sfu.ca/t/insert-in-submission-settings-table/61291/19?u=tnier01, 
	 * which is already implemented partly here, but commented out! 
	 */
	public function extendScheduleForPublication(string $hookName, FormComponent $form): void
	{

		// Import the FORM_METADATA constant
		import('lib.pkp.classes.components.forms.publication.PKPMetadataForm');

		if ($form->id !== 'metadata' || !empty($form->errors)) return;

		if ($form->id === 'metadata') {

			/*
			$publication = Services::get('publication');
			$temporalProperties = $publication->getData('optimetaGeo::temporalProperties');
			$spatialProperties = $publication->getData('optimetaGeo::spatialProperties');
			*/

			/*$form->addField(new \PKP\components\forms\FieldOptions('jatsParser::references', [
				'label' => 'Hello',
				'description' => 'Hello',
				'type' => 'radio',
				'options' => null,
				'value' => null
			]));*/

			// Add a plain HTML field to the form
			/*$form->addField(new FieldHTML('myFieldName', [
				'label' => 'My Field Name',
				'description' => '<p>Add any HTML code that you want.</p>
				<div id="mapdiv" style="width: 1116px; height: 400px; float: left;  z-index: 0;"></div>
				<script src="{$optimetageo_submissionMetadataFormFieldsJS}" type="text/javascript" defer></script>',
			]));*/
		}
	}

	/**
	 * @copydoc Plugin::getActions() - https://docs.pkp.sfu.ca/dev/plugin-guide/en/settings
	 * Function needed for Plugin Settings.
	 */
	public function getActions($request, $actionArgs)
	{

		// Get the existing actions
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) {
			return $actions;
		}

		// Create a LinkAction that will call the plugin's
		// `manage` method with the `settings` verb.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$linkAction = new LinkAction(
			'settings',
			new AjaxModal(
				$router->url(
					$request,
					null,
					null,
					'manage',
					null,
					array(
						'verb' => 'settings',
						'plugin' => $this->getName(),
						'category' => 'generic'
					)
				),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		);

		// Add the LinkAction to the existing actions.
		// Make it the first action to be consistent with
		// other plugins.
		array_unshift($actions, $linkAction);

		return $actions;
	}

	/**
	 * @copydoc Plugin::manage() - https://docs.pkp.sfu.ca/dev/plugin-guide/en/settings#the-form-class 
	 * Function needed for Plugin Settings. 
	 */
	public function manage($args, $request)
	{
		switch ($request->getUserVar('verb')) {
			case 'settings':

				// Load the custom form
				$this->import('OptimetaGeoSettingsForm');
				$form = new OptimetaGeoSettingsForm($this);

				// Fetch the form the first time it loads, before
				// the user has tried to save it
				if (!$request->getUserVar('save')) {
					$form->initData();
					return new JSONMessage(true, $form->fetch($request));
				}

				// Validate and execute the form
				$form->readInputData();
				if ($form->validate()) {
					$form->execute();
					return new JSONMessage(true);
				}
		}
		return parent::manage($args, $request);
	}

	/**
	 * Provide a name for this plugin (plugin gallery)
	 *
	 * The name will appear in the Plugin Gallery where editors can
	 * install, enable and disable plugins.
	 */
	public function getDisplayName()
	{
		return __('plugins.generic.optimetaGeo.name');
	}

	/**
	 * Provide a description for this plugin (plugin gallery) 
	 *
	 * The description will appear in the Plugin Gallery where editors can
	 * install, enable and disable plugins.
	 */
	public function getDescription()
	{
		return __('plugins.generic.optimetaGeo.description');
	}

	/**
	 * Get the current context ID or the site-wide context ID (0) if no context
	 * can be found.
	 */
	function getCurrentContextId()
	{
		$context = Application::get()->getRequest()->getContext();
		return is_null($context) ? 0 : $context->getId();
	}
}
