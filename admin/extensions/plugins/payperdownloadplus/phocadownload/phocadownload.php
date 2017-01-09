<?php
/**
 * @component Pay per Download component
* @author Ratmil Torres
* @copyright (C) Ratmil Torres
* @license GNU/GPL http://www.gnu.org/copyleft/gpl.html
**/
defined( '_JEXEC' ) or
die( 'Direct Access to this location is not allowed.' );

// import the JPlugin class
jimport('joomla.event.plugin');
require_once(JPATH_PLUGINS.'/system/payperdownloadplus/payperdownloadplus.php');


class plgPayperDownloadPlusPhocadownload extends plgSystemPayperDownloadPlus
{
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		// load the language file
		$lang = JFactory::getLanguage();
		$lang->load('plg_payperdownloadplus_phocadownload', JPATH_SITE . '/administrator');
	}

	function onIsActive(&$plugins)
	{
		jimport('joomla.filesystem.folder');
		if(!JFolder::exists(JPATH_ROOT . '/administrator/components/com_phocadownload'))
			return false;
			$component = JComponentHelper::getComponent('com_phocadownload', true);
			if($component->enabled)
			{
				jimport('joomla.filesystem.file');
				$image = "";
				if(JFile::exists(JPATH_ROOT . '/administrator/components/com_payperdownload/images/icon-48-phocadownload.png')) {
					$image = "administrator/components/com_payperdownload/images/icon-48-phocadownload.png";
				}
				$plugins[] = array("name" => "Phoca Download", "description" => JText::_("PAYPERDOWNLOADPLUS_PHOCA_DOWNLOAD_FILE_68"),
						"image" => $image);
			}
	}

	function reorderCats(&$cats_ordered, $cats, $parent_id, $depth)
	{
		$count = count($cats);
		for($i = 0; $i < $count; $i++)
		{
			$cat = $cats[$i];
			if($cat->parent_id == $parent_id)
			{
				$cat->depth = $depth;
				$cats_ordered[] = $cat;
				if(!isset($cat->end_branch) || !$cat->end_branch)
					$this->reorderCats($cats_ordered, $cats, $cat->id, $depth + 1);
			}
		}
	}

	function getCategories()
	{
		$version = new JVersion;
		$db = JFactory::getDBO();
		$db->setQuery("SELECT id, title, parent_id FROM #__phocadownload_categories WHERE published=1");
		$cats = $db->loadObjectList();
		$cats_ordered = array();
		$this->reorderCats($cats_ordered, $cats, 0, 0);
		return $cats_ordered;
	}

	function getDownloads($category)
	{
		$db = JFactory::getDBO();
		$db->setQuery('SELECT id, title FROM #__phocadownload WHERE catid = ' . (int)$category);
		return $db->loadObjectList();
	}

	//searches for a Download button, if payment is needed for this ressource it replaces the
	//text of the button to "Pay per download"
	function onAfterRender() {
		$access = JRequest::getVar('id', '');
		$session = JFactory::getSession();
		$s1=$session->getData();
		$s2=$session->getStores();
		$download_button_matches=array();
		$app = JFactory::getApplication();
		if($app->isAdmin()) {
			return;
		}


		$body = JResponse::getBody();
		$pattern = "/(<div class=\"pd-button-download\"><a class=\"btn btn-success\".*?download=(\d).*>)".JText::_('COM_PHOCADOWNLOAD_DOWNLOAD')."(<\/a>)/";
		if(preg_match_all($pattern, $body,$download_button_matches))
		{
				
			$option = JRequest::getVar('option');
			$db = JFactory::getDBO();
			$escaped_option = $db->escape($option);
			$query = "SELECT resource_license_id, resource_id, resource_type, license_id, resource_params, shared
			FROM #__payperdownloadplus_resource_licenses
			WHERE (resource_option_parameter = '$escaped_option' OR
			resource_option_parameter = '') AND #__payperdownloadplus_resource_licenses.enabled = 1";
			$db->setQuery( $query );
			$resources = $db->loadObjectList();
			$user = JFactory::getUser();
			if(count($resources) > 0)
			{
				foreach ($download_button_matches[2] as $download_id) {
					$shared = true;
					$allowAccess = true;
					JRequest::setVar("download",$download_id);
					$this->onValidateAccess($option, $resources, $allowAccess, $requiredLicenses, $resourcesId);
					$requiresPayment = false;
					$user = JFactory::getUser();
					if(!$allowAccess)
					{
						$decreaseDownloadCount = false;
						$this->onCheckDecreaseDownloadCount($option, $resources, $requiredLicenses, $resourcesId, $decreaseDownloadCount);
						$checkSession = true;
						$deleteResourceFromSession = false;
						$requiresPayment = true;
						if(count($resourcesId) > 0){
							$shared = $this->isResourceShared((int)$resourcesId[0]);
						}
						$item = $this->getItemForResource($option);
						if($user && $user->id)
						{
							if(
									$this->isPrivilegedUser($user) ||
									(count($requiredLicenses) > 0 && $this->isThereValidLicense($requiredLicenses, $decreaseDownloadCount, $item, $checkSession, $deleteResourceFromSession)) ||
									(count($resourcesId) > 0 && $this->isTherePaidResource($resourcesId, $item, $decreaseDownloadCount, $shared, $checkSession, $deleteResourceFromSession)))
							{
								$requiresPayment = false;
							}
						}
						else
						{
							if((count($resourcesId) > 0 && $this->isTherePaidResource($resourcesId, $item, $decreaseDownloadCount, $shared, $checkSession, $deleteResourceFromSession)))
							{
								$requiresPayment = false;
							}
						}
					}
					if($requiresPayment)
					{
						$pattern = "/(<div class=\"pd-button-download\"><a class=\"btn btn-success\".*?download=".$download_id.".*>)".JText::_('COM_PHOCADOWNLOAD_DOWNLOAD')."(<\/a>)/";

						$body = preg_replace($pattern, "$1 ". JText::_('PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_DOWNLOAD') . "$2", $body);
					}
				}
			}
				
				
			JResponse::setBody($body);
		}

	}

	function onRenderConfig($pluginName, $resource)
	{
		if($pluginName == "Phoca Download")
		{
			if($resource)
			{
				$content_id = $resource->resource_id;
				$category_id = $resource->resource_params;
				if($category_id)
					$files = $this->getDownloads($category_id);
			}
			$uri = JURI::root();
			$scriptPath = "administrator/components/com_payperdownload/js/";
			JHTML::script($scriptPath . 'ajax_source.js', false);
			$version = new JVersion;
			if($version->RELEASE >= "1.6"){
				$plugin_path = "plugins/payperdownloadplus/phocadownload/";
			}
			else {
				$plugin_path = "plugins/payperdownloadplus/";
			}
			$scriptPath = $uri . $plugin_path;
			JHTML::script ( $scriptPath . 'phoca_plugin.js', false );
			$cats = $this->getCategories ();
			?>
			<tr>
			<td align="left" class="key"><?php echo htmlspecialchars(JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_CATEGORY"));?></td>
			<td>
			<select id="phocadownload_category" name="phocadownload_category" onchange="phocadownload_plugin_category_change();">
			<option value="0"><?php echo JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_ALL_CATEGORIES");?></option>
			<?php
			foreach($cats as $cat)
			{
				$space = '';
				for($i = 0; $i < $cat->depth; $i++)
					$space .= '&nbsp;&nbsp;&nbsp;&nbsp;';
				$selected = $cat->id == $category_id ? "selected":"";
				echo "<option value=\"" . htmlspecialchars($cat->id) . "\" $selected>" . $space . htmlspecialchars($cat->title) . "</option>";
			}
			?>
			</select>
			</td>
			</tr>
			<tr>
			<td  width="100" align="left" class="key"><?php echo htmlspecialchars(JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_DOWNLOAD"));?></td>
			<td>
			<select id="phocadownload_file" name="phocadownload_file">
			<option value="0"><?php echo JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_ALL_DOWNLOADS");?></option>
			<?php
			if($files)
				foreach($files as $file)
				{
					$selected = $file->id == $content_id ? "selected":"";
					echo "<option value=\"" . htmlspecialchars($file->id) . "\" $selected>" . htmlspecialchars($file->title) . "</option>";
				}
			?>
			</select>
			&nbsp;
			<input type="text" id="search_text" />
			&nbsp;
			<input type="button" id="search_button" value="<?php echo JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_SEARCH")?>" onclick="phocadownload_plugin_search();" />
			<script type="text/javascript">
			var cancel_text = '<?php echo JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_CANCEL", true)?>';
			</script>
			<div id="search_result" style="position:absolute;visibility:hidden;border-width:1px;border-style:solid;background-color:#ffffff;"></div>
			</td>
			</tr>
			<?php
		}
	}
	
	function onGetSaveData(&$resourceId, 
		$pluginName, &$resourceName, &$resourceParams, &$optionParameter,
		&$resourceDesc)
	{
		if($pluginName == "Phoca Download")
		{
			$optionParameter = "com_phocadownload";
			$resourceId = JRequest::getInt('phocadownload_file');
			$categoryId = JRequest::getInt('phocadownload_category');
			$db = JFactory::getDBO();
			$query = "";
			$resourceDesc = JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_ALL_DOWNLOADS");
			if($resourceId)
			{
				$query = "SELECT id, title FROM #__phocadownload WHERE id = " . $resourceId;
				$resourceDesc = JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_CONTENT_ARTICLE");
			}
			else if($categoryId)
			{
				$resourceId = -1;
				$query = "SELECT id, title FROM #__phocadownload_categories WHERE id = " . $categoryId;
				$resourceDesc = JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_CONTENT_CATEGORY");
			}
			else
				$resourceId = -1;
			$resourceName = JText::_("PAYPERDOWNLOADPLUS_PHOCADOWNLOAD_PLUGIN_ALL_DOWNLOADS");
			if($query)
			{
				$db->setQuery( $query );
				$resource = $db->loadObject();
				if($resource)
					$resourceName = $resource->title;
			}
			
			$resourceParams = $categoryId;
		}
	}
	
	function getParentCategories($id)
	{
		$db = JFactory::getDBO();
		$db->setQuery("SELECT catid FROM #__phocadownload WHERE id = " . (int)$id);
		$category = $db->loadResult();
		$parentCategories = array();
		while($category)
		{
			$parentCategories []= $category;
			$db->setQuery("SELECT parent_id FROM #__phocadownload_categories WHERE id = " . (int)$category);
			$category = (int)$db->loadResult();
		}
		return $parentCategories;
	}
	
	function onValidateAccess($option, $resources, &$allowAccess, &$requiredLicenses, &$resourcesId)
	{
		if($option == 'com_phocadownload')
		{
			$requiredLicenses = array();
			$download = JRequest::getInt('download', 0);
			$resourcesId = array();
 			if($download == 0){
 				return;
 			}
			$view = JRequest::getVar('view');
			if($view != 'file' && $view != 'category') {
				return;
			}
			//Check download licenses
			foreach($resources as $resource)
			{
				if($resource->resource_id == $download)
				{
					if($resource->license_id)
					{
						if(array_search($resource->license_id, $requiredLicenses) === false)
							$requiredLicenses[] = $resource->license_id;
					}
					else
					{
						if(array_search($resource->resource_license_id, $resourcesId) === false)
							$resourcesId[] = $resource->resource_license_id;
					}
					$allowAccess = false;
				}
				
			}
			
			$download_categories = $this->getParentCategories($download);
			//Check category licenses
			foreach($resources as $resource)
			{
				if($resource->resource_id < 0)
				{
					$categoryId = $resource->resource_params;
					if(array_search($categoryId, $download_categories) !== false)
					{
						if($resource->license_id)
						{
							if(array_search($resource->license_id, $requiredLicenses) === false)
								$requiredLicenses[] = $resource->license_id;
						}
						else
						{
							if(array_search($resource->resource_license_id, $resourcesId) === false)
								$resourcesId[] = $resource->resource_license_id;
						}	
						$allowAccess = false;
					}
				}
			}
			
			//Check all downloads license
			foreach($resources as $resource)
			{
				if($resource->resource_id < 0)
				{
					$categoryId = $resource->resource_params;
					if($categoryId == 0)
					{
						if($resource->license_id)
						{
							if(array_search($resource->license_id, $requiredLicenses) === false)
								$requiredLicenses[] = $resource->license_id;
						}
						else
						{
							if(array_search($resource->resource_license_id, $resourcesId) === false)
								$resourcesId[] = $resource->resource_license_id;
						}
						$allowAccess = false;
					}
				}
			}
		}
	}
	
	function onAjaxCall($plugin, &$output)
	{
		if($plugin == "phocadownload")
		{
			$t = JRequest::getVar('t');
			$x = JRequest::getVar('x');
			if($t == 's')
			{
				$db = JFactory::getDBO();
				$db->setQuery("SELECT id, title, catid FROM #__phocadownload WHERE title LIKE '%" . $db->escape($x) . "%'");
				$files = $db->loadObjectList();
				$output = '<<' . count($files);
				foreach($files as $file)
				{
					$output .= '>' . htmlspecialchars($file->id) . "<" . htmlspecialchars($file->title) . "<" . htmlspecialchars($file->catid);
				}
				$output .= '>>';
			}
			else
			if($t == 'a')
			{
				$db = JFactory::getDBO();
				$db->setQuery('SELECT id, title FROM #__phocadownload WHERE catid = ' . (int)$x);
				$files = $db->loadObjectList();
				$output = '<<' . count($files);
				foreach($files as $file)
				{
					$output .= '>' . htmlspecialchars($file->id) . "<" . htmlspecialchars($file->title);
				}
				$output .= '>>';
			}
		}
	}
	
	/*function getReturnPage($option, &$returnPage)
	{
		if($option == "com_phocadownload")
		{
			$download = JRequest::getInt('download', 0);
			if(!$download)
			{
				return;
			}
			$db = JFactory::getDBO();
			$db->setQuery("SELECT catid FROM #__phocadownload WHERE id = " . $download);
			$download_category = (int)$db->loadResult();
			$phoceRouterHelperFile = 
				JPATH_SITE . "/components/com_phocadownload/helpers/route.php";
			if(file_exists($phoceRouterHelperFile))
			{
				$alias = "";
				$db->setQuery("SELECT id, alias FROM #__phocadownload_categories WHERE id = " . (int)$download_category);
				$cat = $db->loadObject();
				if($cat)
					$alias = $cat->alias;
				require_once($phoceRouterHelperFile);
				if(class_exists("PhocaDownloadHelperRoute"))
				{
					$uri = JRoute::_(PhocaDownloadHelperRoute::getCategoryRoute($download_category, $alias));
					$protocol = $_SERVER['SERVER_PROTOCOL'];
					if(strtolower(substr($protocol, 0, 5)) == 'https')
						$prefix = "https://";
					else
						$prefix = "http://";
					$port = $_SERVER['SERVER_PORT'];
					if($port == '80')
						$port = '';
					else
						$port = ':' . $port;
					$returnPage = $prefix . $_SERVER['SERVER_NAME'] . $port . $uri;
					return;
				}
			}
			$returnPage = "index.php?option=com_phocadownload&view=category&id=" . 
					urlencode($download_category);
		}
	}*/
	
	function onCheckDecreaseDownloadCount($option, $resources, $requiredLicenses, $resourcesId, &$decreaseDownloadCount)
	{
		if($option == 'com_phocadownload')
		{
			$decreaseDownloadCount = true;
		}
	}
	
	/*
	Returns item id for current download. In the case of Phocadownload it is the id of 
		the download
	*/
	function onGetItemId($option, &$itemId)
	{
		if($option == 'com_phocadownload')
		{
			$itemId = JRequest::getInt('download', 0);
		}
	}
	
}
?>